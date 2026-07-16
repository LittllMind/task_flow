<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

function createRepo() {
    $tmp = tempnam(sys_get_temp_dir(), 'tf_');
    unlink($tmp);
    $pdo = Database::get($tmp);
    return [$pdo, $tmp];
}

[$pdo, $tmp] = createRepo();
$repo = new TaskRepository($pdo);

// Categories from DB initially
$cats = $repo->categories();
assert(isset($cats['Dev']), 'Dev preset present');
assert(in_array('TaskFlow', $cats['Dev'], true), 'TaskFlow subcategory present');

// Add category
$repo->addCategory('Nova');
$cats = $repo->categories();
assert(isset($cats['Nova']), 'Nova added');

// Add subcategory
$repo->addSubcategory('Nova', 'Sub 1');
assert(in_array('Sub 1', $repo->categories()['Nova'], true), 'Sub 1 added');

// Remove subcategory
$repo->removeSubcategory('Nova', 'Sub 1');
assert(!in_array('Sub 1', $repo->categories()['Nova'], true), 'Sub 1 removed');

// Remove category
$repo->removeCategory('Nova');
$cats = $repo->categories();
assert(!isset($cats['Nova']), 'Nova removed');

// Task CRUD
$id = $repo->create(['title' => 'Task', 'category' => 'Dev', 'subcategory' => 'TaskFlow', 'priority' => 2, 'due_at' => null]);
$repo->markDone($id);
assert(count($repo->findDone()) === 1, 'done one');
$repo->restore($id);
assert(count($repo->findIncomplete()) === 1, 'restored');

unlink($tmp);
echo "Tests OK
";
