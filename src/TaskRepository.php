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
        $this->migrateCategoriesSchema();
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS categories (name TEXT NOT NULL, subcategory TEXT NOT NULL DEFAULT \'\', PRIMARY KEY (name, subcategory))');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS task_dependencies (blocker_id INTEGER NOT NULL, blocked_id INTEGER NOT NULL, PRIMARY KEY (blocker_id, blocked_id), FOREIGN KEY (blocker_id) REFERENCES tasks(id) ON DELETE CASCADE, FOREIGN KEY (blocked_id) REFERENCES tasks(id) ON DELETE CASCADE)');
        try {
            $this->pdo->exec('ALTER TABLE tasks ADD COLUMN done_at TEXT');
        } catch (\PDOException $e) {
            // already exists
        }
    }

    private function migrateCategoriesSchema(): void
    {
        // Old categories table had a single TEXT PRIMARY KEY on name, allowing only one subcategory per category.
        $info = $this->pdo->query("PRAGMA table_info('categories')")->fetchAll();
        if (empty($info)) {
            return;
        }
        $hasCompositePk = false;
        foreach ($info as $col) {
            if ($col['name'] === 'subcategory' && ($col['pk'] ?? 0) == 2) {
                $hasCompositePk = true;
                break;
            }
        }
        if ($hasCompositePk) {
            return;
        }
        $this->pdo->exec('ALTER TABLE categories RENAME TO categories_old');
        $this->pdo->exec('CREATE TABLE categories (name TEXT NOT NULL, subcategory TEXT NOT NULL DEFAULT \'\', PRIMARY KEY (name, subcategory))');
        $stmt = $this->pdo->query('SELECT name, subcategory FROM categories_old');
        $seen = [];
        $insert = $this->pdo->prepare('INSERT OR IGNORE INTO categories (name, subcategory) VALUES (:name, :subcategory)');
        while ($row = $stmt->fetch()) {
            $key = $row['name'] . '|' . $row['subcategory'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $insert->execute([':name' => $row['name'], ':subcategory' => $row['subcategory']]);
        }
        $this->pdo->exec('DROP TABLE categories_old');
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
        $sql .= ' ORDER BY due_at IS NULL, (CASE WHEN due_at < DATE(\'now\') THEN 0 ELSE 1 END) ASC, due_at ASC, priority ASC';
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

    public function isOverdue(int $id): bool
    {
        $task = $this->find($id);
        if (!$task || empty($task['due_at']) || (int) $task['done'] === 1) {
            return false;
        }
        return $task['due_at'] < date('Y-m-d');
    }

    public function stats(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM tasks WHERE done = 0')->fetchColumn();
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE done = 0 AND due_at IS NOT NULL AND due_at < DATE(\'now\')');
        $stmt->execute();
        $overdue = (int) $stmt->fetchColumn();
        $done = (int) $this->pdo->query('SELECT COUNT(*) FROM tasks WHERE done = 1')->fetchColumn();
        return ['total' => $total, 'overdue' => $overdue, 'done' => $done];
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
        if (!$this->canBeDone($id)) {
            $titles = array_column($this->blockersFor($id), 'title');
            throw new \RuntimeException('Bloquée par : ' . implode(', ', $titles));
        }
        $this->update($id, ['done' => 1, 'done_at' => date('Y-m-d H:i:s')]);
    }

    public function canBeDone(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM task_dependencies d JOIN tasks t ON t.id = d.blocker_id WHERE d.blocked_id = :id AND t.done = 0 LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() === false;
    }

    public function blockersFor(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT t.* FROM tasks t JOIN task_dependencies d ON d.blocker_id = t.id WHERE d.blocked_id = :id AND t.done = 0 ORDER BY t.due_at IS NULL, t.due_at ASC');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public function addDependency(int $blockerId, int $blockedId): void
    {
        if ($blockerId === $blockedId) {
            throw new \RuntimeException('Une tâche ne peut pas se bloquer elle-même.');
        }
        if ($this->wouldCycle($blockerId, $blockedId)) {
            throw new \RuntimeException('Cette dépendance créerait un cycle.');
        }
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO task_dependencies (blocker_id, blocked_id) VALUES (:blocker_id, :blocked_id)');
        $stmt->execute([':blocker_id' => $blockerId, ':blocked_id' => $blockedId]);
    }

    public function removeDependency(int $blockerId, int $blockedId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM task_dependencies WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id');
        $stmt->execute([':blocker_id' => $blockerId, ':blocked_id' => $blockedId]);
    }

    private function wouldCycle(int $from, int $intTo): bool
    {
        // check if adding edge from -> to would create a cycle; i.e. if from is reachable from to
        $toVisit = [$from];
        $seen = [];
        $stmt = $this->pdo->prepare('SELECT blocked_id FROM task_dependencies WHERE blocker_id = :id');
        while ($toVisit) {
            $current = array_pop($toVisit);
            if ($current === $intTo) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $stmt->execute([':id' => $current]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $next) {
                $toVisit[] = (int) $next;
            }
        }
        return false;
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM tasks WHERE id = :id')->execute([':id' => $id]);
    }
}
