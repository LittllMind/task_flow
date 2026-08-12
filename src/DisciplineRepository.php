<?php

declare(strict_types=1);

namespace TaskFlow;

use PDO;

final class DisciplineRepository
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
            CREATE TABLE IF NOT EXISTS discipline_habits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK(type IN (\'corps\', \'mental\')),
                title TEXT NOT NULL,
                target_value INTEGER NOT NULL CHECK(target_value > 0),
                unit TEXT NOT NULL CHECK(unit IN (\'reps\', \'sessions\')),
                step INTEGER NOT NULL DEFAULT 1 CHECK(step > 0),
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS discipline_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                habit_id INTEGER NOT NULL,
                log_date TEXT NOT NULL,
                value INTEGER NOT NULL CHECK(value >= 0),
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (habit_id) REFERENCES discipline_habits(id) ON DELETE CASCADE,
                UNIQUE (habit_id, log_date)
            )
        ');
    }

    public function addHabit(string $type, string $title, int $targetValue, string $unit = 'reps', int $step = 1): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO discipline_habits (type, title, target_value, unit, step) VALUES (:type, :title, :target_value, :unit, :step)');
        $stmt->execute([
            ':type' => $type,
            ':title' => trim($title),
            ':target_value' => $targetValue,
            ':unit' => $unit,
            ':step' => $step,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listHabits(): array
    {
        $habits = $this->pdo->query('SELECT * FROM discipline_habits ORDER BY type, title')->fetchAll();
        $today = date('Y-m-d');
        foreach ($habits as $k => $habit) {
            $progress = $this->progressFor((int) $habit['id'], $today);
            $habits[$k]['today_current'] = $progress['current'];
            $habits[$k]['today_percent'] = $progress['percent'];
            $habits[$k]['today_reached'] = $progress['reached'];
        }
        return $habits;
    }

    public function findHabit(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM discipline_habits WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateHabit(int $id, array $data): void
    {
        $allowed = ['type' => true, 'title' => true, 'target_value' => true, 'unit' => true, 'step' => true];
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        if (empty($fields)) {
            return;
        }
        $params[':id'] = $id;
        $stmt = $this->pdo->prepare('UPDATE discipline_habits SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function deleteHabit(int $id): void
    {
        $this->pdo->prepare('DELETE FROM discipline_habits WHERE id = :id')->execute([':id' => $id]);
    }

    public function logToday(int $habitId, int $value): void
    {
        $this->log($habitId, date('Y-m-d'), $value);
    }

    public function log(int $habitId, string $date, int $value): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO discipline_logs (habit_id, log_date, value)
            VALUES (:habit_id, :log_date, :value)
            ON CONFLICT(habit_id, log_date)
            DO UPDATE SET value = value + excluded.value
        ");
        $stmt->execute([':habit_id' => $habitId, ':log_date' => $date, ':value' => max(0, $value)]);
    }

    public function setLog(int $habitId, string $date, int $value): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO discipline_logs (habit_id, log_date, value)
            VALUES (:habit_id, :log_date, :value)
            ON CONFLICT(habit_id, log_date)
            DO UPDATE SET value = excluded.value
        ");
        $stmt->execute([':habit_id' => $habitId, ':log_date' => $date, ':value' => max(0, $value)]);
    }

    public function logsFor(int $habitId, ?string $date = null): array
    {
        if ($date) {
            $stmt = $this->pdo->prepare('SELECT * FROM discipline_logs WHERE habit_id = :habit_id AND log_date = :date ORDER BY log_date');
            $stmt->execute([':habit_id' => $habitId, ':date' => $date]);
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->prepare('SELECT * FROM discipline_logs WHERE habit_id = :habit_id ORDER BY log_date DESC');
        $stmt->execute([':habit_id' => $habitId]);
        return $stmt->fetchAll();
    }

    public function progressFor(int $habitId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $logs = $this->logsFor($habitId, $date);
        $current = array_sum(array_column($logs, 'value'));
        $habit = $this->findHabit($habitId);
        if (!$habit) {
            return ['current' => $current, 'target' => 0, 'percent' => 0, 'reached' => false];
        }
        $target = (int) $habit['target_value'];
        $percent = $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0;
        return [
            'current' => $current,
            'target' => $target,
            'percent' => $percent,
            'reached' => $current >= $target,
        ];
    }

    public function streakFor(int $habitId): int
    {
        $habit = $this->findHabit($habitId);
        if (!$habit) {
            return 0;
        }
        $target = (int) $habit['target_value'];
        $today = date('Y-m-d');
        $current = $this->progressFor($habitId, $today)['current'];

        $streak = $current >= $target ? 1 : 0;
        $offset = 1;
        while (true) {
            $date = date('Y-m-d', strtotime("-{$offset} day"));
            $logs = $this->logsFor($habitId, $date);
            $value = array_sum(array_column($logs, 'value'));
            if ($value >= $target) {
                $streak++;
                $offset++;
            } else {
                break;
            }
        }
        return $streak;
    }

    public function stats(): array
    {
        $today = date('Y-m-d');
        $habits = $this->listHabits();
        $total = count($habits);
        $reached = count(array_filter($habits, fn($h) => (bool) $h['today_reached']));
        $corps = count(array_filter($habits, fn($h) => $h['type'] === 'corps'));
        $mental = $total - $corps;
        return [
            'total' => $total,
            'reached' => $reached,
            'corps' => $corps,
            'mental' => $mental,
            'rate' => $total > 0 ? (int) round(($reached / $total) * 100) : 0,
        ];
    }

    public function score(): array
    {
        $habits = $this->listHabits();
        $today = date('Y-m-d');
        $total = count($habits);
        $reached = 0;
        $streakDetails = [];
        foreach ($habits as $habit) {
            $progress = $this->progressFor((int) $habit['id'], $today);
            if ($progress['reached']) {
                $reached++;
            }
            $streakDetails[] = [
                'id' => (int) $habit['id'],
                'title' => $habit['title'],
                'streak' => $this->streakFor((int) $habit['id']),
            ];
        }

        $rate = $total > 0 ? (int) round(($reached / $total) * 100) : 0;
        $avgStreak = $total > 0 ? array_sum(array_column($streakDetails, 'streak')) / $total : 0;
        $streakFactor = min(100, (int) round(($avgStreak / 30) * 100));

        $score = (int) round(($rate * 0.7) + ($streakFactor * 0.3));

        return [
            'score' => $score,
            'rate' => $rate,
            'streakFactor' => $streakFactor,
            'avgStreak' => $avgStreak,
            'streakDetails' => $streakDetails,
        ];
    }

    public function weekHistoryFor(int $habitId): array
    {
        $habit = $this->findHabit($habitId);
        $target = $habit ? (int) $habit['target_value'] : 0;
        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $logs = $this->logsFor($habitId, $date);
            $value = array_sum(array_column($logs, 'value'));
            $out[] = [
                'date' => $date,
                'day' => date('D', strtotime($date)),
                'value' => $value,
                'reached' => $target > 0 && $value >= $target,
            ];
        }
        return $out;
    }

    /**
     * Heatmap des 365 derniers jours.
     * Retourne un tableau groupé par semaine, chaque jour contenant date + level 0-4.
     */
    public function heatmapFor(int $habitId): array
    {
        $habit = $this->findHabit($habitId);
        $target = $habit ? (int) $habit['target_value'] : 0;

        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-364 days');

        // Récupère les logs sur 365 jours
        $stmt = $this->pdo->prepare('
            SELECT log_date, SUM(value) as total
            FROM discipline_logs
            WHERE habit_id = :habit_id AND log_date >= :start AND log_date <= :end
            GROUP BY log_date
        ');
        $stmt->execute([
            ':habit_id' => $habitId,
            ':start' => $start->format('Y-m-d'),
            ':end' => $today->format('Y-m-d'),
        ]);
        $logs = [];
        foreach ($stmt->fetchAll() as $row) {
            $logs[$row['log_date']] = (int) $row['total'];
        }

        // Groupage par semaine (dimanche comme premier jour)
        $weeks = [];
        $currentWeek = [];
        for ($i = 0; $i < 365; $i++) {
            $date = $start->modify("+{$i} days");
            $dateStr = $date->format('Y-m-d');
            $weekday = (int) $date->format('w'); // 0 = dimanche
            $value = $logs[$dateStr] ?? 0;
            $percent = $target > 0 ? min(100, (int) round(($value / $target) * 100)) : ($value > 0 ? 100 : 0);

            $level = 0;
            if ($value > 0) {
                if ($percent >= 100) $level = 4;
                elseif ($percent >= 75) $level = 3;
                elseif ($percent >= 50) $level = 2;
                else $level = 1;
            }

            $currentWeek[$dateStr] = [
                'date' => $dateStr,
                'day' => (int) $date->format('d'),
                'weekday' => $weekday,
                'level' => $level,
                'value' => $value,
                'percent' => $percent,
            ];

            if ($weekday === 6 || $i === 364) { // samedi ou fin de période
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }

        return $weeks;
    }

    /**
     * Score de régularité glissant sur N jours.
     * Pour chaque jour, "atteint" = sum(value) >= target. Score = % d'atteinte.
     */
    public function regularityScore(int $habitId, int $days = 30): int
    {
        $habit = $this->findHabit($habitId);
        $target = $habit ? (int) $habit['target_value'] : 0;
        if ($target <= 0) return 0;

        $stmt = $this->pdo->prepare('
            SELECT log_date, SUM(value) as total
            FROM discipline_logs
            WHERE habit_id = :habit_id AND log_date >= date(:start, \'-' . $days . ' days\')
            GROUP BY log_date
        ');
        // On passe le paramètre via une chaîne assemblée avant pour compatibilité sqlite
        $start = date('Y-m-d', strtotime("-{$days} days"));
        $stmt = $this->pdo->prepare('
            SELECT log_date, SUM(value) as total
            FROM discipline_logs
            WHERE habit_id = :habit_id AND log_date >= :start
            GROUP BY log_date
        ');
        $stmt->execute([':habit_id' => $habitId, ':start' => $start]);
        $rows = $stmt->fetchAll();

        $reached = 0;
        foreach ($rows as $row) {
            if ((int) $row['total'] >= $target) {
                $reached++;
            }
        }

        return (int) round(($reached / $days) * 100);
    }
}
