include ../../PluginsMakefile.mk

# Absolute path to this plugin's own .dev directory, referenced as ${METABASE_DEV_DIR}
# in .dev/docker-compose.yml: Compose resolves relative volume paths against the
# directory of the first -f file, not per-file, so a bare relative path breaks once
# merged with the core compose file.
export METABASE_DEV_DIR = $(CURDIR)/.dev

up: ## Start the local dev services (see .dev/docker-compose.yml)
	@$(COMPOSE) -f ../../docker-compose.yaml -f .dev/docker-compose.yml --project-directory ../.. up -d metabase
.PHONY: up

down: ## Stop the local dev services
	@$(COMPOSE) -f ../../docker-compose.yaml -f .dev/docker-compose.yml --project-directory ../.. down
.PHONY: down

config: ## Print the values to enter in GLPI's Setup > Metabase config page
	@echo "host: metabase"
	@echo "port: 3000"
	@echo "username: admin@glpi.local"
	@echo "password: stk-dev-admin1"
	@echo "metabase_url: http://localhost:$${METABASE_PORT:-3010}/"
	@echo "embedded_token: 2628b70987266db59aa1e05bb06afa2a620a75548569263774d26ca7a3357797"

.PHONY: config
