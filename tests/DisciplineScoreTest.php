<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\DisciplineRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_score_');
unlink($tmp);
$pdo = Database::get($tmp);
$repo = new DisciplineRepository($pdo);

$score = $repo->score();
assert($score['score'] === 0, 'score 0 when no habits');
assert($score['rate'] === 0, 'rate 0 when no habits');
assert($score['streakFactor'] === 0, 'streakFactor 0 when no habits');

// 3 habits, 0 reached → rate 0, streak score 0
$pompes = $repo->addHabit('corps', 'Pompes', 20, 'reps', 5);
$squats = $repo->addHabit('corps', 'Squats', 30, 'reps', 10);
$wimhof = $repo->addHabit('mental', 'Wim Hof', 1, 'sessions', 1);
$score = $repo->score();
assert($score['score'] === 0, 'score 0 when none reached');

// 1/3 reached → rate 33, streak score 0
$repo->logToday($pompes, 20);
$score = $repo->score();
assert($score['rate'] === 33, 'rate rounded 33');
assert($score['streakFactor'] === 0, 'streak factor still 0');
assert($score['score'] === 23, 'score = round(33*0.7) = 23');

// All reached today, streaks = 1 each
$repo->logToday($squats, 30);
$repo->logToday($wimhof, 1);
$score = $repo->score();
assert($score['rate'] === 100, 'rate 100');
assert($score['avgStreak'] === 1, 'avg streak 1');
assert($score['streakFactor'] === 3, 'streak factor round(1/30*100) = 3');
assert($score['score'] === 71, 'score round(100*0.7 + 3*0.3) = 71');

// Streak of 30 on one habit should cap streakFactor at 100
// Long streak cap
for ($i = 1; $i <= 30; $i++) {
    $repo->log($pompes, date('Y-m-d', strtotime("-{$i} days")), 20);
}
$score = $repo->score();
assert($score['streakFactor'] <= 100, 'streakFactor plafonne');

// Historique 7j
$repo->setLog($pompes, date('Y-m-d'), 0);
$repo->log($pompes, date('Y-m-d', strtotime('-1 day')), 20);
$history = $repo->weekHistoryFor($pompes);
assert(count($history) === 7, '7 jours d\'historique');
assert($history[6]['date'] === date('Y-m-d'), 'dernier jour = aujourd\'hui');
assert($history[5]['reached'] === true, 'hier atteint');
assert($history[6]['reached'] === false, 'aujourd\'hui non atteint apres reset');

// Nettoyage

unlink($tmp);
echo "Score tests OK\n";
