up:
	docker-compose up -d

down:
	docker-compose down

env-docker:
	cp envs/.env.example envs/.env

env-api:
	cp envs/.env.api.example envs/.env.api

cp-envs: env-docker env-api

init-envs:
	cp envs/.env .env
	cp envs/.env.api api/.env

init-perms:
	sudo chown -R ${USER}:${USER} .

dev-init: cp-envs init-envs init-perms

build:
	docker-compose up -d --build

build-laravel:
	docker-compose down
	sudo chgrp -R www-data api/storage api/bootstrap/cache
	sudo chmod -R 775 api/storage api/bootstrap/cache
	docker-compose up -d
	docker-compose exec php composer install
	docker-compose exec php php artisan key:generate

storage:
	docker-compose exec php php artisan storage:link

key:
	docker-compose exec php php artisan key:generate --ansi
	docker-compose exec php php artisan jwt:secret -n

restart: down up

install: build build-laravel storage key restart

perm:
	sudo chown -R ${USER}:www-data api/storage
	sudo chmod -R 775 api/bootstrap/cache
	sudo chmod -R 775 api/storage
	sudo chmod -R 1777 docker/mysql/dumps

php:
	docker-compose exec php bash

mysql:
	docker-compose exec mysql bash