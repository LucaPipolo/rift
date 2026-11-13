# Every target runs inside the rift-dev container, so the host needs Docker
# and nothing else. Run `make` to list them.

export UID := $(shell id -u)
export GID := $(shell id -g)

RUN := docker compose run --rm php
BUN := docker compose run --rm bun
GO := docker compose run --rm go

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
.PHONY: help image test test\:php test\:go test\:types test\:coverage sniff lint format shell rift build checksums clean

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
	$(RUN) bash docker/build.sh $(TARGET)
	$(GO) sh /app/docker/build-dash.sh $(TARGET)
	cp builds/rift-$(TARGET) builds/rift
	cp builds/rift-dash-$(TARGET) builds/rift-dash

checksums: ## Re-pin the PHP runtime checksums after changing PHP_VERSION in docker/build.sh
	$(RUN) bash docker/build.sh --checksums

clean: ## Remove build output and the development image
	rm -rf builds
	docker compose down --rmi local --volumes

endif
