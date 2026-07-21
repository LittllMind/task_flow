#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\DisciplineRepository;

$dbPath = $argv[1] ?? null;
if (!$dbPath) {
    fwrite(STDERR, "Usage: php scripts/run-seeds.php <path/to/taskflow.db>\n");
    exit(1);
}

// Refuse prod DB path: data in prod only changes through web UI or explicit DB sync.
$prodDbPath = realpath(__DIR__ . '/../deploy/lmalp.10001mb.com/htdocs/taskflow.db');
$requestedDbPath = realpath($dbPath);
if ($requestedDbPath && $requestedDbPath === $prodDbPath) {
    fwrite(STDERR, "ERROR: refusing to seed prod DB directly: $dbPath\n");
    exit(1);
}

function backup(string $dbPath): void {
    $dir = dirname($dbPath);
    $base = basename($dbPath);
    $target = $dir . '/.' . $base . '.before-seeds.' . date('Ymd-His') . '.bak';
    copy($dbPath, $target);
    fwrite(STDERR, "Backup: $target\n");
}

backup($dbPath);

$seedsDir = __DIR__ . '/../seeds';
$appliedTable = <<<SQL
    CREATE TABLE IF NOT EXISTS seeds_applied (
        name TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
SQL;

$pdo = Database::get($dbPath);
$pdo->exec($appliedTable);

// Need both repositories so seed lambdas get the right references.
$taskRepo = new \TaskFlow\TaskRepository($pdo);
$discipline = new DisciplineRepository($pdo);

$applied = array_column($pdo->query('SELECT name FROM seeds_applied ORDER BY name')->fetchAll(), 'name');
$files = glob($seedsDir . '/*.php');
sort($files);

$batch = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "SKIP  {$name}\n";
        continue;
    }
    $seed = require $file;
    if (!is_callable($seed)) {
        fwrite(STDERR, "SKIP  {$name} (not callable)\n");
        continue;
    }
    try {
        $seed($pdo, $taskRepo);
        $stmt = $pdo->prepare('INSERT INTO seeds_applied (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        echo "OK    {$name}\n";
        $batch++;
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL  {$name}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Seeds applied: {$batch}\n";
