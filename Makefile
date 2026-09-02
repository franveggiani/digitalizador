.PHONY: install test build up down

install:
	pnpm install
	cd services/api && composer install

test:
	pnpm test
	cd services/api && vendor/bin/phpunit

build:
	pnpm build

up:
	docker compose up --build

down:
	docker compose down
