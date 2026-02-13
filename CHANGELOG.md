# Changelog

## [1.1.6]
### Improved
- Entering times is now smoother: if only a start or end time is filled, you get clearer feedback and can still save your progress.
- Clearing time fields works more reliably, so fields are fully reset when removed.
- Saving entries is more stable during quick edits, reducing issues when multiple saves happen close together.
- The break mode switch was redesigned for a cleaner, more modern look and easier use.
- Export options are now clearer and better aligned, with improved input styling for a more consistent interface.

## [1.1.5]
### Fixes
- Employees can now access the special-days check in settings without needing admin rights, as long as they are logged in.

### Added
- German wording was added for HR and employee group access rules.

## [1.1.4]
### Fixed
- Fixed a date issue that could stop timesheet entries from displaying on the last day of a month.

## [1.1.3]
### Added
- More HR rule options, including priority, break times, and maximum hours.
- Clearer info boxes in the HR dashboard.

### Improved
- HR rules now keep each employee group in only one rule to avoid conflicts, with automatic cleanup.
- HR screens are easier to use, with better spacing, inputs, and responsiveness on different screen sizes.

## [1.1.2]
### Improved
- HR layout on different screen sizes.

### Fixed
- Visual appearance of comment input fields inside tables.

## [1.1.1]
### Added
- Another screenshot to the storepage

### Fixed
- Missing app icon in the Application View field in "Apps"

## [1.1.0]
### Added
- New HR email notifications: a optional weekly summary reminder when employees haven’t logged timesheet entries for a while.
- Optional warnings for excessive overtime and for too many negative hours.
- Configurable thresholds (days/hours) directly in the HR UI.

### Improved
- New “HR Notifications” settings section in the HR view for quick configuration.
- Full English/German wording for all new UI labels and email texts.

## [1.0.9]
### Fixed
- Rebuild vendor folder, App now using correct dependency versions

## [1.0.8]
### Fixed
- Fixed missed version bump in appinfo/info.xml to ensure proper updates.

## [1.0.7]
### Fixed
- Fixed an issue where the **Excel (XLSX) export** could fail for some installations (ZipStream-related error).

### Improved
- Improved the **visual consistency of the app icon** in the admin settings (now uses scalable SVG icons).

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
