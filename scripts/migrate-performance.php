<?php
// CLI only: php scripts/migrate-performance.php [--apply]
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../connection/connection.php';
$manifest = json_decode(file_get_contents(__DIR__ . '/../database/performance-indexes.json'), true, 512, JSON_THROW_ON_ERROR);
$apply = in_array('--apply', $argv, true);
$existing = $pdo->query('SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list
                        FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() GROUP BY TABLE_NAME,INDEX_NAME')->fetchAll(PDO::FETCH_ASSOC);
foreach ($manifest as $index) {
    foreach (array_merge([$index['table'], $index['name']], $index['columns']) as $identifier) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
            throw new RuntimeException('Invalid index identifier');
        }
    }
    $covered = false;
    foreach ($existing as $row) {
        if ($row['TABLE_NAME'] !== $index['table']) continue;
        $columns = explode(',', $row['columns_list']);
        if (array_slice($columns, 0, count($index['columns'])) === $index['columns']) $covered = true;
        if ($row['INDEX_NAME'] === $index['name'] && $columns !== $index['columns']) {
            throw new RuntimeException('Existing index name has different columns: ' . $index['name']);
        }
    }
    if ($covered) {
        echo 'Already covered: ' . $index['name'] . PHP_EOL;
        continue;
    }
    $sql = 'CREATE INDEX `' . $index['name'] . '` ON `' . $index['table'] . '` (`' . implode('`,`', $index['columns']) . '`)';
    if ($apply) $pdo->exec($sql);
    echo ($apply ? 'Created: ' : 'Pending: ') . $index['name'] . PHP_EOL;
}
