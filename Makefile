.PHONY: help install update db-update db-migrate db-create db-drop cache-clear local docker-build docker-up docker-down docker-logs docker-exec docker-db-setup docker-composer-install docker-create-classes docker-telegram-setup docker-ngrok docker-telegram-poll docker-healing-daemon docker-restart-all

help:
	@echo "Доступные команды:"
	@echo "make install         - Установка зависимостей"
	@echo "make update         - Обновление зависимостей"
	@echo "make db-update      - Обновить схему базы данных"
	@echo "make db-migrate     - Выполнить миграции"
	@echo "make db-create      - Создать базу данных"
	@echo "make db-drop        - Удалить базу данных"
	@echo "make cache-clear    - Очистить кэш"
	@echo ""
	@echo "Docker команды:"
	@echo "make local          - Полная настройка проекта с Docker (сборка, запуск, настройка БД)"
	@echo "make docker-build   - Собрать Docker контейнеры"
	@echo "make docker-up      - Запустить Docker контейнеры"
	@echo "make docker-down    - Остановить Docker контейнеры"
	@echo "make docker-logs    - Показать логи контейнеров"
	@echo "make docker-exec    - Войти в PHP контейнер"
	@echo "make docker-db-setup - Настроить базу данных в Docker"
	@echo "make docker-composer-install - Установить зависимости Composer в Docker"
	@echo "make docker-create-classes - Создать базовые классы персонажей"
	@echo "make docker-telegram-setup - Настроить Telegram webhook"
	@echo "make docker-ngrok - Запустить ngrok туннель для Telegram webhook"
	@echo "make docker-telegram-poll - Запустить Telegram бота в режиме polling (для локальной разработки)"
	@echo "make docker-healing-daemon - Запустить фоновый процесс лечения персонажей"
	@echo "make docker-restart-all - Перезапустить все сервисы и настроить webhook"

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

# Docker команды
local: docker-build docker-up docker-composer-install docker-db-setup docker-create-classes docker-setup-telegram-webhook

docker-build:
	docker-compose build

docker-up:
	docker-compose up -d

docker-down:
	docker-compose down

docker-logs:
	docker-compose logs -f

docker-exec:
	docker-compose exec php sh

docker-db-setup:
	docker-compose exec php bin/console doctrine:schema:create --no-interaction || true
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

docker-composer-install:
	docker-compose exec php composer install

docker-create-classes:
	@echo "Проверка наличия классов персонажей..."
	@if [ $$(docker-compose exec php bin/console doctrine:query:sql "SELECT COUNT(*) FROM classes" | grep -o '[0-9]\+' | tail -1) -eq 0 ]; then \
		echo "Создание базовых классов персонажей..."; \
		docker-compose exec php bin/console doctrine:query:sql "INSERT INTO classes (name, base_stats) VALUES ('Маг', '{\"hp\":30, \"attack\":5, \"defence\":3, \"strength\":2, \"dexterity\":4, \"speed\":3, \"vitality\":3}')" || true; \
		docker-compose exec php bin/console doctrine:query:sql "INSERT INTO classes (name, base_stats) VALUES ('Рыцарь', '{\"hp\":40, \"attack\":6, \"defence\":8, \"strength\":7, \"dexterity\":2, \"speed\":2, \"vitality\":5}')" || true; \
		docker-compose exec php bin/console doctrine:query:sql "INSERT INTO classes (name, base_stats) VALUES ('Лучник', '{\"hp\":35, \"attack\":7, \"defence\":4, \"strength\":4, \"dexterity\":8, \"speed\":6, \"vitality\":3}')" || true; \
		docker-compose exec php bin/console doctrine:query:sql "INSERT INTO classes (name, base_stats) VALUES ('Жрец', '{\"hp\":35, \"attack\":4, \"defence\":5, \"strength\":3, \"dexterity\":3, \"speed\":3, \"vitality\":7}')" || true; \
		docker-compose exec php bin/console doctrine:query:sql "INSERT INTO classes (name, base_stats) VALUES ('Разбойник', '{\"hp\":30, \"attack\":6, \"defence\":3, \"strength\":5, \"dexterity\":7, \"speed\":8, \"vitality\":2}')" || true; \
		echo "Классы персонажей успешно созданы."; \
	else \
		echo "Классы персонажей уже существуют."; \
	fi

docker-telegram-setup:
	@echo "Для настройки webhook Telegram бота, сначала запустите ngrok:"
	@echo "make docker-ngrok"
	@echo "Затем в другом терминале выполните:"
	@echo "curl -H \"ngrok-skip-browser-warning: true\" \"https://YOUR-NGROK-URL/set-webhook?url=https://YOUR-NGROK-URL/webhook\""
	@echo "Замените YOUR-NGROK-URL на URL, полученный от ngrok"

docker-ngrok:
	@if command -v ngrok >/dev/null 2>&1; then \
		echo "Запуск ngrok туннеля на порт 8080..."; \
		ngrok http 8080; \
	else \
		echo "Ngrok не установлен. Установите его с помощью команды:"; \
		echo "brew install ngrok/ngrok/ngrok"; \
		echo "Затем настройте токен:"; \
		echo "ngrok config add-authtoken YOUR_AUTHTOKEN"; \
	fi

# Запуск Telegram бота в режиме polling
docker-telegram-poll:
	@echo "Запуск Telegram бота в режиме polling..."
	docker-compose exec php bin/console app:telegram:poll --timeout=60 -vv

# Запуск фонового процесса лечения
docker-healing-daemon:
	@echo "Запуск фонового процесса лечения персонажей..."
	docker-compose exec php bin/console app:healing:process --daemon --interval=10 -vv

# Автоматическая настройка Telegram webhook
docker-setup-telegram-webhook:
	@echo "Запуск ngrok в фоновом режиме..."
	@if command -v ngrok >/dev/null 2>&1; then \
		(ngrok http 8080 --log=stdout > /tmp/ngrok.log 2>&1 & echo $$! > /tmp/ngrok.pid); \
		echo "Ожидание запуска ngrok..."; \
		sleep 5; \
		NGROK_URL=$$(curl -s http://localhost:4040/api/tunnels | grep -o '"public_url":"https://[^"]*' | sed 's/"public_url":"//'); \
		if [ -n "$$NGROK_URL" ]; then \
			echo "Получен URL ngrok: $$NGROK_URL"; \
			echo "Настройка webhook для Telegram..."; \
			curl -s -H "ngrok-skip-browser-warning: true" "$$NGROK_URL/set-webhook?url=$$NGROK_URL/webhook"; \
			echo ""; \
			echo "Теперь вы можете тестировать бота в Telegram. Отправьте /start для начала."; \
			echo "Не закрывайте терминал, чтобы ngrok продолжал работать."; \
		else \
			echo "Не удалось получить URL ngrok. Проверьте настройки ngrok."; \
			kill $$(cat /tmp/ngrok.pid); \
		fi; \
	else \
		echo "Ngrok не установлен. Установите его с помощью команды:"; \
		echo "brew install ngrok/ngrok/ngrok"; \
		echo "Затем настройте токен:"; \
		echo "ngrok config add-authtoken YOUR_AUTHTOKEN"; \
	fi

# Полный перезапуск и настройка всех сервисов
docker-restart-all:
	@echo "Перезапуск контейнеров..."
	docker-compose down
	docker-compose up -d
	@echo "Контейнеры перезапущены. Настраиваем webhook..."
	@$(MAKE) docker-setup-telegram-webhook
	@echo "Webhook настроен. Настройка завершена! Бот готов к работе."
