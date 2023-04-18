
### Описание проекта:
Приложение для получения курса валюты на определенную дату.

### Инструкция по разворачиванию:
1. Скопировать ./docker/.env.dist в ./docker/.env. Если нужно, изменить значения переменных.
```bash
cp ./docker/.env.dist ./docker/.env
```
2. В консоли выполнить команды:

```bash
cd ./docker
docker-compose up --build -d
docker-compose exec app composer install
```

### Формат команды:
```bash
php bin/console app:fetch-exchange-rates [дата] [валюта] [базовая валюта]
```

### Пример запуска:
```bash
docker-compose exec app php bin/console app:fetch-exchange-rates 16.05.2021 EUR USD
```

### Запуск тестов:
```bash
docker-compose exec app vendor/bin/phpunit -c tests/phpunit.xml
```
