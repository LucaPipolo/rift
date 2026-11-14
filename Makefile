# Every target runs inside the rift-dev container, so the host needs Docker
# and nothing else. Run `make` to list them.

export UID := $(shell id -u)
export GID := $(shell id -g)

RUN := docker compose run --rm php
BUN := docker compose run --rm bun
GO := docker compose run --rm go
DOCS := docker compose run --rm -w /app/docs bun
VHS := ghcr.io/charmbracelet/vhs:v0.10.0

TARGET := $(if $(filter Darwin,$(shell uname -s)),darwin,linux)-$(if $(filter arm64 aarch64,$(shell uname -m)),arm64,x86_64)

.DEFAULT_GOAL := help

# Treat goals after composer or bun as its arguments, including names of other targets.
ifneq ($(filter $(firstword $(MAKECMDGOALS)),composer bun go),)
TOOL := $(firstword $(MAKECMDGOALS))
TOOL_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
QUOTED_ARGS := $(foreach arg,$(TOOL_ARGS),'$(subst ','"'"',$(arg))')
.PHONY: $(TOOL) $(TOOL_ARGS)

composer: ## Run Composer, e.g. make composer -- update vendor/package -W
	$(RUN) composer $(QUOTED_ARGS)

bun: ## Run Bun, e.g. make bun install
	$(BUN) bun $(QUOTED_ARGS)

go: ## Run Go in dash/, e.g. make go mod tidy
	$(GO) go $(QUOTED_ARGS)

ifneq ($(strip $(TOOL_ARGS)),)
$(TOOL_ARGS): $(TOOL)
	@:
endif

else
.PHONY: help image test test\:php test\:go test\:types test\:coverage sniff lint format shell rift build demo docs\:install docs\:dev docs\:build docs\:preview docs\:lint docs\:format docs\:bun checksums clean

help: ## List the available targets
	@grep -E '^[a-z\\:-]+:.*## ' $(MAKEFILE_LIST) | awk -F ': ## ' '{gsub(/\\/, "", $$1); printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

image: ## Build the development images
	docker compose build php bun go

test: ## Run the PHP and Go tests, then PHP type coverage
	@$(MAKE) --no-print-directory test:php test:go test:types

test\:php: ## Run the Pest suite, e.g. make test:php ARGS="--filter=Instance"
	$(RUN) ./vendor/bin/pest $(ARGS)

test\:go: ## Run the rift-dash Go tests, e.g. make test:go PKG=./internal/ui ARGS="-run TestScreens -update"
	$(GO) go test $(or $(PKG),./...) $(ARGS)

test\:types: ## Check Pest type coverage (100% required)
	$(RUN) php -d memory_limit=512M vendor/bin/pest --type-coverage --min=100

# TIA is off here: replayed tests count towards the summary, but the HTML report gets no coverage data for them.
test\:coverage: ## Run the PHP and Go tests with coverage (100% required), with HTML reports in .coverage/html and dash/coverage.html
	$(RUN) ./vendor/bin/pest --no-tia --coverage --min=100 --coverage-html .coverage/html
	$(GO) sh scripts/coverage.sh
	@echo "  HTML reports: .coverage/html/index.html, dash/coverage.html"

sniff: ## Run static analysis: Larastan for PHP, golangci-lint's linters for Go
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G
	$(GO) golangci-lint run

lint: ## Check PHP and Go formatting without changing anything
	$(RUN) ./vendor/bin/pint --test
	$(GO) golangci-lint fmt --diff

format: ## Fix PHP and Go formatting
	$(RUN) ./vendor/bin/pint
	$(GO) golangci-lint fmt

shell: ## Open a shell in the development container
	$(RUN) bash

rift: ## Run rift from source in the container, e.g. make rift ARGS="list"
	$(RUN) php rift $(ARGS)

build: ## Build native rift and rift-dash binaries for this machine into builds/
	$(GO) sh /app/docker/build-dash.sh $(TARGET)
	$(RUN) bash docker/build.sh $(TARGET)
	rm -f builds/rift builds/rift-dash
	cp builds/rift-$(TARGET) builds/rift
	cp builds/rift-dash-$(TARGET) builds/rift-dash

demo: ## Record .github/dash.gif, the dashboard demo in README.md, with VHS
	$(GO) env CGO_ENABLED=0 go build -trimpath -o /app/builds/rift-dash-demo ./cmd/rift-dash
	docker run --rm --user "$(UID):$(GID)" -e HOME=/tmp -v "$(CURDIR)":/app -w /app $(VHS) docker/demo/dash.tape

docs\:install: ## Install the docs site's dependencies in docs/
	$(DOCS) bun install --frozen-lockfile

docs\:dev: ## Serve the docs site with live reload at http://localhost:3000
	docker compose run --rm -p 3000:3000 -w /app/docs bun bun run dev

docs\:build: ## Build the static docs site into docs/out, and its Vercel output with headers into docs/.vercel/output
	$(DOCS) bun run build

docs\:preview: ## Serve the built docs site with its production headers at http://localhost:3001
	docker compose run --rm -p 3001:3000 -w /app/docs bun bun run preview

docs\:lint: ## Check the docs site with ESLint and Prettier
	$(DOCS) bun run lint
	$(DOCS) bun run format:check

docs\:format: ## Fix the docs site's formatting with Prettier
	$(DOCS) bun run format

docs\:bun: ## Run Bun in docs/, e.g. make docs:bun ARGS="update next --latest"
	$(DOCS) bun $(ARGS)

checksums: ## Re-pin the PHP runtime checksums after changing PHP_VERSION in docker/build.sh
	$(RUN) bash docker/build.sh --checksums

clean: ## Remove build output and the development image
	rm -rf builds
	docker compose down --rmi local --volumes

endif
