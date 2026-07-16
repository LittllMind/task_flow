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
        $this->ensureSchema();
        $this->seedDefaults();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER CHECK(priority BETWEEN 1 AND 3), due_at TEXT, done INTEGER DEFAULT 0, done_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS categories (name TEXT PRIMARY KEY, subcategory TEXT NOT NULL DEFAULT \'\')');
        try {
            $this->pdo->exec('ALTER TABLE tasks ADD COLUMN done_at TEXT');
        } catch (\PDOException $e) {
            // already exists
        }
    }

    private function seedDefaults(): void
    {
        $default = [
            'Perso' => ['Courses', 'Santé', 'Famille'],
            'Pro' => ['Roland', 'CIVITAS', 'Appels'],
            'Dev' => ['TaskFlow', 'Matricothèque', 'LMLaP'],
            'Veille' => ['Météo', 'Veille-tech', 'Veille-concurrence'],
            'Foncier' => ['Carte', 'Dossiers', 'RDV'],
            'Projets' => ['Bougies', 'VINYLS', 'Fundisc'],
            'Administratif' => ['Impôt', 'Banque', 'Assurance'],
        ];

        $existing = $this->pdo->query('SELECT name, subcategory FROM categories')->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($existing as $row) {
            $map[trim($row['name'])][] = $row['subcategory'];
        }

        foreach ($default as $cat => $subs) {
            foreach ($subs as $sub) {
                if (!isset($map[$cat]) || !in_array($sub, $map[$cat], true)) {
                    $this->insertCategory($cat, $sub);
                }
            }
        }
    }

    private function insertCategory(string $category, string $subcategory): void
    {
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO categories (name, subcategory) VALUES (:name, :subcategory)');
        $stmt->execute([':name' => $category, ':subcategory' => $subcategory]);
    }

    public function categories(): array
    {
        $rows = $this->pdo->query('SELECT name, subcategory FROM categories ORDER BY name, subcategory')->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[trim($row['name'])][] = $row['subcategory'];
        }
        return $out;
    }

    public function addCategory(string $category): void
    {
        $name = trim($category);
        if ($name === '') {
            return;
        }
        $this->insertCategory($name, '');
    }

    public function removeCategory(string $category): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE name = :name');
        $stmt->execute([':name' => trim($category)]);
    }

    public function addSubcategory(string $category, string $subcategory): void
    {
        $cat = trim($category);
        $sub = trim($subcategory);
        if ($cat === '' || $sub === '') {
            return;
        }
        $this->insertCategory($cat, $sub);
    }

    public function removeSubcategory(string $category, string $subcategory): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE name = :name AND subcategory = :subcategory');
        $stmt->execute([':name' => trim($category), ':subcategory' => trim($subcategory)]);
        // Ensure category row remains (with empty subcat) if all subs removed
        if (count($this->categories()[trim($category)] ?? []) === 0) {
            $this->insertCategory(trim($category), '');
        }
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
}
