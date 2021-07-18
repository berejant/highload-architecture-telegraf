<?php

header('Content-Type: text/plain');
$connection = new mysqli('p:mysql', 'default', 'secret', 'default');
if ($connection->connect_errno) {
    http_response_code(503);
    exit;
}

$name = filter_input(INPUT_GET, 'name');
if (!$name) {
    http_response_code(400);
    exit;
}

$age = rand(18, 60);
$sex = rand(0, 1);

$stmt = $connection->prepare('INSERT INTO example (name,sex,age) VALUES(?, ?, ?)');
$stmt->bind_param('sii', $name, $sex, $age);

$result = $stmt->execute();
if (!$result) {
    http_response_code(503);
    exit;
}

$user_id = $stmt->insert_id;

$result = $connection->query('SELECT * FROM example WHERE id = ' . $user_id . ' LIMIT 1');
if (!$result) {
    http_response_code(503);
    exit;
}


echo implode(' - ', $result->fetch_assoc()) . PHP_EOL;

$result->free();
$connection->close();
