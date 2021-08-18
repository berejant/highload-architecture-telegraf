<?php

header('Content-type: text/plain');

$time = microtime(true);

$connection = new mysqli('p:mysql', 'default', 'secret', 'default');
if ($connection->connect_errno) {
    http_response_code(503);
    exit;
}

benchmark_query('SELECT * FROM users_no_index WHERE birthday_day = 801');
benchmark_query('SELECT * FROM users_index WHERE birthday_day = 801');

function benchmark_query($sql) {
    global $connection;

    $time = microtime(true);
    $connection->query($sql);
    $execution_time = microtime(true) - $time;

    echo $sql, PHP_EOL, 'Time: ', $execution_time, PHP_EOL;
}