<?php

declare(strict_types=1);

// Seed: today checklist from Patrice email "Huissier, bailleurs, Albert, Arbres du lotissement".

use TaskFlow\ChecklistRepository;

return function (PDO $pdo, TaskFlow\TaskRepository $taskRepo): void {
    $repo = new ChecklistRepository($pdo);
    // Only seed today's checklist if none exists yet.
    if ($repo->findByDate(date('Y-m-d'))) {
        return;
    }

    $cl = $repo->findOrCreateToday('Mail Patrice — Huissier, bailleurs, Albert, Arbres');

    $actions = [
        'Relire le mail de Patrice et identifier les 4 sujets (huissier, bailleurs, Albert, arbres)',
        'Noter dans Obsidian les points qui nécessitent une réponse de Roland',
        'Vérifier si des dossiers CIVITAS existent déjà pour ces sujets',
        'Préparer un brouillon de réponse structurée par sujet',
        'Contacter Roland pour validation des prochaines actions',
    ];

    foreach ($actions as $i => $label) {
        $repo->addItem($cl['id'], $label, $i + 1);
    }
};
