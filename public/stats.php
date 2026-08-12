<?php
declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\Database;
use TaskFlow\TaskRepository;

$configPath = __DIR__ . '/src/Config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../src/Config.php';
}
require $configPath;

requirePin();

$dbPath = __DIR__ . '/taskflow.db';
if (!file_exists($dbPath)) {
    $dbPath = __DIR__ . '/taskflow.sqlite';
    if (!file_exists($dbPath)) {
        $dbPath = __DIR__ . '/../data/taskflow.db';
    }
    if (!file_exists($dbPath)) {
        $dbPath = __DIR__ . '/../data/taskflow.sqlite';
    }
}
$repo = new TaskRepository(Database::get($dbPath));

$stats = $repo->stats();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow — Stats</title>
  <link rel="stylesheet" href="style.css?v=4">
  <link rel="icon" href="favicon.php">
</head>
<body>
<?php require __DIR__ . '/includes/navbar.php'; ?>
  <div class="container">
    <!-- navbar -->
<div class="stats-grid">
      <div class="stat-card">
        <span class="stat-value"><?= (int) $stats['total'] ?></span>
        <span class="stat-label">À faire</span>
      </div>
      <div class="stat-card overdue">
        <span class="stat-value"><?= (int) $stats['overdue'] ?></span>
        <span class="stat-label">En retard</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= (int) $stats['done'] ?></span>
        <span class="stat-label">Terminées</span>
      </div>
    </div>

    <a class="back-link" href="checklist.php">← Retour aux checklists</a>
    <a class="back-link" href="admin.php">Gérer les catégories</a>
  </div>
</body>
</html>
