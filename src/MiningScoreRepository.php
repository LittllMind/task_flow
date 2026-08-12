<?php
declare(strict_types=1);

namespace TaskFlow;

use PDO;

/**
 * Gestion du score Mining Deck + streak + logs d'actions.
 * Skip = neutre (pas de malus) — la carte repart au fond.
 */
final class MiningScoreRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->init();
    }

    private function init(): void
    {
        // Table principale (1 ligne)
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS mining_scores (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            score INTEGER DEFAULT 0,
            streak INTEGER DEFAULT 0,
            best_streak INTEGER DEFAULT 0,
            total_harvested INTEGER DEFAULT 0,
            last_harvested INTEGER DEFAULT 0,
            last_session_date TEXT
        )');

        // Log d'actions (pour stats par jour, anti-spam skip, etc.)
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS mining_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            action TEXT NOT NULL CHECK (action IN ("harvest","skip","destroy")),
            points INTEGER DEFAULT 0,
            log_date TEXT DEFAULT CURRENT_DATE,
            log_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        // Seed la ligne initiale
        $stmt = $this->pdo->query('SELECT id FROM mining_scores WHERE id = 1');
        if (!$stmt->fetch()) {
            $this->pdo->prepare('INSERT INTO mining_scores (id) VALUES (1)')->execute();
        }
    }

    /**
     * Enregistre une action et met à jour le score/streak.
     * - harvest: +10, +5 si en retard, +50 si premier du jour
     * - skip: 0 (neutre)
     * - destroy: -5
     */
    public function recordAction(int $taskId, string $action, bool $isOverdue = false, bool $isFirstHarvestOfDay = false): int
    {
        $row = $this->get();
        $points = 0;
        $today = date('Y-m-d');
        $streak = (int) $row['streak'];
        $lastDate = $row['last_session_date'] ?? null;

        switch ($action) {
            case 'harvest':
                $points = 10;
                if ($isOverdue) $points += 5;
                if ($isFirstHarvestOfDay) {
                    $points += 50;
                    // Streak
                    if ($lastDate) {
                        $lastDt = new \DateTimeImmutable($lastDate);
                        $todayDt = new \DateTimeImmutable($today);
                        $deltaDays = $lastDt->diff($todayDt)->days;
                        if ($deltaDays === 0) {
                            // déjà logué aujourd'hui
                        } elseif ($deltaDays === 1) {
                            $streak += 1;
                        } else {
                            $streak = 1; // reset
                        }
                    } else {
                        $streak = 1;
                    }
                }
                break;
            case 'skip':
                // Neutre — pas de malus
                break;
            case 'destroy':
                $points = -5;
                break;
        }

        // Mise à jour
        $this->pdo->prepare('UPDATE mining_scores SET
            score = score + :points,
            streak = :streak,
            total_harvested = total_harvested + :harvested,
            last_harvested = CASE WHEN :action2 = "harvest" THEN :taskId ELSE last_harvested END,
            best_streak = CASE WHEN :streak > best_streak THEN :streak ELSE best_streak END,
            last_session_date = :today
            WHERE id = 1')->execute([
            ':points' => $points,
            ':streak' => $streak,
            ':harvested' => ($action === 'harvest' ? 1 : 0),
            ':action2' => $action,
            ':taskId' => $taskId,
            ':today' => $today,
        ]);

        // Log
        $this->pdo->prepare('INSERT INTO mining_logs (task_id, action, points, log_date) VALUES (?, ?, ?, ?)')
            ->execute([$taskId, $action, $points, $today]);

        return $points;
    }

    public function get(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM mining_scores WHERE id = 1');
        return $stmt->fetch() ?: [
            'score' => 0,
            'streak' => 0,
            'best_streak' => 0,
            'total_harvested' => 0,
        ];
    }

    /** Compte les actions skip sur une même tâche aujourd'hui */
    public function skipCountForTask(int $taskId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM mining_logs WHERE task_id = ? AND action = "skip" AND log_date = ?');
        $stmt->execute([$taskId, date('Y-m-d')]);
        return (int) $stmt->fetchColumn();
    }

    /** Total d'actions aujourd'hui (harvest ou skip) pour stats quotidiennes */
    public function dailyStats(): array
    {
        $stmt = $this->pdo->prepare('SELECT action, COUNT(*) as cnt FROM mining_logs WHERE log_date = ? GROUP BY action');
        $stmt->execute([date('Y-m-d')]);
        $rows = $stmt->fetchAll();
        $stats = ['harvest' => 0, 'skip' => 0, 'destroy' => 0];
        foreach ($rows as $r) {
            $stats[$r['action']] = (int) $r['cnt'];
        }
        return $stats;
    }

    /** Vérifie s'il y a eu au moins 1 harvest aujourd'hui */
    public function hasMiningHarvestToday(): bool
    {
        $stats = $this->dailyStats();
        return ($stats['harvest'] ?? 0) > 0;
    }

    /** Vérifie s'il y a eu au moins 1 habit discipline loguée aujourd'hui (via jointure sur discipline_logs) */
    public function hasOtherHabitActivityToday(): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM discipline_logs WHERE log_date = ? LIMIT 1");
        $stmt->execute([date('Y-m-d')]);
        return (bool) $stmt->fetchColumn();
    }

    /** Ajoute un bonus de points (pour combo Mining+Discipline) */
    public function addBonus(int $points, string $reason): void
    {
        $this->pdo->prepare("UPDATE mining_scores SET score = score + ? WHERE id = 1")
            ->execute([$points]);
        // Log le bonus comme un harvest spécial
        $this->pdo->prepare('INSERT INTO mining_logs (task_id, action, points, log_date) VALUES (0, ?, ?, ?)')
            ->execute(['harvest', $points, date('Y-m-d')]);
    }
}