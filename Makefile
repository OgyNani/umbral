.PHONY: help install update db-update db-migrate db-create db-drop cache-clear

help:
	@echo "Доступные команды:"
	@echo "make install         - Установка зависимостей"
	@echo "make update         - Обновление зависимостей"
	@echo "make db-update      - Обновить схему базы данных"
	@echo "make db-migrate     - Выполнить миграции"
	@echo "make db-create      - Создать базу данных"
	@echo "make db-drop        - Удалить базу данных"
	@echo "make cache-clear    - Очистить кэш"

install:
	composer install

update:
	composer update

db-update:
	php bin/console doctrine:schema:update --force

db-migrate:
	php bin/console doctrine:migrations:migrate --no-interaction

db-create:
	php bin/console doctrine:database:create

db-drop:
	php bin/console doctrine:database:drop --force

cache-clear:
	php bin/console cache:clear
