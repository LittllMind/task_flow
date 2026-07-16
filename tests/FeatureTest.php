<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

// Test unitaire minimum : créer une tâche et la relire en base temporaire.
$tmp = tempnam(sys_get_temp_dir(), 'tf_');
unlink($tmp);
$pdo = Database::get($tmp);
$pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER, due_at TEXT, done INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$repo = new TaskRepository($pdo);
$id = $repo->create(['title' => 'Rédiger spec', 'category' => 'Dev', 'subcategory' => 'TaskFlow', 'priority' => 2, 'due_at' => '2026-07-17']);
$tasks = $repo->findIncomplete();
assert(count($tasks) === 1, 'Une tâche insérée doit être listée');
assert($tasks[0]['title'] === 'Rédiger spec', 'Le titre doit correspondre');
$repo->markDone((int) $tasks[0]['id']);
$tasks = $repo->findIncomplete();
assert(count($tasks) === 0, 'Après avoir terminé la liste doit être vide');
unlink($tmp);
echo "Tests OK\n";
