# test-work-rso — Сервис расчета стоимости страховки

REST API микросервис для расчета стоимости полиса страхования для выезжающих за рубеж. Реализован на актуальной версии **Symfony 8.1** и **PHP 8.4** с соблюдением принципов чистой архитектуры, SOLID и PSR.

---

## Стек технологий

- **PHP 8.4** (`bcmath`, `intl`, `opcache`, `zip`)
- **Symfony 8.1** (минимальный скелет для микросервисов)
- **Компоненты Symfony:** `Serializer`, `Validator`, `HttpClient`, `Cache`, `Console`
- **Docker & Docker Compose:** Nginx + PHP-FPM 8.4 (запуск от непривилегированного пользователя `www-data:1000`)
- **Stateless архитектура:** сервис не требует реляционной БД (YAGNI), мгновенно стартует и масштабируется

---

## Быстрый старт (Docker)

Проект полностью контейнеризирован. Для запуска требуется только Docker и Docker Compose.

### 1. Запуск контейнеров

```bash
docker compose up -d --build
```
*(Либо через Makefile: `make up`)*

Сервис доступен по адресу: `http://localhost:8800` (порт настраивается через переменную `NGINX_PORT` в `.env`).

### 2. Синхронизация и прогрев кэша курсов валют ЦБ РФ

Для получения и кэширования актуальных курсов валют на сегодня (запускать раз в час / раз в несколько часов):

```bash
docker compose exec app php bin/console app:currency:sync
```

Вывод команды:
```text
Синхронизация курсов валют ЦБ РФ на 2026-11-17
==============================================

 -------- -------------- ------------------ 
  Валюта   Курс к рублю   Статус            
 -------- -------------- ------------------ 
  EUR      91.1948 RUB    Успешно (в кэше)  
  USD      82.5143 RUB    Успешно (в кэше)  
 -------- -------------- ------------------ 

 [OK] Курсы валют успешно получены и сохранены в кэш.
```

---

## API Спецификация

### Эндпоинт расчета: `POST /api/v1/insurance/calculate`

Запрос принимает входные параметры в формате JSON:
- `insured_amount` *(int, обязательно)*: страховая сумма. Допустимые значения: `30000`, `50000`.
- `start_date` *(string, обязательно)*: дата начала поездки в формате `YYYY-mm-dd`.
- `end_date` *(string, обязательно)*: дата окончания поездки в формате `YYYY-mm-dd` (не может быть раньше даты начала).
- `currency` *(string, обязательно)*: код валюты из 3 символов (`EUR`, `USD`).

Формат успешного ответа (200 OK):
- `cost_in_currency` *(float)*: общая стоимость в валюте страхования (округление до 2 знаков).
- `cost_in_rub` *(float)*: общая стоимость в рублях (округление до 2 знаков).
- `days_count` *(int)*: количество дней поездки (`diff + 1`).
- `daily_rate` *(float)*: коэффициент 1 дня (0.6 для 30 000, 0.8 для 50 000).
- `currency_rate` *(float)*: курс валюты ЦБ РФ на день расчета (округление до 4 знаков).
- `insured_amount` *(int)*: страховая сумма.

#### Обработка ошибок:
Все ошибки сериализуются через централизованный `ExceptionListener` в единый формат: `{"type": "ERROR", "data": ...}`.
- При ошибках валидации и некорректных данных возвращаются клиентские коды **4xx** (`422 Unprocessable Content`, `400 Bad Request`, `405 Method Not Allowed`). Коды 5xx при ошибках во входных данных исключены.
- При недоступности внешнего сервиса котировок ЦБ РФ возвращается статус **503 Service Unavailable** (строго по ТЗ).

---

## Сценарии проверки всех кейсов через cURL

### 1. Успешный расчет на 3 дня (2025-10-01 → 2025-10-03, 30 000 EUR)
```bash
curl -X POST http://localhost:8800/api/v1/insurance/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "insured_amount": 30000,
    "start_date": "2025-10-01",
    "end_date": "2025-10-03",
    "currency": "EUR"
  }'
```
**Ответ (HTTP 200 OK):**
```json
{"cost_in_currency":1.8,"cost_in_rub":163.46,"days_count":3,"daily_rate":0.6,"currency_rate":90.8136,"insured_amount":30000}
```

