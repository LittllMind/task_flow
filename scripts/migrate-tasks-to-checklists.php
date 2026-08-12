<?php

declare(strict_types=1);

// Migration : transforme toutes les tâches TaskFlow v0.x en checklists.
// Idempotente : ne crée pas de doublon si déjà exécutée.

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\ChecklistRepository;
use TaskFlow\Database;

$dbPath = $argv[1] ?? __DIR__ . '/../data/taskflow.db';
if (!file_exists($dbPath)) {
    fwrite(STDERR, "DB non trouvée: $dbPath\n");
    exit(1);
}

$pdo = Database::get($dbPath);
$repo = new ChecklistRepository($pdo);

// Mapping des tâches par checklist.
$mapping = [
    'CIVITAS — Patrice' => [
        ['Relire mail Patrice 49109 (huissier, bailleurs, Albert, arbres)', true],
        ['Identifier et rechercher la personne en baskets constat à la Séraphothèque', true],
        ['Récupérer/transmettre liste bailleurs camping 2000 puis post-2009', true],
        ['Traiter photo prunus abattu parcelle Roux → demander photos a patrice', true],
    ],
    'Jardin / Pergola' => [
        ['Structure pergola jardin', false],
        ['Couper du bamboo', false],
        ['Commander du bois', false],
        ['Contact scierie, meuble, sciure', false],
    ],
    'Admin Pro' => [
        ['Ouvrir compte pro CA', false],
        ['Stopper les demandes de prêts', true],
        ['Monter une société', false],
        ['Déclaration urssaf', false],
        ['Ajouter rib', false],
    ],
    'LMLaP' => [
        ['REFONTE LMALP - CITOYEN', false],
        ['Chronologie - LItige Boutique "En saison"', false],
    ],
    'Perso divers' => [
        ['300 - manon', false],
        ['Là où chantent les écrevisses', false],
        ['Joints carrelage', false],
    ],
];

$pdo->beginTransaction();

try {
    foreach ($mapping as $title => $items) {
        // Vérifie si la checklist existe déjà (idempotence).
        $stmt = $pdo->prepare('SELECT id FROM checklists WHERE title = ?');
        $stmt->execute([$title]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $checklistId = (int) $existing;
            echo "Checklist existante: $title (id=$checklistId)\n";
        } else {
            $checklistId = $repo->create($title);
            echo "Checklist créée: $title (id=$checklistId)\n";
        }

        // Items
        foreach ($items as $index => [$label, $done]) {
            $stmt = $pdo->prepare(
                'SELECT id FROM checklist_items WHERE checklist_id = ? AND label = ?'
            );
            $stmt->execute([$checklistId, $label]);
            $existingItem = $stmt->fetchColumn();

            if ($existingItem) {
                $stmt = $pdo->prepare(
                    'UPDATE checklist_items SET done = ?, sort_order = ? WHERE id = ?'
                );
                $stmt->execute([$done ? 1 : 0, $index, $existingItem]);
                echo "  Item mis à jour: $label\n";
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO checklist_items (checklist_id, label, done, sort_order) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$checklistId, $label, $done ? 1 : 0, $index]);
                echo "  Item créé: $label\n";
            }
        }
    }

    // Archivage des tâches historiques (pas de suppression définitive).
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM sqlite_master WHERE type="table" AND name="tasks_archive"'
    );
    $hasArchive = (bool) $stmt->fetchColumn();

    if (!$hasArchive) {
        $pdo->exec('CREATE TABLE tasks_archive AS SELECT * FROM tasks WHERE 0');
        $pdo->exec('ALTER TABLE tasks_archive ADD COLUMN archived_at TEXT DEFAULT CURRENT_TIMESTAMP');
    }

    $pdo->exec('INSERT INTO tasks_archive SELECT *, CURRENT_TIMESTAMP FROM tasks');
    $archived = $pdo->query('SELECT changes()')->fetchColumn();
    $pdo->exec('DELETE FROM tasks');

    $pdo->commit();
    echo "\nMigration terminée. $archived tâches archivées, table tasks vidée.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Échec migration: " . $e->getMessage() . "\n");
    exit(1);
}
