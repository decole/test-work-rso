up:
	docker compose up -d --remove-orphans

down:
	docker compose down

restart: down up

build:
	docker compose build --no-cache

console-in:
	docker compose exec app bash

ps:
	docker compose ps -a

log:
	docker compose logs -f app

# Quality Assurance & Testing
test:
	docker compose exec app composer test

app-audit:
	docker compose exec app composer audit --abandoned=ignore || true

backend-audit: app-audit

phpstan:
	docker compose exec app composer phpstan

psalm:
	docker compose exec app composer psalm

deptrac:
	docker compose exec app composer deptrac

fix-cs:
	docker compose exec app composer fix-cs

check-cs:
	docker compose exec app composer check-cs

lint:
	docker compose exec app composer lint

