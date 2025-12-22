# Nextcloud Timesheet (development)

Simple Timesheet app for Nextcloud — lightweight time tracking for users. This repository contains the app source intended for local Nextcloud Docker development.

## Features
- Track time
- Per-user entries and export
- Simple UI integrated into Nextcloud

## Prerequisites
- Nextcloud (tested with Nextcloud 20+)
- PHP 7.4+ (as required by the Nextcloud instance)
- Docker & docker-compose (for local development)
- composer (for PHP dependencies)
- npm/node (if the app contains frontend assets)

## Development notes
- Follow Nextcloud server API and app development guidelines for compatibility.

## Project structure

### App metadata & routing
- `appinfo/info.xml`  
  Declares the app ID, name, description, dependencies, navigation entry and admin settings section.
- `appinfo/routes.php`  
  Defines HTTP routes for the main page (`timesheet.page.index`) and REST API endpoints (entries, HR overview, config, holidays, admin settings).

### Controllers
- `lib/Controller/PageController.php`  
  Renders the main app UI, registers JS/CSS and loads translations for the browser.
- `lib/Controller/EntryController.php`  
  REST API for creating, updating and deleting timesheet entries (for the current user or, if HR, for selected employees).
- `lib/Controller/ConfigController.php`  
  REST API for per-user configuration (daily working minutes, state) including access checks.
- `lib/Controller/OverviewController.php`  
  REST API for the HR overview (employee list, overtime summary, last entry, warnings).
- `lib/Controller/SettingsController.php`  
  Handles admin-side updates of HR groups and employee groups from the settings UI.

### Settings & services
- `lib/Settings/AdminSettings.php`  
  Provides the admin settings form (HR groups / employee groups) and passes data to the template.
- `lib/Settings/AdminSection.php`  
  Registers the app’s admin section in the Nextcloud administration settings.
- `lib/Service/HrService.php`  
  Centralized HR permission logic: resolves HR groups from app config and checks whether a user is an HR member.
- `lib/Service/EntryService.php`  
  Business logic for creating and updating `Entry` records (including user assignment and default values).

### Templates (UI)
- `templates/main.php`  
  Main UI template: tabs for “Timesheet” and “HR overview”, personal timesheet table, HR user list and HR detail view.
- `templates/settings-admin.php`  
  Admin settings template for managing HR groups and employee groups (multi-select with chips/X for removal).

### Frontend code
- `js/timesheet-main.js`  
  Main frontend logic: loading/saving rows, calculating duration and difference, monthly/overtime summary, HR employee table and detail view.
- `js/admin.js`  
  Admin-side JS for dynamically adding/removing HR groups and employee groups via AJAX (auto-save, no explicit save button).

### Localization
- `l10n/de.json`  
  Server-side translation files used by PHP (`$l->t(...)`) and templates.
- `l10n/de.js`  
  Client-side translation registration for JavaScript (`t('timesheet', ...)`), loaded via `Util::addTranslations('timesheet')`.

## Contributing
- Fork, create a feature branch, and open a pull request.

## License
GNU Affero General Public License v3.0 — see LICENSE file.

For issues or questions, open an issue in this repository.
