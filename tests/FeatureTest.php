<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_');
unlink($tmp);
$pdo = Database::get($tmp);
$repo = new TaskRepository($pdo);

// Default categories
$cats = $repo->categories();
assert(isset($cats['Dev']), 'Dev default present');

// Add category
$repo->addCategory('TestCat');
assert(isset($repo->categories()['TestCat']), 'category added');
$repo->removeCategory('TestCat');

// Overdue task
$past = date('Y-m-d', strtotime('-1 day'));
$future = date('Y-m-d', strtotime('+1 day'));
$overdueId = $repo->create(['title' => 'Retard', 'category' => 'Dev', 'subcategory' => '', 'priority' => 2, 'due_at' => $past]);
$futureId = $repo->create(['title' => 'Futur', 'category' => 'Dev', 'subcategory' => '', 'priority' => 2, 'due_at' => $future]);
$tasks = $repo->findIncomplete();
assert($tasks[0]['id'] == $overdueId, 'overdue first');
assert($repo->isOverdue($overdueId), 'isOverdue true');
assert(!$repo->isOverdue($futureId), 'isOverdue false for future');

// Stats
$stats = $repo->stats();
assert($stats['total'] >= 2, 'stats total');
assert($stats['overdue'] === 1, 'stats overdue one');

// Done with date
$repo->markDone($overdueId);
assert(count($repo->findDone()) === 1, 'done one');

unlink($tmp);
echo "Tests OK
";
