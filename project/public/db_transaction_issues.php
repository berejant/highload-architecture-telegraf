#!/usr/bin/env php
<?php
/**
 * @link https://en.wikipedia.org/wiki/Isolation_(database_systems)
 * @link https://ru.wikipedia.org/wiki/%D0%A3%D1%80%D0%BE%D0%B2%D0%B5%D0%BD%D1%8C_%D0%B8%D0%B7%D0%BE%D0%BB%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%BD%D0%BE%D1%81%D1%82%D0%B8_%D1%82%D1%80%D0%B0%D0%BD%D0%B7%D0%B0%D0%BA%D1%86%D0%B8%D0%B9
 * @run docker compose exec php-fpm public/db_transaction_issues.php
 */

mysqli_report(MYSQLI_REPORT_ALL & ~MYSQLI_REPORT_INDEX);

/** @var mysqli[] $connections */
$connections = [];
for ($i = 0; $i < 2; $i++) {
    $connections[] = new mysqli('p:mysql', 'default', 'secret', 'default');
}

foreach ($connections as $connection) {
    $connection->query('SET SESSION innodb_lock_wait_timeout = 2');
}

$connections[0]->query('CREATE TABLE IF NOT EXISTS  isolation_test (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `value` INT NOT NULL DEFAULT 0
)');

$isolation_levels = [
    'READ UNCOMMITTED',
    'READ COMMITTED',
    'REPEATABLE READ',
    'SERIALIZABLE',
];

/** @var callable[] $tests */
$tests = [
    'Dirty reads' => 'test_dirty_reads',
    'Lost updates' => 'test_lost_update',
    'Non-repeatable reads' => 'test_non_repeatable_reads',
    'Phantoms' => 'test_phantoms',
];

$exception_results_map = [
    'deadlock' => 'Deadlock',
    'lock wait timeout' => 'Lock timeout',
];

$headers = array_keys($tests);
array_unshift($headers, 'Isolation level');
$column_width = max(array_map('strlen', $headers)) + 4;
foreach ($headers as $header) {
    print_cell($header);
}
echo PHP_EOL;

foreach ($isolation_levels as $isolation_level) {
    print_cell($isolation_level);
    foreach ($connections as $connection) {
        $connection->query('SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolation_level);
    }

    foreach ($tests as $test) {
        $connections[0]->query('TRUNCATE TABLE isolation_test');

        try {
            [$expected, $actual] = $test();
            $result =  $expected . ($expected == $actual ? '==' : '!=') . $actual;
        } catch (\mysqli_sql_exception $e) {
            foreach ($connections as $connection) {
                $connection->query('ROLLBACK');
            }

            $result = 'Exception';
            foreach ($exception_results_map as $substring => $result_string) {
                if (stripos($e->getMessage(), $substring) !== false) {
                    $result = $result_string;
                    break;
                }
            }
        }
        print_cell($result);
    }
    echo PHP_EOL;
}

function test_dirty_reads(): array {
    global $connections;

    $connections[0]->query('INSERT INTO isolation_test VALUES(1, 10)');

    $connections[0]->query('START TRANSACTION');
    $connections[0]->query('UPDATE isolation_test SET value = value + 25 WHERE id = 1');

    $actual_value = $connections[1]->query('SELECT value FROM isolation_test WHERE id = 1')->fetch_row()[0];
    $connections[0]->query('ROLLBACK');

    $expected_value = $connections[1]->query('SELECT value FROM isolation_test WHERE id = 1')->fetch_row()[0];
    return [$expected_value, $actual_value];
}

function test_lost_update (): array {
    global $connections;

    $expected_value = 20+25;

    $connections[0]->query('INSERT INTO isolation_test VALUES(1, 0)');

    $connections[0]->query(
        'INSERT INTO isolation_test
        SELECT id, value + 20 + SLEEP(2) FROM isolation_test WHERE id = 1
        ON DUPLICATE KEY UPDATE value = VALUES(value)',
        MYSQLI_ASYNC
    );
    $connections[1]->query('UPDATE isolation_test SET value = value + 25 WHERE id = 1', MYSQLI_ASYNC);

    // finish_async_queries
    $wait_count = count($connections);
    while ($wait_count) {
        $links = $errors = $reject = $connections;
        $wait_count -= mysqli::poll($links, $errors, $reject, 1);
    }
    foreach ($connections as $index => $connection) {
        if (!$connection->reap_async_query()) {
            throw new mysqli_sql_exception('Connection #' . $index . ': ' . $connection->error, $connection->errno);
        }
    }

    $actual_value = $connections[1]->query('SELECT value FROM isolation_test WHERE id = 1')->fetch_row()[0];

    return [$expected_value, $actual_value];
}

function test_non_repeatable_reads(): array {
    global $connections;

    $connections[0]->query('INSERT INTO isolation_test VALUES(1, 20)');

    $connections[0]->query('START TRANSACTION');
    $connections[1]->query('START TRANSACTION');

    $actual_value = $connections[1]->query('SELECT value FROM isolation_test WHERE id = 1')->fetch_row()[0];
    $connections[0]->query('UPDATE isolation_test SET value = value + 25 WHERE id = 1');

    $connections[0]->query('COMMIT');

    $expected_value = $connections[1]->query('SELECT value FROM isolation_test WHERE id = 1')->fetch_row()[0];
    $connections[1]->query('COMMIT');

    return [$expected_value, $actual_value];
}

function test_phantoms(): array {
    global $connections;

    $connections[0]->query('INSERT INTO isolation_test VALUES(1, 10)');

    $connections[0]->query('START TRANSACTION');
    $connections[1]->query('START TRANSACTION');

    $expected_value = $connections[1]->query('SELECT SUM(value) FROM isolation_test')->fetch_row()[0];

    $connections[0]->query('INSERT INTO isolation_test VALUES(2, 20)');
    $connections[0]->query('COMMIT');

    $actual_value = $connections[1]->query('SELECT SUM(value) FROM isolation_test')->fetch_row()[0];
    $connections[1]->query('COMMIT');

    return [$expected_value, $actual_value];
}

function print_cell (string $row) {
    global $column_width;
    echo str_pad(substr($row, 0, $column_width), $column_width, ' ', STR_PAD_BOTH), '|';
}
