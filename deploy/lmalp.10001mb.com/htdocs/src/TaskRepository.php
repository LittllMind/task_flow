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

    public function markDone(int $id): void
    {
        $this->pdo->prepare('UPDATE tasks SET done = 1 WHERE id = :id')->execute([':id' => $id]);
    }
}
