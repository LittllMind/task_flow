<?php
require __DIR__ . '/../vendor/autoload.php';
use TaskFlow\Database;
use TaskFlow\DisciplineRepository;
$pdo = Database::get('/tmp/tmpq2_4ut0d.db');
$repo = new DisciplineRepository($pdo);
$pompes = $repo->addHabit('corps', '20 Pompes', 20, 'reps', 5);
$respi = $repo->addHabit('mental', '20 respi gainées', 20, 'reps', 5);
assert($pompes > 0 && $respi > 0, 'habitudes créées');
$habits = $repo->listHabits();
$titles = array_column($habits, 'title');
assert(in_array('20 Pompes', $titles, true), '20 Pompes listé');
assert(in_array('20 respi gainées', $titles, true), '20 respi gainées listé');
echo "Defis OK
";
unlink('/tmp/tmpq2_4ut0d.db');
