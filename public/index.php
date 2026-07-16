<?php
declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\Database;
use TaskFlow\TaskRepository;

session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

function csrfOk(string $token): bool
{
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

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
$needsInit = !file_exists($dbPath);
$repo = new TaskRepository(Database::get($dbPath));
if ($needsInit) {
    Database::get($dbPath)->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER CHECK(priority BETWEEN 1 AND 3), due_at TEXT, done INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
}

$categories = $repo->categories();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

function normalizeTask(array $post): array
{
    global $categories;
    $title = trim($post['title'] ?? '');
    $category = $post['category'] ?? '';
    $subcategory = $post['subcategory'] ?? '';
    $priority = (int) ($post['priority'] ?? 2);
    return [
        'title' => $title,
        'category' => $category,
        'subcategory' => isset($categories[$category]) && in_array($subcategory, $categories[$category], true) ? $subcategory : null,
        'priority' => max(1, min(3, $priority)),
        'due_at' => $post['due_at'] ?? null,
    ];
}

if (in_array($action, ['create', 'update', 'delete', 'done'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
    if ($action === 'create') {
        $repo->create(normalizeTask($_POST));
    } elseif ($action === 'update' && $id) {
        $repo->update($id, normalizeTask($_POST));
    } elseif ($action === 'done' && $id) {
        $repo->update($id, ['done' => 1]);
    } elseif ($action === 'delete' && $id) {
        $repo->delete($id);
    }
    header('Location: .');
    exit;
}

$filter = $_GET['category'] ?? null;
$tasks = $repo->findIncomplete($filter);
$priorities = [1 => 'Haute', 2 => 'Moyenne', 3 => 'Basse'];
$editTask = null;
if (($action === 'edit') && isset($_GET['id'])) {
    $editTask = $repo->find((int) $_GET['id']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%2322d3ee'/%3E%3Ctext x='50' y='68' font-size='55' text-anchor='middle' fill='%230f172a'%3E✓%3C/text%3E%3C/svg%3E">
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
            <form method="post" class="action-form">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="done">
              <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
              <button type="submit" class="btn btn-success" title="Terminer">✓</button>
            </form>
            <a class="btn btn-primary" href="?action=edit&id=<?= (int) $task['id'] ?>" title="Éditer">✎</a>
            <form method="post" class="action-form" onsubmit="return confirm('Supprimer cette tâche ?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
              <button type="submit" class="btn btn-danger" title="Supprimer">✕</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <button class="add-btn" id="openModal">+</button>

  <div class="overlay" id="modal">
    <form method="post" action="?action=create" class="modal" id="taskForm">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="id" id="formId" value="">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <h3 id="modalTitle">Nouvelle tâche</h3>
      <input type="text" name="title" id="title" placeholder="Que dois-tu faire ?" required autofocus>
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
        <select name="priority" id="priority">
          <?php foreach ($priorities as $val => $label): ?>
            <option value="<?= $val ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="due_at" id="due_at">
      </div>
      <button type="submit" id="submitBtn">Ajouter</button>
      <a href="#" class="close-modal" id="closeModal">Annuler</a>
    </form>
  </div>

  <?php if ($editTask): ?>
  <script>
    window.editTask = <?= json_encode($editTask, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    window.editSubcats = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <?php endif; ?>

  <script>
    const subcats = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="app.js"></script>
</body>
</html>
