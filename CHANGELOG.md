# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.5.0] - 2026-10-06

### Added

- GLPI 12 compatibility
- Local dev environment (Metabase instance + admin/embedding seeding) for plugin development

### Fixed

- Fixed Metabase compatibility with current API versions
- Fix `embedded-token` migration for existing configurations.
- Use libs provided by core.
- CI: fix Psalm cache directory, declare a unique composer autoloader suffix

## [1.4.2] - 2026-08-04

### Fixed

- Various minor fixes and hardening

## [1.4.1] - 2025-11-25

### Fixed

- Fix error message when assigning rights to a profile
- Fixes the display when there is no dashboard.

## [1.4.0] - 2025-09-26

### Added

- GLPI 11 compatibility
