# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Fixed

- Fix question/dashboard extraction against current Metabase API versions (`ordered_cards` → `dashcards`, `sizeX`/`sizeY` → `size_x`/`size_y`, native question query moved to `dataset_query.stages[0].*`)
- Fix root collection questions never matching in `getCards('root')`
- Fix extraction AJAX URL resolving to the wrong host when `root_doc` is empty
- Fix `embedded_token` migration not actually encrypting the value on upgrade, breaking the embedded dashboard with an `InvalidKeyProvided` exception for any site that had a token configured before updating to 1.4.2

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
