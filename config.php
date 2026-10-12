<?php
$GLOBALS['env']=@parse_ini_file(__DIR__.'/.env')?:[];
function env($k,$d=''){return $GLOBALS['env'][$k]??(getenv($k)?:$d);}
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);
session_start();
function db(){static $p;return $p??=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';dbname='.env('DB_NAME','marketmind').';charset=utf8mb4',env('DB_USER','root'),env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
