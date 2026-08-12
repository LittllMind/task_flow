<?php

declare(strict_types=1);

// Seed: TaskFlow v0.4 starter dataset — idempotent within this seed lifecycle.
// Deletes all tasks/categories/dependencies to ensure a clean, known baseline.

return function (PDO $pdo, TaskFlow\TaskRepository $repo): void {
    $pdo->exec('DELETE FROM task_dependencies');
    $pdo->exec('DELETE FROM tasks');
    $pdo->exec('DELETE FROM categories');
    try {
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('tasks', 'categories')");
    } catch (PDOException $e) {
        // table may not exist on fresh empty DB
    }

    foreach (['Perso', 'Pro'] as $cat) {
        $repo->addSubcategory($cat, 'Jardin');
        $repo->addSubcategory($cat, 'Banque');
        $repo->addSubcategory($cat, 'Prêts');
    }

    $pergola = $repo->create([
        'title' => 'Structure pergola jardin',
        'category' => 'Perso',
        'subcategory' => 'Jardin',
        'priority' => 2,
        'due_at' => '2026-07-25',
    ]);
    $bamboo = $repo->create([
        'title' => 'Couper du bamboo',
        'category' => 'Perso',
        'subcategory' => 'Jardin',
        'priority' => 1,
        'due_at' => '2026-07-23',
    ]);
    $ca = $repo->create([
        'title' => 'Ouvrir compte pro CA',
        'category' => 'Pro',
        'subcategory' => 'Banque',
        'priority' => 1,
        'due_at' => '2026-07-30',
    ]);
    $stop = $repo->create([
        'title' => 'Stopper les demandes de prêts',
        'category' => 'Pro',
        'subcategory' => 'Prêts',
        'priority' => 3,
        'due_at' => null,
    ]);

    $repo->addDependency($bamboo, $pergola);
    $repo->addDependency($stop, $ca);
};
