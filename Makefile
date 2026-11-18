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
	docker compose exec app bin/phpunit

phpstan:
	docker compose exec app composer phpstan

cs-fix:
	docker compose exec app composer fix-cs

cs-check:
	docker compose exec app composer check-cs

fix-cs: cs-fix

check-cs: cs-check

lint:
	docker compose exec app composer lint

