<?php

declare(strict_types=1);

namespace TaskFlow;

use PDO;

final class ChecklistRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS checklist_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                checklist_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                done INTEGER DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
            )
        ');

        // Créer la table checklists seulement si elle n'existe pas.
        // Si elle existe avec un ancien schema (checklist_date legacy), on ne touche pas la structure.
        $tables = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='checklists'")->fetchAll();
        if (empty($tables)) {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS checklists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ');
        } else {
            $cols = $this->pdo->query("PRAGMA table_info(checklists)")->fetchAll();
            $hasDate = false;
            foreach ($cols as $c) {
                if ($c['name'] === 'checklist_date') {
                    $hasDate = true;
                    break;
                }
            }
            if ($hasDate) {
                $this->pdo->exec('
                    CREATE TABLE checklists_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )
                ');
                $this->pdo->exec('INSERT INTO checklists_new (id, title, created_at) SELECT id, title, COALESCE(created_at, datetime("now")) FROM checklists');
                $this->pdo->exec('DROP TABLE checklists');
                $this->pdo->exec('ALTER TABLE checklists_new RENAME TO checklists');
            }
        }
    }

    public function create(string $title): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO checklists (title) VALUES (:title)');
        $stmt->execute([':title' => trim($title)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function rename(int $id, string $title): void
    {
        $this->pdo->prepare('UPDATE checklists SET title = :title WHERE id = :id')->execute([':id' => $id, ':title' => trim($title)]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklists WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findDefault(): ?array
    {
        $rows = $this->pdo->query('SELECT * FROM checklists ORDER BY id DESC LIMIT 1')->fetchAll();
        return $rows[0] ?? null;
    }

    public function listAll(): array
    {
        return $this->pdo->query('SELECT * FROM checklists ORDER BY id DESC')->fetchAll();
    }

    public function deleteChecklist(int $id): void
    {
        $this->pdo->prepare('DELETE FROM checklists WHERE id = :id')->execute([':id' => $id]);
    }

    public function addItem(int $checklistId, string $label, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO checklist_items (checklist_id, label, sort_order) VALUES (:cid, :label, :order)');
        $stmt->execute([':cid' => $checklistId, ':label' => trim($label), ':order' => $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function toggleItem(int $itemId): void
    {
        $this->pdo->prepare('UPDATE checklist_items SET done = 1 - done WHERE id = :id')->execute([':id' => $itemId]);
    }

    public function updateItemLabel(int $itemId, string $label): void
    {
        $this->pdo->prepare('UPDATE checklist_items SET label = :label WHERE id = :id')->execute([':id' => $itemId, ':label' => trim($label)]);
    }

    public function deleteItem(int $itemId): void
    {
        $this->pdo->prepare('DELETE FROM checklist_items WHERE id = :id')->execute([':id' => $itemId]);
    }

    public function itemsFor(int $checklistId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklist_items WHERE checklist_id = :cid ORDER BY sort_order, id');
        $stmt->execute([':cid' => $checklistId]);
        return $stmt->fetchAll();
    }

    public function statsFor(int $checklistId): array
    {
        $items = $this->itemsFor($checklistId);
        return ['total' => count($items), 'done' => count(array_filter($items, fn($i) => (int) $i['done'] === 1))];
    }

    /**
     * Retourne tous les items non cochés de toutes les checklists,
     * avec le score et le poids aléatoire pondéré.
     */
    public function findOpenItems(): array
    {
        $stmt = $this->pdo->query('
            SELECT i.id, i.label, i.checklist_id, c.title AS checklist_title
            FROM checklist_items i
            JOIN checklists c ON c.id = i.checklist_id
            WHERE i.done = 0
            ORDER BY c.id, i.sort_order, i.id
        ');
        return $stmt->fetchAll();
    }

    public function markItemDone(int $itemId): void
    {
        $this->pdo->prepare('UPDATE checklist_items SET done = 1 WHERE id = :id')->execute([':id' => $itemId]);
    }

    public function findItemById(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklist_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function search(string $q): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklists WHERE title LIKE :q ORDER BY id DESC');
        $stmt->execute([':q' => '%' . $q . '%']);
        return $stmt->fetchAll();
    }
}
