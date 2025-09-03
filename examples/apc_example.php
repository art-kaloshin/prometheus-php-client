<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\RenderTextFormat;

// Проверяем, что APCu расширение установлено
if (!extension_loaded('apcu')) {
    die("APCu расширение не установлено. Установите его командой:\n" .
        "sudo apt-get install php8.0-apcu\n" .
        "или\n" .
        "sudo yum install php-pecl-apcu\n");
}

// Создаем реестр с APCu хранилищем
$adapter = new APC();
$registry = new CollectorRegistry($adapter);

// Пример 1: Счетчик (Counter)
$counter = $registry->registerCounter('app', 'requests_total', 'Общее количество запросов', ['method', 'endpoint']);
$counter->inc(['GET', '/api/users']);
$counter->inc(['POST', '/api/users']);
$counter->incBy(5, ['GET', '/api/products']);

// Пример 2: Измеритель (Gauge)
$gauge = $registry->registerGauge('app', 'active_users', 'Количество активных пользователей', ['region']);
$gauge->set(150, ['europe']);
$gauge->set(200, ['asia']);
$gauge->inc(['europe']); // увеличить на 1
$gauge->dec(['asia']);   // уменьшить на 1
$gauge->incBy(10, ['europe']); // увеличить на 10

// Пример 3: Гистограмма (Histogram)
$histogram = $registry->registerHistogram(
    'app', 
    'request_duration_seconds', 
    'Время выполнения запросов', 
    ['method'], 
    [0.1, 0.5, 1.0, 2.0, 5.0] // корзины (buckets)
);

// Симулируем разные времена выполнения
$histogram->observe(0.05, ['GET']);   // 50ms
$histogram->observe(0.2, ['GET']);    // 200ms
$histogram->observe(0.8, ['POST']);   // 800ms
$histogram->observe(1.5, ['POST']);   // 1.5s
$histogram->observe(3.0, ['DELETE']); // 3s

// Пример 4: Использование getOrRegister методов (рекомендуется)
$cacheHits = $registry->getOrRegisterCounter('cache', 'hits_total', 'Количество попаданий в кеш', ['cache_type']);
$cacheHits->inc(['redis']);
$cacheHits->inc(['apcu']);

// Получаем метрики в формате Prometheus
$renderer = new RenderTextFormat();
$result = $renderer->render($registry->getMetricFamilySamples());

// Выводим метрики
echo "=== Prometheus метрики ===\n";
echo $result;

// Пример 5: Очистка APCu кеша (осторожно!)
echo "\n=== Очистка APCu кеша ===\n";
$adapter->flushAPC();
echo "APCu кеш очищен\n";

// Проверяем, что метрики очищены
$resultAfterFlush = $renderer->render($registry->getMetricFamilySamples());
echo "\n=== Метрики после очистки ===\n";
echo $resultAfterFlush;

// Пример 6: Работа с метриками в разных процессах
echo "\n=== Демонстрация работы в разных процессах ===\n";

// В реальном приложении APCu позволяет делиться метриками между процессами
// Это особенно полезно для веб-серверов с несколькими worker процессами

$sharedCounter = $registry->getOrRegisterCounter('shared', 'process_requests', 'Запросы по процессам', ['pid']);
$sharedCounter->inc([getmypid()]);

echo "Текущий PID: " . getmypid() . "\n";
echo "Метрики процесса:\n";
$processMetrics = $renderer->render($registry->getMetricFamilySamples());
echo $processMetrics; 