### 2. Поездка на 1 день (2025-10-01 → 2025-10-01, 50 000 USD)
```bash
curl -X POST http://localhost:8800/api/v1/insurance/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "insured_amount": 50000,
    "start_date": "2025-10-01",
    "end_date": "2025-10-01",
    "currency": "USD"
  }'
```
**Ответ (HTTP 200 OK):**
```json
{"cost_in_currency":0.8,"cost_in_rub":65.96,"days_count":1,"daily_rate":0.8,"currency_rate":82.4529,"insured_amount":50000}
```

### 3. Недопустимая страховая сумма (например, 10 000 вместо 30 000 / 50 000)
```bash
curl -i -X POST http://localhost:8800/api/v1/insurance/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "insured_amount": 10000,
    "start_date": "2025-10-01",
    "end_date": "2025-10-03",
    "currency": "EUR"
  }'
```
**Ответ (HTTP 422 Unprocessable Content):**
```json
{"type":"ERROR","data":[{"type":"validation","property":"insured_amount","message":"Недопустимая страховая сумма. Доступны: 30000, 50000."}]}
```

### 4. Невалидный диапазон дат (дата начала позже даты окончания)
```bash
curl -i -X POST http://localhost:8800/api/v1/insurance/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "insured_amount": 30000,
    "start_date": "2025-10-05",
    "end_date": "2025-10-01",
    "currency": "EUR"
  }'
```
**Ответ (HTTP 422 Unprocessable Content):**
```json
{"type":"ERROR","data":[{"type":"validation","property":"end_date","message":"Дата начала поездки не может быть позже даты окончания."}]}
```

### 5. Неподдерживаемая валюта (например, JPY)
```bash
curl -i -X POST http://localhost:8800/api/v1/insurance/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "insured_amount": 30000,
    "start_date": "2025-10-01",
    "end_date": "2025-10-03",
    "currency": "JPY"
  }'
```
**Ответ (HTTP 422 Unprocessable Content):**
```json
{"type":"ERROR","data":[{"type":"validation","property":"currency","message":"Недопустимая валюта. Доступны: \"EUR\", \"USD\"."}]}
```

### 6. Некорректный синтаксис JSON (битый payload)
```bash
curl -i -X POST http://localhost:8800/api/v1/insurance/calculate \
  -H "Content-Type: application/json" \
  -d 'invalid-json'
```
**Ответ (HTTP 400 Bad Request):**
```json
{"type":"ERROR","data":{"type":"exception","message":"Request payload contains invalid \"json\" data."}}
```

### 7. Неверный HTTP-метод (GET вместо POST)
```bash
curl -i -X GET http://localhost:8800/api/v1/insurance/calculate
```
**Ответ (HTTP 405 Method Not Allowed):**
```json
{"type":"ERROR","data":{"type":"exception","message":"No route found for \"GET http://localhost:8800/api/v1/insurance/calculate\": Method Not Allowed (Allow: POST)"}}
```

---

## Архитектурные особенности и реализация SOLID

1. **Тонкие контроллеры и именование DTO (`Input` / `Output`):**
   - Входные данные связываются декларативно через атрибут `#[MapRequestPayload]` в DTO `CalculateInsuranceInput` (`src/Dto/Input/`), ответ сериализуется через `CalculateInsuranceOutput` (`src/Dto/Output/`).
   - **Почему суффиксы `Input`/`Output`:**
     - Исключают коллизии импортов `use` в контроллере без необходимости громоздких псевдонимов (`as ...`);
     - Предотвращают путаницу с HTTP-классами ядра Symfony (`HttpFoundation\Request` и `Response`);
     - Четко отделяют структуру данных (DTO) от команд/сервисов (глагол `CalculateInsurance`).
   - Автоматический маппинг `snake_case` JSON в `camelCase` свойства PHP сконфигурирован через `CamelCaseToSnakeCaseNameConverter`.
   - Бизнес-инварианты периода инкапсулированы в Value Object `TripPeriod` (`$start->diff($end)->days + 1`).

2. **Управление страховыми суммами и коэффициентами (Open/Closed Principle):**
   - Согласно ТЗ, список страховых сумм (сейчас 30 000 и 50 000) может меняться.
   - Список сумм и коэффициенты вынесены в **единую точку конфигурации** — параметр `app.insurance.daily_coefficients` в `config/services.yaml`:
     ```yaml
     parameters:
         app.insurance.daily_coefficients:
             30000: 0.6
             50000: 0.8
             100000: 1.2  # Пример добавления новой суммы
     ```
   - Кастомный валидатор `#[SupportedInsuredAmount]` внедряет `DailyCoefficientResolverInterface` и динамически валидирует входное значение по актуальному списку из конфигурации.
   - При добавлении или изменении сумм в `services.yaml`:
     - Резолвер коэффициентов автоматически применяет новые ставки;
     - Валидатор автоматически обновляет список разрешенных сумм и сообщение об ошибке (`Недопустимая страховая сумма. Доступны: 30000, 50000...`);
     - **Исходный PHP-код менять не требуется вообще.**

