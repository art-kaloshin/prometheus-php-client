# Использование APCu хранилища в Prometheus PHP Client

## Что такое APCu?

APCu (Alternative PHP Cache User) - это расширение PHP, которое предоставляет пользовательский кеш в памяти. В отличие от Redis, APCu не требует отдельного сервера и работает в рамках одного PHP процесса или нескольких процессов на одном сервере.

## Преимущества APCu

✅ **Простота установки** - не требует дополнительных серверов  
✅ **Высокая производительность** - данные хранятся в памяти  
✅ **Автоматическое управление памятью** - APCu сам очищает старые данные  
✅ **Атомарные операции** - безопасно для многопоточности  
✅ **Низкие накладные расходы** - минимальное потребление ресурсов

## Установка APCu

### Ubuntu/Debian

```bash
sudo apt-get update
sudo apt-get install php8.0-apcu
sudo systemctl restart php8.0-fpm  # или ваш веб-сервер
```

### CentOS/RHEL

```bash
sudo yum install php-pecl-apcu
sudo systemctl restart php-fpm  # или ваш веб-сервер
```

### Проверка установки

```php
<?php
if (extension_loaded('apcu')) {
    echo "APCu установлено и работает!\n";
} else {
    echo "APCu не установлено\n";
}
```

## Базовое использование

### 1. Создание реестра с APCu

```php
<?php
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;

// Создаем APCu адаптер
$adapter = new APC();

// Создаем реестр с APCu хранилищем
$registry = new CollectorRegistry($adapter);
```

### 2. Работа со счетчиками (Counters)

```php
// Регистрируем счетчик
$counter = $registry->registerCounter(
    'app',                    // namespace
    'requests_total',         // name
    'Общее количество запросов', // help
    ['method', 'endpoint']    // labels
);

// Увеличиваем счетчик
$counter->inc(['GET', '/api/users']);
$counter->incBy(5, ['POST', '/api/users']);

// Или используем getOrRegister (рекомендуется)
$counter = $registry->getOrRegisterCounter('app', 'requests_total', 'Общее количество запросов', ['method', 'endpoint']);
```

### 3. Работа с измерителями (Gauges)

```php
// Регистрируем измеритель
$gauge = $registry->registerGauge(
    'app',
    'active_users',
    'Количество активных пользователей',
    ['region']
);

// Устанавливаем значение
$gauge->set(150, ['europe']);

// Увеличиваем/уменьшаем
$gauge->inc(['europe']);     // +1
$gauge->dec(['asia']);       // -1
$gauge->incBy(10, ['europe']); // +10
$gauge->decBy(5, ['asia']);    // -5
```

### 4. Работа с гистограммами (Histograms)

```php
// Регистрируем гистограмму
$histogram = $registry->registerHistogram(
    'app',
    'request_duration_seconds',
    'Время выполнения запросов',
    ['method'],
    [0.1, 0.5, 1.0, 2.0, 5.0] // buckets (корзины)
);

// Наблюдаем значения
$histogram->observe(0.05, ['GET']);   // 50ms
$histogram->observe(0.8, ['POST']);   // 800ms
$histogram->observe(3.0, ['DELETE']); // 3s
```

## Экспорт метрик

```php
use Prometheus\RenderTextFormat;

// Создаем рендерер
$renderer = new RenderTextFormat();

// Получаем метрики в формате Prometheus
$result = $renderer->render($registry->getMetricFamilySamples());

// Выводим для Prometheus
header('Content-Type: ' . RenderTextFormat::MIME_TYPE);
echo $result;
```

## Управление APCu кешем

### Очистка кеша

```php
// Очищаем все метрики
$adapter->flushAPC();

// Или очищаем весь APCu кеш (осторожно!)
apcu_clear_cache();
```

### Мониторинг APCu

```php
// Получаем информацию о APCu
$info = apcu_cache_info();
echo "Количество записей: " . $info['num_entries'] . "\n";
echo "Размер кеша: " . $info['mem_size'] . " байт\n";

// Получаем статистику
$stats = apcu_sma_info();
echo "Доступная память: " . $stats['avail_mem'] . " байт\n";
```

