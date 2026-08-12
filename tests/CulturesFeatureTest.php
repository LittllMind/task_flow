<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\SeedlingRepository;
use TaskFlow\VarietyRepository;

$tmp = tempnam(sys_get_temp_dir(), 'tf_cult_');
unlink($tmp);
$pdo = Database::get($tmp);

// Instantiation triggers schema creation (varieties, periods, etc. via VarietyRepository)
$varietyRepo = new VarietyRepository($pdo);
$seedlings = new SeedlingRepository($pdo);

// Schema should have been created by repos
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assert(in_array('varieties', $tables), 'varieties table exists');
assert(in_array('variety_periods', $tables), 'variety_periods table exists');

// Seed idempotent via run-seeds
$runner = require __DIR__ . '/../scripts/seed-varieties.php';
if (is_callable($runner)) {
    $runner($pdo);
}

$countV = $pdo->query('SELECT COUNT(*) FROM varieties')->fetchColumn();
assert($countV >= 6, "At least 6 varieties seeded, got $countV");

$countP = $pdo->query('SELECT COUNT(*) FROM variety_periods')->fetchColumn();
assert($countP >= 40, "At least 40 periods seeded, got $countP");

// Navet discontinuous sow
$navetId = $pdo->query("SELECT id FROM varieties WHERE species_name = 'Navet'")->fetchColumn();
assert($navetId !== false, 'Navet variety exists');
$periods = $varietyRepo->allPeriods((int)$navetId);
$sowMonths = [];
foreach ($periods as $p) {
    if ($p['period_type'] !== 'sow') continue;
    $s = (int)$p['start_month'];
    $e = (int)$p['end_month'];
    do { $sowMonths[] = $s; if ($s === $e) break; $s = $s === 12 ? 1 : $s + 1; } while (true);
}
$sowMonths = array_unique($sowMonths);
foreach ([2,3,4,7,8,9] as $m) {
    assert(in_array($m, $sowMonths, true), "Navet sow includes month $m");
}

// Mache wrap-around harvest (Sept → Mars)
$macheId = $pdo->query("SELECT id FROM varieties WHERE species_name = 'Mache'")->fetchColumn();
assert($macheId !== false, 'Mache variety exists');
$machePeriods = $varietyRepo->allPeriods((int)$macheId);
$harvest = null;
foreach ($machePeriods as $p) {
    if ($p['period_type'] === 'harvest') { $harvest = $p; break; }
}
assert($harvest !== null, 'Mache has harvest period');
assert((int)$harvest['start_month'] === 9 && (int)$harvest['end_month'] === 3, 'Mache harvest wraps Sep→Mar');

// CRUD with variety join
$figId = $pdo->query("SELECT id FROM varieties WHERE species_name = 'Figuier'")->fetchColumn();
$sid = $seedlings->create([
    'name' => '__test_culture__',
    'variety_id' => $figId,
    'variety' => '',
    'quantity' => 2,
    'seeded_at' => '2026-08-12',
    'repotted_at' => null,
    'location' => 'banc',
    'origin' => 'graines test',
    'note' => 'note test',
]);
$found = $seedlings->findById($sid);
assert($found !== null, 'Created seedling found');
assert($found['species_name'] === 'Figuier', 'Variety join OK');

$seedlings->update($sid, array_merge($found, ['name' => '__test_culture_updated__']));
$updated = $seedlings->findById($sid);
assert($updated['name'] === '__test_culture_updated__', 'Update OK');

$seedlings->delete($sid);
assert($seedlings->findById($sid) === null, 'Delete OK');

unlink($tmp);
echo "Cultures OK\n";
