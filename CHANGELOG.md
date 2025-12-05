# Changelog

## [Unreleased]

## [1.0.4]
### Added
- HR settings can now be managed more comfortably in the admin area: you can select multiple HR groups and multiple “employee groups” directly from the list of all Nextcloud groups.
- HR users can now work with several employee groups at once in the HR overview (instead of a single fixed group).
- Full localization of the app UI: Timesheet now supports both English and German and automatically follows the user’s Nextcloud language.
- CSV export for both personal and HR views: users can download the current month’s timesheet including all table columns and key summary information (user, worked hours, overtime, daily working time).

### Fixed
- Fixed an issue where time entries created by an HR user in another employee’s timesheet were sometimes saved under the HR user instead of the selected employee.
- Fixed an issue where the “Difference” column was not cleared correctly when a row was deleted or reset.