## Конфигурация APCu

### php.ini настройки

```ini
; Включаем APCu
extension=apcu

; Размер кеша (в байтах)
apc.shm_size=128M

; Время жизни записей (в секундах)
apc.ttl=7200

; Включаем статистику
apc.stat=1

; Включаем CLI поддержку
apc.enable_cli=1
```

### Проверка конфигурации

```php
<?php
echo "APCu включено: " . (ini_get('apc.enabled') ? 'Да' : 'Нет') . "\n";
echo "Размер кеша: " . ini_get('apc.shm_size') . "\n";
echo "TTL: " . ini_get('apc.ttl') . "\n";
echo "CLI поддержка: " . (ini_get('apc.enable_cli') ? 'Да' : 'Нет') . "\n";
```

## Практические примеры

### Веб-приложение

```php
<?php
// middleware.php или index.php
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;

$adapter = new APC();
$registry = new CollectorRegistry($adapter);

// Счетчик запросов
$requestCounter = $registry->getOrRegisterCounter('http', 'requests_total', 'HTTP запросы', ['method', 'status']);

// Измеритель активных соединений
$activeConnections = $registry->getOrRegisterGauge('http', 'active_connections', 'Активные соединения');

// Гистограмма времени ответа
$responseTime = $registry->getOrRegisterHistogram('http', 'response_time_seconds', 'Время ответа', ['endpoint'], [0.1, 0.5, 1.0, 2.0]);

// В начале запроса
$startTime = microtime(true);
$activeConnections->inc();

// В конце запроса
$duration = microtime(true) - $startTime;
$requestCounter->inc([$_SERVER['REQUEST_METHOD'], http_response_code()]);
$responseTime->observe($duration, [$_SERVER['REQUEST_URI']]);
$activeConnections->dec();
```

### CLI скрипт

```php
<?php
// worker.php
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;

$adapter = new APC();
$registry = new CollectorRegistry($adapter);

$processedJobs = $registry->getOrRegisterCounter('worker', 'jobs_processed', 'Обработанные задачи', ['status']);
$queueSize = $registry->getOrRegisterGauge('worker', 'queue_size', 'Размер очереди');

while ($job = getNextJob()) {
    try {
        processJob($job);
        $processedJobs->inc(['success']);
    } catch (Exception $e) {
        $processedJobs->inc(['error']);
    }

    $queueSize->set(getQueueSize());
}
```

## Ограничения APCu

⚠️ **Ограниченная память** - данные хранятся в памяти процесса  
⚠️ **Нет персистентности** - данные теряются при перезапуске  
⚠️ **Ограниченное масштабирование** - только один сервер  
⚠️ **Нет сетевого доступа** - только локальный доступ

## Когда использовать APCu

### ✅ Подходит для:

- Небольших приложений
- Одиночных серверов
- Высоконагруженных API
- Временных метрик
- Прототипирования

### ❌ Не подходит для:

- Распределенных систем
- Кластеров серверов
- Долгосрочного хранения
- Критически важных метрик

## Сравнение с Redis

| Характеристика         | APCu          | Redis          |
| ---------------------- | ------------- | -------------- |
| Установка              | Простая       | Требует сервер |
| Производительность     | Очень высокая | Высокая        |
| Сетевое взаимодействие | Нет           | Да             |
| Масштабируемость       | Ограниченная  | Высокая        |
| Персистентность        | Нет           | Да             |
| Потребление ресурсов   | Минимальное   | Среднее        |

## Отладка

### Проверка работы APCu

```php
<?php
// Проверяем, что APCu работает
if (!apcu_store('test', 'value')) {
    die("APCu не работает\n");
}

$value = apcu_fetch('test');
echo "APCu работает: $value\n";

// Очищаем тест
apcu_delete('test');
```

### Логирование ошибок

```php
<?php
// Включаем логирование APCu
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/apcu_errors.log');

// Проверяем ошибки
if (!apcu_store('key', 'value')) {
    error_log("APCu store failed");
}
```
