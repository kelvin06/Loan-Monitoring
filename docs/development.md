# Development and deployment

## API setup

Use PHP 8.4.1+ and Node.js 26. From the repo root, run `. .\scripts\use-toolchain.ps1` in PowerShell to select the installed PHP 8.4.5 for that terminal. In `backend/`, run `composer install`, copy `.env.example` to `.env`, create the MySQL database, then run `php artisan key:generate`, `php artisan migrate`, and `php artisan serve`. The local cache uses files; application records and web sessions use MySQL. Never expose credentials in git.

The admin workspace includes loan categories, down payment rates, interest rates, penalties, loans, and payment schedules. Enter percentage rates as decimal fractions (for example, `0.10` for 10%). Monthly amortization is calculated as `(interest rate × loan amount) + (loan amount ÷ months to pay)` and down payment as `loan amount × down payment rate`. Saving a loan generates one ledger installment per month from the start date. Paid installments cannot be edited through the loan form; payment reference images are stored privately and served only through the authenticated admin API.

## Web and mobile

Run `npm install` and `npm run dev` in `apps/web` or `apps/mobile`. Set `VITE_API_URL` to the API origin plus `/api/v1`. For web, configure Sanctum stateful domains, session domain, HTTPS-only HttpOnly cookies in production, CSRF cookie initialization, and CORS with credentials. For mobile, set a native API origin and replace the Capacitor Preferences token adapter with a Keychain/Keystore-backed secure storage plugin before release; tokens must never use ordinary preferences.

Build the native shells after installing dependencies with `npx cap add android` or `npx cap add ios`, then `npx cap sync`. Android releases require a protected signing key and Android Studio. iOS builds and signing require macOS and Xcode. Neither signing credentials nor production secrets belong in source control.

## Operational notes

Use HTTPS, least-privilege database credentials, backups, monitored logs with token/password redaction, and rate limiting in production. Configure a health check, queue worker, and deployment pipeline for lint/build/backend test jobs. API v1 remains supported during a minimum six-month deprecation window; announce deprecation with `Deprecation` and `Sunset` headers and migrate clients before removing endpoints.
