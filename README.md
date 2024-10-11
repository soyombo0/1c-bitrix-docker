## Шаги установки

1. Делаем бэкап 1с-битрикс с архивацией ДБ
2. git clone https://github.com/soyombo0/1c-bitrix-docker.git
3. `cd 1c-bitrix-docker`
4. `make copyinitdata`
5. `make dc-build`
6. `make dc-up`
7. `docker compose ps` - проверить все ли сервисы запущены
8. `http://localhost/restore.php`
9. Копируем ссылку бэкапа и разархивируем бэкап через ссылку
10. Заполняем доступы от датабазы после разархивации проекта из бекапа.
11. Можно работать с проектом. `http://localhost`

## Дефолтные доступы от дб :

```bash
'host' => 'module-mysql',
'database' => 'bitrixdb',
'login' => 'root',
'password' => 'wGAb~rPQnK',
```

### Также лучше  включить debug в .settings.php ( только для локальной разработки )

```bash
'debug' => true,
```

## Структура:

```bash
-- docker
    -- bash_history # папка для хранения истории bash контейнеров
    -- conf # конфиги. ngnix и пр.
    -- dumps # папка для дампов БД
    -- images # папка с docker образами
    -- initdata # папка со служебными файлами
-- www # root директория проекта
-- .env.example # пример файла `.env`
-- .gitignore # список игнора
-- Makefile # команды make. Список команд make можно посмотреть так: `make` или `make help`
-- docker-compose.yml # конфиг контейнеров
-- README.md # этот файл
```
