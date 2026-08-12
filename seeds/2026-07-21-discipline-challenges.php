<?php

declare(strict_types=1);

// Seed: daily discipline challenges — kept alongside starter dataset.

use TaskFlow\DisciplineRepository;

return function (PDO $pdo, TaskFlow\TaskRepository $taskRepo): void {
    $discipline = new DisciplineRepository($pdo);

    // Ensure challenges exist (no-op if already present thanks to unique titles not enforced; we add once per seed)
    $discipline->addHabit('corps', '20 Pompes', 20, 'reps', 5);
    $discipline->addHabit('mental', '20 respi gainées', 20, 'reps', 5);
};