3. **Расширяемость формулы расчета:**
   - Расчет выполняется в `InsuranceCalculator`. Базовая формула: `(Коэффициент 1 дня) * (кол-во дней)`.
   - Поддерживается расширение формулы без модификации калькулятора через интерфейс `PriceModifierInterface` и тегированный итератор Symfony `#[AutowireIterator('app.insurance.price_modifier')]` (дополнительные коэффициенты за возраст, спорт, франшизу регистрируются простым созданием нового класса).

4. **Интеграция с ЦБ РФ и паттерн Декоратор (GoF):**
   - `CbrXmlCurrencyRateProvider`: клиент к `https://www.cbr.ru/scripts/XML_daily.asp` с парсингом кодировки `Windows-1251`, учетом номинала и разделителя-запятой.
   - `CachedCurrencyRateProvider`: декоратор (`#[AsDecorator]`) с кэшированием на 1 час (3600 сек) и встроенным **Circuit Breaker** (при недоступности ЦБ включается 30-секундный кулдаун для защиты от зависаний).
   - В scoped HTTP-клиенте настроен жесткий таймаут `3.0` сек и стратегия автоматического `retry` с экспоненциальной задержкой.
   - При сбое ЦБ сервис возвращает `5xx` статус строго по ТЗ.

5. **Финансовая точность (bcmath):**
   - Для исключения ошибок представления чисел с плавающей точкой промежуточные финансовые операции выполняются через расширение `bcmath`.
   - В `php.ini` явно зафиксирована директива `serialize_precision = -1` для гарантии чистого вывода JSON float.
   - Округление итоговых значений производится функцией `round()` (до 2 знаков для денег, до 4 знаков для валютного курса, строго по ТЗ).

6. **Безопасность контейнеров:**
   - PHP-FPM воркеры запускаются от непривилегированного пользователя `www-data` (UID 1000), файлы монтируются с сохранением прав хост-системы.

---

## Запуск тестов

Тестовый набор включает 36 тестов: модульные тесты предметной области, декоратора кэширования с Circuit Breaker, консольной команды, валидаторов и провайдера валют, а также функциональные тесты API контроллера с проверкой всех граничных условий и статус-кодов (200, 422, 400, 405, 503):

```bash
make test
# или напрямую:
docker compose exec app bin/phpunit
```

---

## Контроль качества кода (Code Quality & Style)

Для соблюдения стандартов кодирования, критериев PSR и чистоты архитектуры в проект интегрированы анализаторы кода:

1. **PHPStan (статический анализ):**
   - Настроен на строгий уровень **Level 8** в [phpstan.neon](phpstan.neon).
   - Проверяет строгость типов, возвращаемые значения и инварианты предметной области в `src/` и `tests/`.
   ```bash
   make phpstan
   ```

2. **PHP-CS-Fixer (стиль кодирования и стандарт PSR):**
   - Конфигурация в [.php-cs-fixer.dist.php](.php-cs-fixer.dist.php) объединяет правила `@Symfony`, официальный стандарт **`@PER-CS2x0`** (преемник PSR-12 от PHP-FIG), обязательный `declare(strict_types=1)` и многострочные завершающие запятые (PHP 8).
   - Проверка стиля без изменения файлов (dry-run):
     ```bash
     make cs-check
     ```
   - Автоматическое приведение кода к единому стилю:
     ```bash
     make cs-fix
     ```

---

## Полезные команды (Makefile)

```bash
make up         # Запустить контейнеры
make down       # Остановить контейнеры
make restart    # Перезапустить контейнеры
make test       # Запустить все тесты (PHPUnit)
make phpstan    # Статический анализ кода (PHPStan)
make cs-check   # Проверка кодовой базы на стиль кодирования (PHP-CS-Fixer dry-run)
make cs-fix     # Автоматическое форматирование кода (PHP-CS-Fixer)
make log        # Просмотр логов приложения
make console-in # Зайти в консоль bash контейнера app
```
