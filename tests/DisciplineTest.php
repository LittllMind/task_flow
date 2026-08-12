<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\DisciplineRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_disc_');
unlink($tmp);
$pdo = Database::get($tmp);
$repo = new DisciplineRepository($pdo);

// Habits CRUD
$pompes = $repo->addHabit('corps', 'Pompes', 20, 'reps', 5);
$wimhof = $repo->addHabit('mental', 'Wim Hof', 1, 'sessions', 1);
assert(count($repo->listHabits()) === 2, 'two habits created');

// Default unit and step
$pushups = $repo->addHabit('corps', 'Respirations gainées', 20, 'reps');
$h = $repo->findHabit($pushups);
assert($h['unit'] === 'reps' && $h['step'] === 1, 'default unit and step');

// Progress before log
$progress = $repo->progressFor($pompes);
assert($progress['current'] === 0, 'current starts at 0');
assert($progress['target'] === 20, 'target kept');
assert($progress['percent'] === 0, 'percent 0');
assert($progress['reached'] === false, 'not reached');

// Log adds up
$repo->logToday($pompes, 5);
$repo->logToday($pompes, 10);
$progress = $repo->progressFor($pompes);
assert($progress['current'] === 15, 'current sums logs');
assert($progress['percent'] === 75, 'percent 75');

// Reach target
$repo->logToday($pompes, 5);
$progress = $repo->progressFor($pompes);
assert($progress['current'] === 20, 'current 20');
assert($progress['reached'] === true, 'reached');
assert($progress['percent'] === 100, 'percent capped at 100');

// Over-log percent cap
$repo->logToday($pompes, 100);
$progress = $repo->progressFor($pompes);
assert($progress['current'] === 120, 'current sums over target');
assert($progress['percent'] === 100, 'percent still capped');

// Session-based habit
assert($repo->progressFor($wimhof)['current'] === 0, 'session current 0');
$repo->logToday($wimhof, 1);
assert($repo->progressFor($wimhof)['reached'] === true, 'session reached');

// Streak calculation
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$dayBefore = date('Y-m-d', strtotime('-2 days'))
;
$repo->log($pompes, $yesterday, 20);
$repo->log($pompes, $dayBefore, 20);
assert($repo->streakFor($pompes) === 3, 'streak 3 days including today');

// Broken streak (today reached, yesterday missed, day before reached)
$another = $repo->addHabit('corps', 'Squats', 30, 'reps', 10);
$repo->log($another, $dayBefore, 30);
$repo->log($another, $today, 30);
assert($repo->streakFor($another) === 1, 'streak reset to today only');

// List with day state
$list = $repo->listHabits();
$state = array_values(array_filter($list, fn($x) => (int) $x['id'] === $pompes))[0];
assert($state['today_current'] === 120, 'list carries current');
assert($state['today_reached'] === true, 'list carries reached');

// Update habit
$repo->updateHabit($pompes, ['title' => 'Pompes gainées', 'target_value' => 25]);
$updated = $repo->findHabit($pompes);
assert($updated['title'] === 'Pompes gainées', 'title updated');
assert($updated['target_value'] === 25, 'target updated');

// Delete habit clears logs
$repo->deleteHabit($wimhof);
assert(count($repo->listHabits()) === 3, 'habit deleted');
assert(count($repo->logsFor($wimhof)) === 0, 'logs deleted');

unlink($tmp);
echo "Discipline tests OK\n";
