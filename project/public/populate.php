#!/usr/bin/env php
<?php

define('USE_QUERY_LOG', false);

ini_set('memory_limit', '2G');
header('Content-type: text/plain');

if (USE_QUERY_LOG) {
    $query_log = fopen(__DIR__ . '/populate.sql', 'w+');
} else {
    $connection = new mysqli('p:mysql', 'default', 'secret', 'default');
    if ($connection->connect_errno) {
        http_response_code(503);
        exit;
    }
}

$table_schema = <<<SQL
(
    `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(30) NOT NULL,
    `birthday_date` DATE NOT NULL,
    `birthday_day` SMALLINT UNSIGNED NOT NULL
) ENGINE=InnoDB
SQL;

do_query('DROP TABLE IF EXISTS users_no_index');
do_query('DROP TABLE IF EXISTS users_index');
do_query('CREATE TABLE users_no_index ' . $table_schema);
do_query('CREATE TABLE users_index ' . $table_schema);

$total_count_left = 40E6;

$insert_count = 0;
$insert_buffer = '';

$first_names_stream = fopen(__DIR__ . '/names.txt', 'r');
$second_names_stream = fopen(__DIR__ . '/names.txt', 'r');
if (!$first_names_stream) {
    http_response_code(500);
    echo 'Failed to open names file';
    exit;
}

do_query('START TRANSACTION');
$id = 0;
$birthdate = 0;
$commit_interval = 0;
while (!feof($first_names_stream) && $total_count_left > 0) {
    $name_prefix = rtrim(fgets($first_names_stream));
    rewind($second_names_stream);
    while (!feof($second_names_stream) && $total_count_left > 0) {
        $name = $name_prefix . ' ' . rtrim(fgets($second_names_stream));
        $birthdate += 86401;
        if ($birthdate > time()) {
            $birthdate = 0;
        }

        $id++;
        $insert_count++;
        $insert_buffer .= '('
            . $id . ', "'
            . $name .  '", "'
            . date('Y-m-d', $birthdate) . '", '
            . date('nd', $birthdate)
        . '), ';
    }
    $insert_buffer = substr($insert_buffer, 0, -2);
    do_query('INSERT INTO users_no_index (`id`, `name`, `birthday_date`, `birthday_day`) VALUES ' . $insert_buffer);
    $total_count_left -= $insert_count;
    echo 'Left count: ', $total_count_left, PHP_EOL;
    $insert_buffer = '';
    $insert_count = 0;

    $commit_interval++;
    if ($commit_interval >= 50) {
        echo 'Committing...', PHP_EOL;
        do_query('COMMIT');
        do_query('START TRANSACTION');
        $commit_interval = 0;
    }
}
fclose($second_names_stream);
fclose($first_names_stream);

do_query('COMMIT');
do_query('INSERT INTO users_index SELECT * FROM users_no_index');

do_query('CREATE INDEX birthday_date USING BTREE ON users_index (birthday_date)');

do_query('CREATE INDEX birthday_day USING BTREE ON users_index (birthday_day)');

function do_query(string $query): void {
    global $query_log;
    global /** @global mysqli $connection */ $connection;

    if (USE_QUERY_LOG) {
        $result = fwrite($query_log, $query . ';' . PHP_EOL);
    } else {
        $result = $connection->query($query);
    }
    ensure_success($result);
}

function ensure_success($result) {
    global $connection;
    if (!$result) {
        http_response_code(500);
        echo 'Called at ', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]['line'] . ' ', $connection->error, PHP_EOL;
        exit;
    }
}