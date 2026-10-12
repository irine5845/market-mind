<?php
require __DIR__.'/config.php';
header('Content-Type: application/json');
$in=json_decode(file_get_contents('php://input'),true)?:[];
$a=$_GET['a']??'';
function out($d,$c=200){http_response_code($c);echo json_encode($d);exit;}
function uid(){if(empty($_SESSION['uid']))out(['error'=>'Login required'],401);return(int)$_SESSION['uid'];}
function sym($s){return substr(preg_replace('/[^A-Z0-9.\-]/','',strtoupper($s)),0,12);}
if($_SERVER['REQUEST_METHOD']==='POST'&&!in_array($a,['login','register'])&&!hash_equals($_SESSION['csrf']??'x',$_SERVER['HTTP_X_CSRF']??''))out(['error'=>'Bad CSRF token'],403);

function hist($s,$r='3mo'){
 $r=in_array($r,['5d','1mo','3mo','6mo','1y','5y'])?$r:'3mo';
 $f=sys_get_temp_dir()."/mm_{$s}_$r.json";
 if(is_file($f)&&time()-filemtime($f)<60)return json_decode(file_get_contents($f),true);
 $ch=curl_init("https://query1.finance.yahoo.com/v8/finance/chart/$s?range=$r&interval=1d");
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>6,CURLOPT_USERAGENT=>'Mozilla/5.0']);
 $j=json_decode((string)curl_exec($ch),true);$res=$j['chart']['result'][0]??null;$rows=[];
 if($res){$q=$res['indicators']['quote'][0];foreach($res['timestamp']as$i=>$t)if(isset($q['close'][$i]))$rows[]=['t'=>date('Y-m-d',$t),'o'=>$q['open'][$i],'h'=>$q['high'][$i],'l'=>$q['low'][$i],'c'=>$q['close'][$i],'v'=>$q['volume'][$i]];}
 $demo=!$rows;
 if($demo){mt_srand(crc32($s));$p=50+mt_rand(0,250);$n=['5d'=>5,'1mo'=>22,'3mo'=>66,'6mo'=>132,'1y'=>252,'5y'=>1260][$r];
  for($i=$n;$i>0;$i--){$o=$p;$p*=1+mt_rand(-200,210)/10000;$rows[]=['t'=>date('Y-m-d',strtotime("-$i day")),'o'=>round($o,2),'h'=>round(max($o,$p)*1.005,2),'l'=>round(min($o,$p)*.995,2),'c'=>round($p,2),'v'=>mt_rand(1000000,9000000)];}}
 $d=['demo'=>$demo,'rows'=>$rows];file_put_contents($f,json_encode($d));return $d;}
function quote($s){$h=hist($s,'1mo');$r=$h['rows'];$n=count($r);if(!$n)return null;$l=$r[$n-1];$p=$r[$n-2]['c']??$l['c'];
 return['symbol'=>$s,'price'=>round($l['c'],2),'change'=>round($l['c']-$p,2),'pct'=>round(($l['c']-$p)/$p*100,2),'volume'=>$l['v'],'demo'=>$h['demo']];}

switch($a){
case 'register':
 $e=filter_var($in['email']??'',FILTER_VALIDATE_EMAIL);$p=$in['password']??'';
 if(!$e||strlen($p)<8)out(['error'=>'Valid email and 8+ char password required'],422);
 try{db()->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)')->execute([$e,password_hash($p,PASSWORD_DEFAULT)]);
  $id=db()->lastInsertId();db()->prepare('INSERT INTO portfolios(user_id) VALUES(?)')->execute([$id]);}
 catch(PDOException $x){out(['error'=>'Email already registered'],409);}
 out(['ok'=>1]);
case 'login':
 $s=db()->prepare('SELECT * FROM users WHERE email=?');$s->execute([$in['email']??'']);$u=$s->fetch();
 if(!$u||!password_verify($in['password']??'',$u['password_hash']))out(['error'=>'Invalid credentials'],401);
 session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(16));
 out(['user'=>$u['email'],'csrf'=>$_SESSION['csrf']]);
case 'me':
 $u=uid();$s=db()->prepare('SELECT email FROM users WHERE id=?');$s->execute([$u]);out(['user'=>$s->fetchColumn(),'csrf'=>$_SESSION['csrf']]);
case 'logout': session_destroy();out(['ok'=>1]);
case 'history': out(hist(sym($_GET['s']??''),$_GET['r']??'3mo'));
case 'quotes': $o=[];foreach(array_slice(explode(',',$_GET['s']??''),0,25)as$s)if($q=quote(sym($s)))$o[]=$q;out($o);
case 'wl_list':
 $s=db()->prepare('SELECT symbol FROM watchlist WHERE user_id=?');$s->execute([uid()]);
 out(array_values(array_filter(array_map(fn($r)=>quote($r['symbol']),$s->fetchAll()))));
case 'wl_add': db()->prepare('INSERT IGNORE INTO watchlist VALUES(?,?)')->execute([uid(),sym($in['symbol']??'')]);out(['ok'=>1]);
case 'wl_del': db()->prepare('DELETE FROM watchlist WHERE user_id=? AND symbol=?')->execute([uid(),sym($in['symbol']??'')]);out(['ok'=>1]);
case 'al_add':
 if(!in_array($in['type']??'',['above','below','pct'])||!is_numeric($in['value']??''))out(['error'=>'Invalid alert'],422);
 db()->prepare('INSERT INTO alerts(user_id,symbol,type,value) VALUES(?,?,?,?)')->execute([uid(),sym($in['symbol']??''),$in['type'],$in['value']]);out(['ok'=>1]);
