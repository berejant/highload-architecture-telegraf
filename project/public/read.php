<?php

define('CACHE_TTL', 30);
define('LEFT_TTL_TO_FLUSH', 5);

header('Content-Type: text/plain');

$use_probabilistic_cache_flushing = filter_input(INPUT_SERVER, 'HTTP_X_PROBABILISTIC_CACHE_FLUSHING', FILTER_VALIDATE_BOOLEAN);
$id = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit;
}

$memcached = new Memcached;
$result = $memcached->addServer('memcached', 11211);
if (!$result) {
    http_response_code(503);
    exit;
}

$cache_key = 'user:' . $id;
list($record, $expire_ts) = $memcached->get($cache_key);

if ($use_probabilistic_cache_flushing && $record && ($expire_ts - time() <= LEFT_TTL_TO_FLUSH) && rand(0, 1000) <= 2) {
    $record = null;
}

if (!$record) {
    $connection = new mysqli('p:mysql', 'default', 'secret', 'default');
    if ($connection->connect_errno) {
        http_response_code(503);
        exit;
    }
    $result = $connection->query('SELECT SQL_NO_CACHE * FROM example WHERE id = ' . $id . ' LIMIT 1');
    $record = $result->fetch_assoc();
    $result->fetch_assoc();
    $result->close();
    $connection->close();

    $memcached->set($cache_key, [$record, time() + CACHE_TTL], CACHE_TTL);
}

echo implode(' - ', $record) . PHP_EOL;
