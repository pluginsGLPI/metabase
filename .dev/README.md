# metabase plugin — local test environment

Adds a [Metabase](https://www.metabase.com/) instance for this plugin's dashboard-embedding
feature.

## What's here

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Purely additive: declares only `metabase` and `metabase-seed` (never touches `app`) |
| `seed.sh` | Runs Metabase's first-time setup, sets a fixed embedding secret key, enables static embedding |

## Usage

```bash
make up
make config
```

Then, in GLPI: *Setup → Metabase*, fill in the fields printed by `make config`
(`host`/`port` for the server-to-server API calls, `username`/`password` for the Metabase admin
account, `metabase_url` for the browser-facing iframe source, `embedded_token` as the embedding
signing key). Save, then use the plugin's own actions on that page:

- *Create GLPI database in local Metabase*: has the plugin connect Metabase directly to GLPI's own
  database, using this compose stack's `db` service (reachable at `db:3306`).
- *Push reports and dashboards in Metabase*: has the plugin build its bundled dashboards/cards
  against that database connection.

Neither of those two steps is pre-seeded here: they are exactly what the plugin's config page is
for, and exercising them for real is how this environment is validated.

The published port defaults to `3010`; override with `METABASE_PORT=<port>` if already taken.

## Field names

The plugin's config page (`inc/config.class.php`) uses `host`/`port`/`username`/`password` for the
Metabase API connection, `metabase_url` for the iframe source, and `embedded_token` for the
embedding secret key (despite the name, it is used directly as JWT signing key material in
`PluginMetabaseDashboard::showForCentral()`, not as a bearer token).