case 'al_list': $s=db()->prepare('SELECT * FROM alerts WHERE user_id=? ORDER BY id DESC');$s->execute([uid()]);out($s->fetchAll());
case 'al_del': db()->prepare('DELETE FROM alerts WHERE id=? AND user_id=?')->execute([(int)($in['id']??0),uid()]);out(['ok'=>1]);
case 'al_check':
 $u=uid();$s=db()->prepare('SELECT * FROM alerts WHERE user_id=? AND active=1');$s->execute([$u]);$hit=[];
 foreach($s->fetchAll()as$al){$q=quote($al['symbol']);if(!$q)continue;$v=(float)$al['value'];
  $ok=match($al['type']){'above'=>$q['price']>=$v,'below'=>$q['price']<=$v,'pct'=>abs($q['pct'])>=$v};
  if($ok){$m="{$al['symbol']} alert: {$al['type']} {$v} (price {$q['price']}, {$q['pct']}%)";
   db()->prepare('UPDATE alerts SET active=0,triggered_at=NOW() WHERE id=?')->execute([$al['id']]);
   db()->prepare('INSERT INTO notifications(user_id,message) VALUES(?,?)')->execute([$u,$m]);$hit[]=$m;}}
 out($hit);
case 'notifs': $s=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');$s->execute([uid()]);out($s->fetchAll());
case 'pf_get':
 $u=uid();$p=db()->prepare('SELECT * FROM portfolios WHERE user_id=?');$p->execute([$u]);$p=$p->fetch();
 $h=db()->prepare('SELECT * FROM holdings WHERE user_id=?');$h->execute([$u]);$rows=[];$tot=(float)$p['cash'];
 foreach($h->fetchAll()as$r){$q=quote($r['symbol']);$px=$q['price']??$r['avg_cost'];$val=$px*$r['qty'];$tot+=$val;
  $rows[]=['symbol'=>$r['symbol'],'qty'=>$r['qty'],'avg_cost'=>round($r['avg_cost'],2),'price'=>$px,'value'=>round($val,2),'pl'=>round($val-$r['avg_cost']*$r['qty'],2)];}
 out(['initial'=>(float)$p['initial'],'cash'=>(float)$p['cash'],'total'=>round($tot,2),'return_pct'=>round(($tot/$p['initial']-1)*100,2),'holdings'=>$rows]);
case 'trade':
 $u=uid();$s=sym($in['symbol']??'');$n=(int)($in['qty']??0);$side=$in['side']??'';$q=quote($s);
 if(!$q||$n<1||!in_array($side,['buy','sell']))out(['error'=>'Invalid trade'],422);
 $px=$q['price'];$db=db();$db->beginTransaction();
 $c=$db->prepare('SELECT cash FROM portfolios WHERE user_id=? FOR UPDATE');$c->execute([$u]);$cash=(float)$c->fetchColumn();
 if($side==='buy'){
  if($cash<$px*$n){$db->rollBack();out(['error'=>'Insufficient virtual cash'],422);}
  $db->prepare('UPDATE portfolios SET cash=cash-? WHERE user_id=?')->execute([$px*$n,$u]);
  $db->prepare('INSERT INTO holdings VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE avg_cost=(avg_cost*qty+VALUES(avg_cost)*VALUES(qty))/(qty+VALUES(qty)),qty=qty+VALUES(qty)')->execute([$u,$s,$n,$px]);
 }else{
  $h=$db->prepare('SELECT qty FROM holdings WHERE user_id=? AND symbol=? FOR UPDATE');$h->execute([$u,$s]);
  if((int)$h->fetchColumn()<$n){$db->rollBack();out(['error'=>'Not enough shares'],422);}
  $db->prepare('UPDATE holdings SET qty=qty-? WHERE user_id=? AND symbol=?')->execute([$n,$u,$s]);
  $db->prepare('DELETE FROM holdings WHERE user_id=? AND qty=0')->execute([$u]);
  $db->prepare('UPDATE portfolios SET cash=cash+? WHERE user_id=?')->execute([$px*$n,$u]);}
 $db->prepare('INSERT INTO transactions(user_id,symbol,side,qty,price) VALUES(?,?,?,?,?)')->execute([$u,$s,$side,$n,$px]);
 $db->commit();out(['ok'=>1,'price'=>$px]);
case 'ai':
 uid();$key=env('ANTHROPIC_API_KEY');if(!$key)out(['text'=>'AI is not configured. Set ANTHROPIC_API_KEY in .env.']);
 $ctx=json_encode($in['context']??[]);$qn=substr($in['question']??'Give a short insight on this stock.',0,500);
 $ch=curl_init('https://api.anthropic.com/v1/messages');
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>40,CURLOPT_POST=>1,
  CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01'],
  CURLOPT_POSTFIELDS=>json_encode(['model'=>env('AI_MODEL','claude-sonnet-4-6'),'max_tokens'=>600,
   'system'=>'You are MarketMind AI, an educational market analyst. Use only the supplied data. Separate indicators from interpretation. Never guarantee predictions. Be concise. This is informational, not financial advice.',
   'messages'=>[['role'=>'user','content'=>"Market data: $ctx\n\nQuestion: $qn"]]])]);
 $j=json_decode((string)curl_exec($ch),true);
 out(['text'=>$j['content'][0]['text']??'AI service unavailable.']);
default: out(['error'=>'Unknown action'],404);
}
