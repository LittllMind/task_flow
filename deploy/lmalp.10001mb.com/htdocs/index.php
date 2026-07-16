<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

$dbPath = __DIR__ . '/taskflow.db';
if (!file_exists($dbPath)) {
    $dbPath = __DIR__ . '/taskflow.sqlite';
}
$needsInit = !file_exists($dbPath);
$repo = new TaskRepository(Database::get($dbPath));

if ($needsInit) {
    $pdo = Database::get($dbPath);
    $pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER CHECK(priority BETWEEN 1 AND 3), due_at TEXT, done INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
}

$categories = $repo->categories();

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? '';
    $subcategory = $_POST['subcategory'] ?? '';
    $priority = (int) ($_POST['priority'] ?? 2);
    $due = $_POST['due_at'] ?? '';
    if ($title !== '' && isset($categories[$category])) {
        $repo->create([
            'title' => $title,
            'category' => $category,
            'subcategory' => in_array($subcategory, $categories[$category], true) ? $subcategory : null,
            'priority' => max(1, min(3, $priority)),
            'due_at' => $due,
        ]);
    }
    header('Location: .');
    exit;
}

if ($action === 'done' && isset($_GET['id'])) {
    $repo->markDone((int) $_GET['id']);
    header('Location: .');
    exit;
}

$filter = $_GET['category'] ?? null;
$tasks = $repo->findIncomplete($filter);
$priorities = [1 => 'Haute', 2 => 'Moyenne', 3 => 'Basse'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <header>
      <h1>TaskFlow</h1>
      <span class="count"><?= count($tasks) ?> tâche<?= count($tasks) > 1 ? 's' : '' ?></span>
    </header>

    <nav class="filters">
      <a href="." class="<?= $filter === null ? 'active' : '' ?>">Toutes</a>
      <?php foreach ($categories as $cat => $_): ?>
        <a href="?category=<?= urlencode($cat) ?>" class="<?= $filter === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="stack">
      <?php if (empty($tasks)): ?>
        <p class="empty">Aucune tâche en cours.</p>
      <?php endif; ?>
      <?php foreach ($tasks as $task): ?>
        <article class="task-card priority-<?= (int) $task['priority'] ?>">
          <div class="task-content">
            <h2><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="task-meta">
              <span class="chip"><?= htmlspecialchars($task['category']) ?><?= $task['subcategory'] ? ' · ' . htmlspecialchars($task['subcategory']) : '' ?></span>
              <?php if ($task['due_at']): ?><span class="chip due-chip"><?= htmlspecialchars($task['due_at']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="task-actions">
            <a class="done-btn" href="?action=done&id=<?= (int) $task['id'] ?>" title="Terminer">✓</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <button class="add-btn" id="openModal">+</button>

  <div class="overlay" id="modal">
    <form method="post" action="?action=create" class="modal">
      <input type="hidden" name="action" value="create">
      <h3>Nouvelle tâche</h3>
      <input type="text" name="title" placeholder="Que dois-tu faire ?" required autofocus>
      <select name="category" id="category" required>
        <option value="">Catégorie</option>
        <?php foreach ($categories as $cat => $_): ?>
          <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="subcategory" id="subcategory">
        <option value="">Sous-catégorie (optionnel)</option>
      </select>
      <div class="row">
        <select name="priority">
          <?php foreach ($priorities as $val => $label): ?>
            <option value="<?= $val ?>" <?= $val === 2 ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="due_at">
      </div>
      <button type="submit">Ajouter</button>
      <a href="#" class="close-modal" id="closeModal">Annuler</a>
    </form>
  </div>

  <script>
    const subcats = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="app.js"></script>
</body>
</html>
