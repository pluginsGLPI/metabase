# Plugin GLPI for metabase integration

![metabase illustration](https://www.metabase.com/images/dashboard.png)

This plugin eases integration of GLPI with [Metabase](https://www.metabase.com/)
It actually permits to:
- [X] Connect to Metabase API.
- [X] Push database configuration or use existing configured db in metabase.
- [X] Push GLPI foreign keys in metabase datamodel
- [X] Push GLPI enumeration (tickets impacts/urgency/priority/types) in metabase datamodel
- [X] Push questions and collections (if exists).
- [X] Push dashboards (if exists).
- [X] Integrate Metabase dashboards into GLPI (on Central).
- [X] Profiles management (dashboards publication)
- [X] import existing questions/dashboards/collection from metabase and save them as json
- [ ] Check sync status

[Teclib'](http://www.teclib-group.com/) provides with [GLPI Network](https://services.glpi-network.com/) distribution, aditionnal services like support for installation, questions and dashboards conception.

**[Contact Teclib'](https://services.glpi-network.com/)** for more information.

## Installation

The plugin requires a existing instance of [Metabase](https://www.metabase.com/start/) even without database setup (but with an existing admin user).

Install the glpi plugin as usual, and in plugin configuration (Setup > General, metabase tab), follow the process:

- Setup host and credentials.
- Choose (or create from glpi) a database.
- Generate datemodel.
- Push question and dashboards.

## Screenshots

### Teclib Questions and Dashboards

![Teclib Helpdesk dashboard](screenshots/teclib_helpdesk.png)
![Teclib Assets dashboard](screenshots/teclib_assets.png)

### Configuration in Setup > General

![metabase plugin configuration](screenshots/configuration.png)

### Display dashboards in central page

![central page](screenshots/central.png)
