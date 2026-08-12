<?php

declare(strict_types=1);

namespace TaskFlow;

use PDO;

/**
 * Suivi des semis / plants avec historique d'arrosage et stats.
 */
final class SeedlingRepository
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
            CREATE TABLE IF NOT EXISTS seedlings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                variety_id INTEGER,
                variety TEXT,
                quantity INTEGER DEFAULT 1,
                seeded_at TEXT,
                repotted_at TEXT,
                last_watered_at TEXT,
                location TEXT,
                origin TEXT,
                note TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (variety_id) REFERENCES varieties(id) ON DELETE SET NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS watering_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seedling_id INTEGER NOT NULL,
                watered_at TEXT DEFAULT CURRENT_DATE,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (seedling_id) REFERENCES seedlings(id) ON DELETE CASCADE
            )
        ');

        // Colonnes legacy
        $cols = $this->pdo->query("PRAGMA table_info(seedlings)")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['variety_id', 'variety', 'repotted_at', 'last_watered_at', 'location', 'origin', 'note'] as $col) {
            if (!in_array($col, $cols, true)) {
                $this->pdo->exec("ALTER TABLE seedlings ADD COLUMN {$col} TEXT");
            }
        }
        if (!in_array('quantity', $cols, true)) {
            $this->pdo->exec('ALTER TABLE seedlings ADD COLUMN quantity INTEGER DEFAULT 1');
        }
    }

    public function listAll(): array
    {
        return $this->pdo
            ->query('SELECT * FROM seedlings ORDER BY created_at DESC')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.*, v.species_name, v.cultivar_name, v.cycle,
                   v.germination_days_min, v.germination_days_max,
                   v.spacing_cm_min, v.spacing_cm_max,
                   v.harvest_days_min, v.harvest_days_max,
                   v.essential_advice, v.propagation_notes,
                   v.seed_production, v.warnings
            FROM seedlings s
            LEFT JOIN varieties v ON v.id = s.variety_id
            WHERE s.id = :id
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO seedlings (name, variety_id, variety, quantity, seeded_at, repotted_at, location, origin, note)
            VALUES (:name, :variety_id, :variety, :quantity, :seeded_at, :repotted_at, :location, :origin, :note)
        ');
        $stmt->execute([
            ':name' => trim($data['name']),
            ':variety_id' => isset($data['variety_id']) ? (int) $data['variety_id'] : null,
            ':variety' => $data['variety'] ? trim($data['variety']) : null,
            ':quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            ':seeded_at' => $data['seeded_at'] ?: null,
            ':repotted_at' => $data['repotted_at'] ?: null,
            ':location' => $data['location'] ? trim($data['location']) : null,
            ':origin' => $data['origin'] ? trim($data['origin']) : null,
            ':note' => $data['note'] ? trim($data['note']) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE seedlings SET
                name = :name,
                variety_id = :variety_id,
                variety = :variety,
                quantity = :quantity,
                seeded_at = :seeded_at,
                repotted_at = :repotted_at,
                location = :location,
                origin = :origin,
                note = :note
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $id,
            ':name' => trim($data['name']),
            ':variety_id' => isset($data['variety_id']) ? (int) $data['variety_id'] : null,
            ':variety' => $data['variety'] ? trim($data['variety']) : null,
            ':quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            ':seeded_at' => $data['seeded_at'] ?: null,
            ':repotted_at' => $data['repotted_at'] ?: null,
            ':location' => $data['location'] ? trim($data['location']) : null,
            ':origin' => $data['origin'] ? trim($data['origin']) : null,
            ':note' => $data['note'] ? trim($data['note']) : null,
        ]);
    }

    public function water(int $id, ?string $date = null): void
    {
        $today = $date ?? date('Y-m-d');
        if ($today === 'today') {
            $today = date('Y-m-d');
        }
        $this->pdo
            ->prepare('UPDATE seedlings SET last_watered_at = :today WHERE id = :id')
            ->execute([':today' => $today, ':id' => $id]);

        // Idempotence : un seul log par jour et par plante
        $stmt = $this->pdo->prepare('
            SELECT id FROM watering_logs WHERE seedling_id = :id AND watered_at = :today LIMIT 1
        ');
        $stmt->execute([':id' => $id, ':today' => $today]);
        if (!$stmt->fetch()) {
            $this->pdo
                ->prepare('INSERT INTO watering_logs (seedling_id, watered_at) VALUES (:id, :today)')
                ->execute([':id' => $id, ':today' => $today]);
        }
    }

    /** Retire un log d'arrosage si besoin (erreur utilisateur). */
    public function unwater(int $logId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM watering_logs WHERE id = :id');
        $stmt->execute([':id' => $logId]);

        // Recalcule last_watered_at
        $stmt = $this->pdo->prepare('
            UPDATE seedlings
            SET last_watered_at = (SELECT MAX(watered_at) FROM watering_logs WHERE seedling_id = seedlings.id)
            WHERE id = (SELECT seedling_id FROM watering_logs WHERE id = :id2 LIMIT 1)
        ');
        $stmt->execute([':id2' => $logId]);
    }

    /** Historique d'arrosage d'une plante, 120 derniers jours par défaut. */
    public function wateringHistory(int $seedlingId, int $days = 120): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, watered_at FROM watering_logs
            WHERE seedling_id = :id AND watered_at >= date(:since, \'-\' || :days || \' days\')
            ORDER BY watered_at DESC
        ');
        $stmt->execute([':id' => $seedlingId, ':days' => $days, ':since' => date('Y-m-d')]);
        return $stmt->fetchAll();
    }

    /** Calendrier des arrosages : date -> présent/accumulation. */
    public function wateringCalendar(int $seedlingId, int $days = 42): array
    {
        $stmt = $this->pdo->prepare('SELECT watered_at, COUNT(*) as cnt FROM watering_logs WHERE seedling_id = :id GROUP BY watered_at');
        $stmt->execute([':id' => $seedlingId]);
        $logs = [];
        foreach ($stmt->fetchAll() as $row) {
            $logs[$row['watered_at']] = (int) $row['cnt'];
        }

        $cal = [];
        $today = new \DateTimeImmutable();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $today->modify("-{$i} days")->format('Y-m-d');
            $cal[$d] = [
                'date' => $d,
                'count' => $logs[$d] ?? 0,
                'isToday' => $d === $today->format('Y-m-d'),
            ];
        }
        return $cal;
    }

    public function stats(int $seedlingId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM watering_logs WHERE seedling_id = :id');
        $stmt->execute([':id' => $seedlingId]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare('
            SELECT COUNT(DISTINCT watered_at) as days,
                   MAX(watered_at) as last,
                   MIN(watered_at) as first
            FROM watering_logs WHERE seedling_id = :id
        ');
        $stmt->execute([':id' => $seedlingId]);
        $row = $stmt->fetch();

        return [
            'total' => $total,
            'days' => (int) ($row['days'] ?? 0),
            'first' => $row['first'] ?? null,
            'last' => $row['last'] ?? null,
        ];
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM seedlings WHERE id = :id')->execute([':id' => $id]);
    }

    /** Streak d'arrosage jours consécutifs jusqu'à aujourd'hui. */
    public function streak(int $seedlingId): int
    {
        $history = $this->wateringHistory($seedlingId, 365);
        $days = array_column($history, 'watered_at');
        sort($days, SORT_STRING);
        $unique = array_unique($days);
        rsort($unique, SORT_STRING);

        $streak = 0;
        $cursor = new \DateTimeImmutable();
        foreach ($unique as $d) {
            if ($d === $cursor->format('Y-m-d')) {
                $streak++;
                $cursor = $cursor->modify('-1 day');
            } else {
                break;
            }
        }
        return $streak;
    }
}
