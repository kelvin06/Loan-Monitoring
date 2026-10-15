# Project Guide

Read this file before changing the Loan Monitoring project. It summarizes the project structure, frontend conventions, and visual design to avoid repeated repository discovery.

## Project map

- `backend/`: Laravel 13 REST API, Sanctum authentication, MySQL persistence, API routes in `routes/api.php`.
- `apps/web/`: Vue 3 + TypeScript + Vite admin and user web portal.
- `apps/mobile/`: Ionic Vue + Capacitor mobile client.
- `packages/api-client/`: shared typed API client used by frontends.
- `packages/shared-types/`: shared API and domain types.
- `docs/openapi.yaml`: API contract.

Requirements and setup details are in `README.md` and `docs/development.md`. Use PHP 8.4 and Node.js 26 for local development.

## Frontend map

Web code lives in `apps/web/src/`:

- `App.vue`: app shell, routing, and admin side navigation.
- `views/LoginView.vue`: user and administrator login screens.
- `views/DashboardView.vue`: dashboard.
- `views/UserLoansView.vue`: borrower installment list and payment-reference upload.
- `views/UserMaintenanceView.vue`: admin user management.
- `views/CatalogMaintenanceView.vue`: loan categories, down payment rates, interest rates, and penalties.
- `views/LoanMaintenanceView.vue`: loan management and generated payment ledger.
- `components/ModalDialog.vue`: shared modal dialog for forms.
- `components/SortableTable.vue`: compact custom table with sortable columns.
- `style.css`: global styles, design tokens, and shared components.
- `admin.css`: admin navigation, tables, forms, ledger, and modal styles.
- `stores/auth.ts` and `api.ts`: frontend auth state and API setup.

Keep create/edit/payment forms in modals, use the shared API client and shared types, and retain responsive layouts for narrow screens. Tables use the lightweight custom table component; do not add AG Grid. Keep tables compact, with search/filter controls and sortable data columns. Use icon-only row actions with accessible labels and tooltips. Reuse the existing views and components before adding new dependencies or parallel abstractions. Keep login and admin styling aligned with the design system.

## Visual design

Use this palette consistently. The web and mobile apps follow the operating system appearance preference with `prefers-color-scheme`; keep both modes readable and do not add a separate theme switch unless requested.

| Element / Token | Light mode | Dark mode | Usage |
| --- | --- | --- | --- |
| Background (Base) | `#F9F9F9` | `#1A1A2E` | Main application backdrop and page canvas |
| Surface (Card/Modal) | `#FFFFFF` | `#16213E` | Elevated containers, cards, sidebar panels, and modals |
| Text (Primary) | `#1A1A2E` | `#F9F9F9` | Main headings, body text, and high-priority typography |
| Text (Secondary) | `#0F3460` | `#8C9BB0` | Subtitles, supporting details, and secondary labels |
| Brand / Accent | `#E94560` | `#E94560` | Primary buttons, calls to action, and active highlights |
| Interactive / Secondary Accent | `#0F3460` | `#0F3460` | Secondary buttons, links, and active tab backgrounds |
| Border / Divider | `#E2E4E8` | `#0F3460` | Subtle component outlines and structural dividers |

The crimson brand accent stays the same in both modes. Use white cards on the light canvas, navy surfaces above the dark canvas, and the secondary navy accent for interactive states. The light and dark appearance follows the operating system setting.

Font family: `Poppins, Arial, Verdana, sans-serif`. Poppins is not bundled; it is used when available on the device, with the remaining fonts as fallbacks.

The intended UI is compact and flat: favor clear hierarchy, restrained spacing, simple borders, and minimal shadows. Keep text and controls legible and maintain sufficient contrast. Define shared color tokens in `apps/web/src/style.css`; place admin-specific rules in `admin.css` and login-specific rules in the login view stylesheet. Ionic mobile color tokens live in `apps/mobile/src/theme.css`.

## Local commands

Web frontend, from `apps/web/`:

```powershell
npm install
npm run dev
npm run build
```

The Vite dev server prints its local URL. Configure `VITE_API_URL` to the Laravel API origin ending in `/api/v1`.

Laravel API, from `backend/`:

```powershell
composer install
php artisan serve
```

Configure `.env` for the local MySQL database before running migrations. Do not commit `.env`, passwords, tokens, or other secrets. Preserve existing user data when changing database setup.

## Project conventions

- Keep API request/response changes consistent across Laravel resources, OpenAPI, shared types, and the API client.
- Use existing authentication and role protections for admin-only operations.
- Rates are stored as decimal fractions (for example, `0.10` means 10%).
- Loan schedules are generated from the loan start date; payment references are private and require authenticated access.
- Avoid unrelated dependency upgrades and broad refactors when making focused UI changes.
- Run the relevant build or checks when requested, and report the exact command and result.
