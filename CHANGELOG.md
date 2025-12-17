# Changelog

## [Unreleased]

## [1.0.6]
### Added
- **Excel (XLSX) export** for time entries.
- **Flexible HR access rules:** admins can define which HR groups are allowed to view which employee groups.

### Improved
- **Date range selection** for excel exports to include multiple months.

## [1.0.5]
### Added
- New HR statistics panel in the HR view: shows total working hours, total overtime, and how many employees currently have positive or negative overtime for the selected month.
- Month labels in both personal and HR views are now clickable – a single click jumps back to the current month, making navigation much faster.

### Improved
- The description now explicitly mentions that, when a German federal state is configured, public holidays of that state are shown in the calendar.

### Fixed
- HR configuration saving now correctly updates the selected employee’s settings instead of the HR user’s own configuration.
- Negative overtime values are now handled correctly in all statistics, so monthly totals are accurate.
- The holiday logic no longer falls back to “BY” (Bavaria) when no state is selected; leaving the state empty means no public holidays are applied.

## [1.0.4]
### Added
- HR settings can now be managed more comfortably in the admin area: you can select multiple HR groups and multiple “employee groups” directly from the list of all Nextcloud groups.
- HR users can now work with several employee groups at once in the HR overview (instead of a single fixed group).
- Full localization of the app UI: Timesheet now supports both English and German and automatically follows the user’s Nextcloud language.
- CSV export for both personal and HR views: users can download the current month’s timesheet including all table columns and key summary information (user, worked hours, overtime, daily working time).

### Fixed
- Fixed an issue where time entries created by an HR user in another employee’s timesheet were sometimes saved under the HR user instead of the selected employee.
- Fixed an issue where the “Difference” column was not cleared correctly when a row was deleted or reset.