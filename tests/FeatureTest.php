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

// Default categories have multiple subcategories
$cats = $repo->categories();
foreach (['Perso' => ['Courses','Santé','Famille'], 'Dev' => ['TaskFlow','Matricothèque','LMLaP']] as $cat => $expectedSubs) {
    assert(isset($cats[$cat]), "category {$cat} present");
    foreach ($expectedSubs as $sub) {
        assert(in_array($sub, $cats[$cat], true), "subcategory {$sub} present in {$cat}");
    }
}

// Add/Remove subcategory
$repo->addSubcategory('Dev', 'TestSub');
assert(in_array('TestSub', $repo->categories()['Dev'], true), 'subcategory added');
$repo->removeSubcategory('Dev', 'TestSub');
assert(!in_array('TestSub', $repo->categories()['Dev'], true), 'subcategory removed');

// Remove category keeps only empty shell, no crash
$repo->addCategory('Audit');
assert(isset($repo->categories()['Audit']), 'category Auditt added');
$repo->removeCategory('Audit');
assert(!isset($repo->categories()['Audit']), 'category Audit removed');

// Add category bug (admin.php originally sent 'subcategory' field)
$repo->addCategory('TestCat2');
assert(isset($repo->categories()['TestCat2']), 'category added via addCategory');
$repo->removeCategory('TestCat2');

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

// Category add bug (admin.php sends 'subcategory' field)
$repo->addCategory('Audit');
assert(isset($repo->categories()['Audit']), 'category added via addCategory');
$repo->removeCategory('Audit');

unlink($tmp);
echo "Tests OK\n";
