<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_');
unlink($tmp);
$pdo = Database::get($tmp);
$pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER, due_at TEXT, done INTEGER DEFAULT 0, done_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$repo = new TaskRepository($pdo);

// CREATE
$id = $repo->create(['title' => 'Rédiger spec', 'category' => 'Dev', 'subcategory' => 'TaskFlow', 'priority' => 2, 'due_at' => '2026-07-18']);
assert(is_int($id) && $id > 0, 'create should return an id');

// READ
assert($repo->find($id) !== null, 'find should return the task');

// DONE
$repo->markDone($id);
assert(count($repo->findIncomplete()) === 0, 'after done list is empty');
$done = $repo->find($id);
assert((int) $done['done'] === 1, 'done flag set');
assert(!empty($done['done_at']), 'done_at should be set');

// DONE LIST
$doneList = $repo->findDone();
assert(count($doneList) === 1, 'one done task');

// RESTORE
$repo->restore($id);
$restored = $repo->find($id);
assert((int) $restored['done'] === 0, 'done cleared');
assert($restored['done_at'] === null, 'done_at cleared');
assert(count($repo->findIncomplete()) === 1, 'back in incomplete list');

// DELETE
$repo->delete($id);
assert($repo->find($id) === null, 'deleted task not found');

// UPDATE
$id2 = $repo->create(['title' => 'Update test', 'category' => 'Pro', 'subcategory' => 'CIVITAS', 'priority' => 3, 'due_at' => '']);
$repo->update($id2, ['title' => 'Updated']);
assert($repo->find($id2)['title'] === 'Updated', 'title updated');

// CATEGORIES
assert(isset($repo->categories()['Dev']), 'Dev category present');

unlink($tmp);
echo "Tests OK
";
