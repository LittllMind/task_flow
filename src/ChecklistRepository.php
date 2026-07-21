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
            CREATE TABLE IF NOT EXISTS checklists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                checklist_date TEXT NOT NULL,
                title TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (checklist_date)
            )
        ');
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
    }

    public function findOrCreateToday(string $title = 'Checklist Hermès du jour'): array
    {
        return $this->findOrCreateForDate(date('Y-m-d'), $title);
    }

    public function findOrCreateForDate(string $date, string $title = 'Checklist Hermès du jour'): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklists WHERE checklist_date = :date');
        $stmt->execute([':date' => $date]);
        $row = $stmt->fetch();
        if (!$row) {
            $insert = $this->pdo->prepare('INSERT INTO checklists (checklist_date, title) VALUES (:date, :title)');
            $insert->execute([':date' => $date, ':title' => $title]);
            $row = [
                'id' => (int) $this->pdo->lastInsertId(),
                'checklist_date' => $date,
                'title' => $title,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        return (array) $row;
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

    public function deleteChecklist(int $checklistId): void
    {
        $this->pdo->prepare('DELETE FROM checklists WHERE id = :id')->execute([':id' => $checklistId]);
    }

    public function itemsFor(int $checklistId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklist_items WHERE checklist_id = :cid ORDER BY sort_order, id');
        $stmt->execute([':cid' => $checklistId]);
        return $stmt->fetchAll();
    }

    public function findByDate(string $date): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM checklists WHERE checklist_date = :date');
        $stmt->execute([':date' => $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listDates(): array
    {
        return $this->pdo->query('SELECT * FROM checklists ORDER BY checklist_date DESC')->fetchAll();
    }
}
