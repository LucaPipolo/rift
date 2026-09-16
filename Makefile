# Every target runs inside the rift-dev container, so the host needs Docker
# and nothing else. Run `make` to list them.

export UID := $(shell id -u)
export GID := $(shell id -g)

RUN := docker compose run --rm php
BUN := docker compose run --rm bun

TARGET := $(if $(filter Darwin,$(shell uname -s)),darwin,linux)-$(if $(filter arm64 aarch64,$(shell uname -m)),arm64,x86_64)

.DEFAULT_GOAL := help

# Treat goals after composer or bun as its arguments, including names of other targets.
ifneq ($(filter $(firstword $(MAKECMDGOALS)),composer bun),)
TOOL := $(firstword $(MAKECMDGOALS))
TOOL_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
QUOTED_ARGS := $(foreach arg,$(TOOL_ARGS),'$(subst ','"'"',$(arg))')
.PHONY: $(TOOL) $(TOOL_ARGS)

composer: ## Run Composer, e.g. make composer -- update vendor/package -W
	$(RUN) composer $(QUOTED_ARGS)

bun: ## Run Bun, e.g. make bun install
	$(BUN) bun $(QUOTED_ARGS)

ifneq ($(strip $(TOOL_ARGS)),)
$(TOOL_ARGS): $(TOOL)
	@:
endif

else
.PHONY: help image test test\:unit test\:types test\:coverage sniff lint format shell rift build checksums clean

help: ## List the available targets
	@grep -E '^[a-z\\:-]+:.*## ' $(MAKEFILE_LIST) | awk -F ': ## ' '{gsub(/\\/, "", $$1); printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

image: ## Build the development images
	docker compose build php bun

test: ## Run the Pest suite and type coverage
	@$(MAKE) --no-print-directory test:unit test:types

test\:unit: ## Run the Pest suite, e.g. make test:unit ARGS="--filter=Instance"
	$(RUN) ./vendor/bin/pest $(ARGS)

# TIA is off here: replayed tests count towards the summary, but the HTML report gets no coverage data for them.
test\:coverage: ## Run the Pest suite with code coverage (100% required), with an HTML report in .coverage/html
	$(RUN) ./vendor/bin/pest --no-tia --coverage --min=100 --coverage-html .coverage/html $(ARGS)
	@echo "  HTML report: .coverage/html/index.html"

test\:types: ## Check Pest type coverage (100% required)
	$(RUN) php -d memory_limit=512M vendor/bin/pest --type-coverage --min=100

sniff: ## Run Larastan static analysis
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G $(ARGS)

lint: ## Check formatting without changing anything
	$(RUN) ./vendor/bin/pint --test

format: ## Fix formatting
	$(RUN) ./vendor/bin/pint

shell: ## Open a shell in the development container
	$(RUN) bash

rift: ## Run rift from source in the container, e.g. make rift ARGS="list"
	$(RUN) php rift $(ARGS)

build: ## Build a native rift binary for this machine into builds/rift
	$(RUN) bash docker/build.sh $(TARGET)
	cp builds/rift-$(TARGET) builds/rift

checksums: ## Re-pin the PHP runtime checksums after changing PHP_VERSION in docker/build.sh
	$(RUN) bash docker/build.sh --checksums

clean: ## Remove build output and the development image
	rm -rf builds
	docker compose down --rmi local --volumes

endif
