# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [10.1.2] - 2026-02-27

### Changed

- Bumped the MYSQL version in the CI pipeline to 9.6.0
- Bumped the Moodle version in the CI pipeline to 5.1
- Ensured compatibility with Moodle 5.1

### Fixed

- Code style fixes

## [10.1.1] - 2025-12-09

### Fixed

- Updated code style to match current Moodle coding standards

## [10.1.0] - 2025-07-23

### Added

- Button group for repository targets (landing pages)

## [10.0.0] - 2025-07-14

### Changed

- Bumped the PHP version in the CI pipeline to 8.4
- Bumped the MYSQL version in the CI pipeline to 9.3.0
- Bumped the Moodle version in the CI pipeline to 5.0
- Ensured compatibility with Moodle 5.0

## [9.0.0] - 2025-01-07

### Added

- French translation file

## [8.1.1] - 2024-11-07

### Changed

- Updated Moodle CI and changed code to match current criteria
- Ensured compatibility with Moodle 4.5

## [8.1.0] - 2024-05-02

### Changed

- Major refactoring to update plugin to current Moodle CI requirements

### Added

- GitLab CI pipeline including Moodle CI checks

## [8.0.3] - 2024-03-16

### Changed

- Moved JS to module, refactored locallib class

## [8.0.2] - 2024-01-26

### Added

- Submitted edu-sharing files can now be replaced or removed in edit mode
- Updated german language string for search button to include hyphen before "Repositorium"
- Query parameter added to repository URL to prevent references to be added as submissions

### Changed

- Landing page in ES-Repository changed from "search" to "workspace"

## [8.0.1] - 2024-01-14

### Changed

- Refactored code and doc blocks to conform with moodle guidelines

##  [8.0.0] - 2023-10-01

### Added

- Compatibility with version 8.0.0 of the edu-sharing activity module
- Functionality can now be used by students

### Removed

- Moodle legacy functions in event declarations

### Fixed

- Filename field in dialogue module can no longer be edited

### Changed

- Minor refactoring
