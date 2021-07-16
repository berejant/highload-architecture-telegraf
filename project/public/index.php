<?php

header('Content-Type: text/plain');
$connection = new mysqli('p:mysql', 'default', 'secret', 'default');
if ($connection->connect_errno) {
    http_response_code(503);
    exit;
}

$result = $connection->query('SELECT * FROM example');
if (!$result) {
    http_response_code(503);
    exit;
}

foreach ($result as $row) {
    echo implode(' - ', $row) . PHP_EOL;
}

$result->free();
$connection->close();
