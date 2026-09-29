#!/bin/sh
# Runs Metabase's first-time setup (or confirms an existing admin account
# still works on a re-run), then sets a fixed embedding secret key and
# enables static embedding, via the Metabase REST API.
# Idempotent: reuses the existing admin account and re-applies the settings
# on every run.
set -eu

apk add --no-cache curl jq >/dev/null

BASE_URL="http://metabase:3000"
ADMIN_EMAIL="admin@glpi.local"
ADMIN_PASSWORD="stk-dev-admin1"
SECRET_KEY="2628b70987266db59aa1e05bb06afa2a620a75548569263774d26ca7a3357797"

api() {
  method="$1"; path="$2"; body="${3:-}"
  if [ -n "$body" ]; then
    if [ -n "${SESSION:-}" ]; then
      curl -sf -X "$method" "$BASE_URL$path" -H "Content-Type: application/json" -H "X-Metabase-Session: $SESSION" -d "$body"
    else
      curl -sf -X "$method" "$BASE_URL$path" -H "Content-Type: application/json" -d "$body"
    fi
  else
    if [ -n "${SESSION:-}" ]; then
      curl -sf -X "$method" "$BASE_URL$path" -H "X-Metabase-Session: $SESSION"
    else
      curl -sf -X "$method" "$BASE_URL$path"
    fi
  fi
}

props=$(api GET /api/session/properties)
has_user_setup=$(printf '%s' "$props" | jq -r '.["has-user-setup"]')

if [ "$has_user_setup" = "true" ]; then
  SESSION=$(api POST /api/session "$(jq -n --arg u "$ADMIN_EMAIL" --arg p "$ADMIN_PASSWORD" '{username:$u,password:$p}')" | jq -r '.id')
else
  setup_token=$(printf '%s' "$props" | jq -r '.["setup-token"]')
  SESSION=$(api POST /api/setup "$(jq -n --arg t "$setup_token" --arg e "$ADMIN_EMAIL" --arg p "$ADMIN_PASSWORD" \
    '{token:$t, user:{first_name:"Stk", last_name:"Dev", email:$e, password:$p}, prefs:{site_name:"GLPI Dev", allow_tracking:false}}')" | jq -r '.id')
fi

api PUT /api/setting/embedding-secret-key "$(jq -n --arg v "$SECRET_KEY" '{value:$v}')" >/dev/null
api PUT /api/setting/enable-embedding-static '{"value":true}' >/dev/null

echo "metabase: dev admin account and static embedding ready"
