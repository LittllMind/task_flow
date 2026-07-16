<?php

declare(strict_types=1);

namespace TaskFlow;

use PDO;

final class TaskRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tasks (title, category, subcategory, priority, due_at) VALUES (:title, :category, :subcategory, :priority, :due_at)');
        $stmt->execute([
            ':title' => $data['title'],
            ':category' => $data['category'],
            ':subcategory' => $data['subcategory'] ?? null,
            ':priority' => $data['priority'],
            ':due_at' => $data['due_at'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findIncomplete(?string $category = null): array
    {
        $sql = 'SELECT * FROM tasks WHERE done = 0';
        $params = [];
        if ($category) {
            $sql .= ' AND category = :category';
            $params[':category'] = $category;
        }
        $sql .= ' ORDER BY due_at IS NULL, due_at ASC, priority ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findDone(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE done = 1 ORDER BY done_at DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $changes): void
    {
        $allowed = ['title', 'category', 'subcategory', 'priority', 'due_at', 'done', 'done_at'];
        $fields = [];
        $params = [];
        foreach ($changes as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $fields[] = "$key = :$key";
            $params[":$key"] = $value === '' ? null : $value;
        }
        if (empty($fields)) {
            return;
        }
        $params[':id'] = $id;
        $stmt = $this->pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function restore(int $id): void
    {
        $this->update($id, ['done' => 0, 'done_at' => null]);
    }

    public function markDone(int $id): void
    {
        $this->update($id, ['done' => 1, 'done_at' => date('Y-m-d H:i:s')]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM tasks WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * @return array<int, string[]>
     */
    public function categories(): array
    {
        return [
            'Perso' => ['Courses', 'Santé', 'Famille'],
            'Pro' => ['Roland', 'CIVITAS', 'Appels'],
            'Dev' => ['TaskFlow', 'Matricothèque', 'LMLaP'],
            'Veille' => ['Météo', 'Veille-tech', 'Veille-concurrence'],
            'Foncier' => ['Carte', 'Dossiers', 'RDV'],
            'Projets' => ['Bougies', 'VINYLS', 'Fundisc'],
            'Administratif' => ['Impôt', 'Banque', 'Assurance'],
        ];
    }
}
