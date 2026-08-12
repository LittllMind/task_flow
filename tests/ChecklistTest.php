<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\ChecklistRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_check_');
unlink($tmp);
$pdo = Database::get($tmp);
$repo = new ChecklistRepository($pdo);

// Create
$id = $repo->create('Préparation déménagement');
assert($id > 0, 'checklist created');

// Default = dernier modifié
$default = $repo->findDefault();
assert(isset($default['id']) && (int) $default['id'] === $id, 'default finds newest');
assert($default['title'] === 'Préparation déménagement', 'default title');

// No date column
assert(!array_key_exists('checklist_date', $default), 'no date field');

// Rename
$repo->rename($id, 'Préparation déménagement — semaine 1');
$renamed = $repo->findById($id);
assert($renamed['title'] === 'Préparation déménagement — semaine 1', 'renamed');

// Add items
$a = $repo->addItem($id, 'Acheter cartons', 1);
$b = $repo->addItem($id, 'Contacter syndic', 2);
$items = $repo->itemsFor($id);
assert(count($items) === 2, 'two items');

// Toggle
$repo->toggleItem($a);
$done = array_values(array_filter($repo->itemsFor($id), fn($x) => (int) $x['id'] === $a && (int) $x['done'] === 1));
assert(count($done) === 1, 'toggled to done');

// Stats
$stats = $repo->statsFor($id);
assert($stats['total'] === 2 && $stats['done'] === 1, 'stats correct');

// Multiple checklists
$id2 = $repo->create('Courses hebdo');
$list = $repo->listAll();
assert(count($list) === 2, 'two checklists');
assert((int) $list[0]['id'] === $id2, 'last created first');

// Delete item
$repo->deleteItem($b);
assert(count($repo->itemsFor($id)) === 1, 'item deleted');

// Delete checklist cascades
$repo->deleteChecklist($id);
assert(count($repo->listAll()) === 1, 'one checklist left');
assert(count($repo->itemsFor($id)) === 0, 'items cascade deleted');

unlink($tmp);
echo "Checklist tests OK\n";
