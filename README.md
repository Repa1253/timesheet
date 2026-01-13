# Nextcloud Timesheet (development)

A lightweight Timesheet app for Nextcloud. This repository contains the app source intended for local Nextcloud Docker development.

## Features
- Time entries
- HR overview (accessible users + overtime summary)
- XLSX export
- Optional holiday integration
- Automated HR reminder emails (background job)

## Requirements
- Nextcloud 30–32
- PHP 8.2+
- Docker & docker-compose (optional, for local development)
- Composer (for PHP dependencies)
- Node.js / npm (optional, for frontend assets)

## Development notes
- Follow Nextcloud server API and app development guidelines for compatibility.

## Project structure

### App metadata & routing
- `appinfo/info.xml`  
  App metadata & registration: name/id, dependencies, navigation entry, settings, and background jobs.
- `appinfo/routes.php`  
  Defines the app’s HTTP routes: UI entry point and API endpoints (e.g., entries, HR overview, settings).

### Bootstrap
- `lib/AppInfo/Application.php`
  Ensures the HR notification background job is registered.

### Controllers
- `lib/Controller/PageController.php`  
  Renders the main UI and loads JS/CSS assets.
- `lib/Controller/EntryController.php`  
  REST API for timesheet entries + XLSX export (HR can optionally access other users).
- `lib/Controller/ConfigController.php`  
  Reads/writes per-user config and HR notification settings with access checks.
- `lib/Controller/OverviewController.php`  
  HR endpoints: list accessible users + compute overtime summary.
- `lib/Controller/HolidayController.php`  
  Public endpoint to fetch holidays via `HolidayService`.
- `lib/Controller/SettingsController.php`  
  Admin endpoints for HR group setup and HR access rules stored in app config.

### Services
- `lib/Service/EntryService.php`  
  Core entry CRUD logic with permission checks (HR vs. normal user).
- `lib/Service/HrService.php`  
  Central HR permission model: “who is HR” + “which users HR can access” (group/rule based).
- `lib/Service/HrNotificationService.php`  
  Builds the HR warning payload (no-entry / overtime / negative overtime) used by the timed job.
- `lib/Service/HolidayService.php`  
  Fetches holidays from an external API and caches them in Nextcloud app data.

### Settings (Admin)
- `lib/Settings/AdminSection.php`  
  Registers the admin settings section for this app in Nextcloud.
- `lib/Settings/AdminSettings.php`  
  Defines the admin settings page/form and ties it to stored app config.

### Background job (TimedJob)
- `lib/BackgroundJob/HrNotificationJob.php`  
  Scheduled job that periodically triggers HR notification logic and sends reminder emails.

### Templates (UI)
- `templates/main.php`  
  Main server-rendered page template for the app UI (loaded by `PageController`).
- `templates/settings-admin.php`  
  Admin settings template shown in Nextcloud’s admin area.

### Frontend (JavaScript)
- `js/timesheet-main.js`  
  Frontend entry point: bootstraps the timesheet UI.
- `js/timesheet-core.js`  
  Shared frontend base (common helpers / shared logic used by other modules).
- `js/timesheet-entries.js`  
  Handles entry UI + API calls for creating/updating/deleting entries.
- `js/timesheet-export.js`  
  Export UI/flow (triggers XLSX export and related actions).
- `js/timesheet-hr.js`  
  HR UI logic (overview, user selection, HR-specific actions).
- `js/timesheet-config.js`  
  User configuration UI logic (load/save settings).
- `js/timesheet-copy.js`  
  Copy/clipboard helpers used across the UI.
- `js/admin.js`  
  Admin settings page behavior (client-side interactions).

### Localization
  Add a new language by adding matching `l10n/<locale>.json` and `l10n/<locale>.js`.
- `l10n/*.json`  
  Server-side translations used by PHP (`$l->t(...)`) and templates.
- `l10n/*.js`  
  Client-side translations for JavaScript (`t('timesheet', ...)`), loaded via Nextcloud’s translation utilities.  

### Dependencies
- `composer.json`  
  Composer dependencies and tooling configuration.

## Contributing
- Fork, create a feature branch, and open a pull request.

## License
GNU Affero General Public License v3.0 — see LICENSE file.

For issues or questions, open an issue in this repository.
