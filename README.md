<<<<<<< HEAD
# MarketMind AI (core build)
PHP 8+ / MySQL / Bootstrap / Chart.js stock intelligence platform.

## Setup
1. `mysql -u root < sql/schema.sql`
2. `cp .env.example .env` and fill DB credentials (+ `ANTHROPIC_API_KEY` for AI features).
3. `php -S localhost:8000` in this folder (or place in XAMPP htdocs) and open http://localhost:8000
Requires PHP `curl` and `pdo_mysql` extensions.

## Included
Auth (hashed passwords, sessions, CSRF), market data + history, line chart with SMA20/50, RSI, watchlist, price/% alerts (checked every 30s while open) with in-app notifications, virtual portfolio ($10,000), AI insight/assistant (server-side key).

## Notes
- Market data uses Yahoo Finance's unofficial endpoint, falling back to clearly labelled demo data. Replace `hist()` in api.php with a licensed provider for production.
- Not financial advice; no real-money trading.
- Promote an admin: `UPDATE users SET role='admin' WHERE email='you@x.com';`
=======
# market-mind
>>>>>>> 91b00d78cf6f8a7c76c77fcf1c91c551224840af
