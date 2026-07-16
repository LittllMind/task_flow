<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_');
unlink($tmp);
$pdo = Database::get($tmp);
$pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER, due_at TEXT, done INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$repo = new TaskRepository($pdo);

// CREATE
$id = $repo->create(['title' => 'Rédiger spec', 'category' => 'Dev', 'subcategory' => 'TaskFlow', 'priority' => 2, 'due_at' => '2026-07-18']);
assert(is_int($id) && $id > 0, 'create should return an id');

// READ
$task = $repo->find($id);
assert($task !== null, 'find should return the task');
assert($task['title'] === 'Rédiger spec', 'title should match');

// UPDATE
$repo->update($id, ['title' => 'Spec finalisée', 'priority' => 1]);
$updated = $repo->find($id);
assert($updated['title'] === 'Spec finalisée', 'title updated');
assert((int) $updated['priority'] === 1, 'priority updated');
assert($updated['category'] === 'Dev', 'untouched field preserved');

// LIST
$tasks = $repo->findIncomplete();
assert(count($tasks) === 1, 'one incomplete task');

// DONE
$repo->markDone($id);
assert(count($repo->findIncomplete()) === 0, 'after done list is empty');
$done = $repo->find($id);
assert((int) $done['done'] === 1, 'done flag set');

// DELETE
$repo->delete($id);
assert($repo->find($id) === null, 'deleted task not found');

// CATEGORIES
$keys = array_keys($repo->categories());
assert(in_array('Dev', $keys, true) && in_array('Pro', $keys, true), 'categories present');

unlink($tmp);
echo "Tests OK
";
