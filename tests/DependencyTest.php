<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_');
unlink($tmp);
$pdo = Database::get($tmp);
$repo = new TaskRepository($pdo);

// Créer deux tâches
$a = $repo->create(['title' => 'A', 'category' => 'Dev', 'subcategory' => '', 'priority' => 2, 'due_at' => '']);
$b = $repo->create(['title' => 'B', 'category' => 'Dev', 'subcategory' => '', 'priority' => 2, 'due_at' => '']);

// A doit être faite avant B (A bloque B)
$repo->addDependency($a, $b);

// Vérifier B est bloquée par A
$blockers = $repo->blockersFor($b);
assert(count($blockers) === 1, 'B a un bloqueur');
assert($blockers[0]['id'] == $a, 'bloqueur est A');

// Hard block : impossible de terminer B tant que A n'est pas done
try {
    $repo->markDone($b);
    assert(false, 'markDone aurait dû bloquer B');
} catch (\RuntimeException $e) {
    assert(strpos($e->getMessage(), 'Bloquée') !== false, 'message de blocage');
}

// A terminée, B débloquée
$repo->markDone($a);
$repo->markDone($b);
assert($repo->find($b)['done'] == 1, 'B est done après déblocage');

// Cycle interdit (A bloque B, B ne peut pas bloquer A)
try {
    $repo->addDependency($b, $a);
    assert(false, 'cycle aurait dû être refusé');
} catch (\RuntimeException $e) {
    assert(strpos($e->getMessage(), 'cycle') !== false, 'message cycle');
}

// Suppression de dépendance
$repo->removeDependency($a, $b);
assert(count($repo->blockersFor($b)) === 0, 'B débloquée après suppression');

unlink($tmp);
echo "Tests dépendances OK\n";
