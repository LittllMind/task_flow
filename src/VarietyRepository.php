<?php

declare(strict_types=1);

namespace TaskFlow;

use PDO;

/**
 * Référentiel de variétés avec périodes culturales.
 */
final class VarietyRepository
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
            CREATE TABLE IF NOT EXISTS varieties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                species_name TEXT NOT NULL,
                cultivar_name TEXT,
                cycle TEXT,
                germination_days_min INTEGER,
                germination_days_max INTEGER,
                spacing_cm_min INTEGER,
                spacing_cm_max INTEGER,
                harvest_days_min INTEGER,
                harvest_days_max INTEGER,
                essential_advice TEXT,
                propagation_notes TEXT,
                seed_production TEXT,
                warnings TEXT,
                UNIQUE(species_name, cultivar_name)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS variety_periods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                variety_id INTEGER NOT NULL,
                period_type TEXT NOT NULL,
                start_month INTEGER NOT NULL,
                end_month INTEGER NOT NULL,
                note TEXT,
                FOREIGN KEY (variety_id) REFERENCES varieties(id) ON DELETE CASCADE
            )
        ');

        // Colonnes legacy / additives
        $cols = $this->pdo->query("PRAGMA table_info(varieties)")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['cultivar_name', 'cycle', 'germination_days_min', 'germination_days_max', 'spacing_cm_min', 'spacing_cm_max', 'harvest_days_min', 'harvest_days_max', 'essential_advice', 'propagation_notes', 'seed_production', 'warnings'] as $col) {
            if (!in_array($col, $cols, true)) {
                $this->pdo->exec("ALTER TABLE varieties ADD COLUMN {$col} TEXT");
            }
        }
    }

    public function speciesUniqueKey(string $species, ?string $cultivar): string
    {
        return $species . '|' . ($cultivar ?? '');
    }

    public function upsert(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO varieties (species_name, cultivar_name, cycle, germination_days_min, germination_days_max,
                spacing_cm_min, spacing_cm_max, harvest_days_min, harvest_days_max, essential_advice,
                propagation_notes, seed_production, warnings)
            VALUES (:species, :cultivar, :cycle, :germination_min, :germination_max, :spacing_min, :spacing_max,
                :harvest_min, :harvest_max, :essential, :propagation, :seed_production, :warnings)
            ON CONFLICT(species_name, cultivar_name) DO UPDATE SET
                cycle = excluded.cycle,
                germination_days_min = excluded.germination_days_min,
                germination_days_max = excluded.germination_days_max,
                spacing_cm_min = excluded.spacing_cm_min,
                spacing_cm_max = excluded.spacing_cm_max,
                harvest_days_min = excluded.harvest_days_min,
                harvest_days_max = excluded.harvest_days_max,
                essential_advice = excluded.essential_advice,
                propagation_notes = excluded.propagation_notes,
                seed_production = excluded.seed_production,
                warnings = excluded.warnings
        ');
        $stmt->execute([
            ':species' => trim($data['species_name']),
            ':cultivar' => $data['cultivar_name'] ? trim($data['cultivar_name']) : null,
            ':cycle' => $data['cycle'] ? trim($data['cycle']) : null,
            ':germination_min' => $data['germination_days_min'] ?? null,
            ':germination_max' => $data['germination_days_max'] ?? null,
            ':spacing_min' => $data['spacing_cm_min'] ?? null,
            ':spacing_max' => $data['spacing_cm_max'] ?? null,
            ':harvest_min' => $data['harvest_days_min'] ?? null,
            ':harvest_max' => $data['harvest_days_max'] ?? null,
            ':essential' => $data['essential_advice'] ? trim($data['essential_advice']) : null,
            ':propagation' => $data['propagation_notes'] ? trim($data['propagation_notes']) : null,
            ':seed_production' => $data['seed_production'] ? trim($data['seed_production']) : null,
            ':warnings' => $data['warnings'] ? trim($data['warnings']) : null,
        ]);
        $lastId = (int) $this->pdo->lastInsertId();
        if ($lastId) {
            // lastInsertId peut être pollué par d'autres INSERTs (ex: variety_periods) sur la même connexion
            $rowById = $this->findByNames($data['species_name'], $data['cultivar_name'] ?? null);
            return $rowById ? (int) $rowById['id'] : $lastId;
        }
        $row = $this->findByNames($data['species_name'], $data['cultivar_name'] ?? null);
        return $row ? (int) $row['id'] : 0;
    }

    public function setPeriods(int $varietyId, string $periodType, array $ranges): void
    {
        $this->pdo->prepare('DELETE FROM variety_periods WHERE variety_id = :id AND period_type = :type')
            ->execute([':id' => $varietyId, ':type' => $periodType]);
        $stmt = $this->pdo->prepare('
            INSERT INTO variety_periods (variety_id, period_type, start_month, end_month, note)
            VALUES (:id, :type, :start, :end, :note)
        ');
        foreach ($ranges as $range) {
            $stmt->execute([
                ':id' => $varietyId,
                ':type' => $periodType,
                ':start' => $range['start_month'] ?? 0,
                ':end' => $range['end_month'] ?? 0,
                ':note' => $range['note'] ?? null,
            ]);
        }
    }

    public function clearPeriods(int $varietyId): void
    {
        $this->pdo->prepare('DELETE FROM variety_periods WHERE variety_id = :id')
            ->execute([':id' => $varietyId]);
    }

    public function allPeriods(int $varietyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM variety_periods WHERE variety_id = :id ORDER BY period_type, start_month');
        $stmt->execute([':id' => $varietyId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM varieties WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByNames(string $species, ?string $cultivar): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM varieties WHERE species_name = :species AND cultivar_name IS :cultivar');
        $stmt->execute([':species' => trim($species), ':cultivar' => $cultivar ? trim($cultivar) : null]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listAll(): array
    {
        return $this->pdo->query('SELECT * FROM varieties ORDER BY species_name, cultivar_name ASC')->fetchAll();
    }
}
