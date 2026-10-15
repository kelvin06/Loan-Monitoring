# Loan Monitoring

Cross-platform loan monitoring foundation: Laravel JSON API, Vue web portal, and Ionic Vue mobile client.

## Requirements

- PHP 8.4.1+ and Composer 2.8+
- Node.js 20.19+ or 22.12+, npm 11+
- MySQL 8.0+
- Android Studio for Android; macOS with Xcode for iOS

This workspace uses PHP 8.4.5 and Node.js 26.12.0. Run `. .\scripts\use-toolchain.ps1` in PowerShell to prepend the installed PHP 8.4 binary to the current terminal and verify both versions. `.nvmrc` selects Node 26 for nvm users. Laravel 13.36.0 and the current locked dependencies need PHP 8.4.1+.

## Layout

- `backend/` Laravel API (Sanctum, role middleware, API resources, versioned routes)
- `apps/web/` Vue 3 + TypeScript + Vite web portal
- `apps/mobile/` Ionic Vue + Capacitor app scaffold
- `packages/api-client/` platform-neutral typed HTTP client
- `packages/shared-types/` API contract types
- `docs/openapi.yaml` API contract

## Development

See [docs/development.md](docs/development.md) for setup, authentication, API, and deployment notes. This repository is a starter implementation; payment/loan workflows and production deployment credentials are intentionally not fabricated.
