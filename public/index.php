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
$pdo = Database::get($dbPath);
$repo = new TaskRepository($pdo);

if ($needsInit) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER CHECK(priority BETWEEN 1 AND 3), due_at TEXT, done INTEGER DEFAULT 0, done_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
}

// Migrate existing DB to add done_at if missing
try {
    $pdo->exec('ALTER TABLE tasks ADD COLUMN done_at TEXT');
} catch (\PDOException $e) {
    // ignore if column exists
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

function faviconColor(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return str_contains($host, '10001mb.com') ? '#f43f5e' : '#22d3ee';
}

if (in_array($action, ['create', 'update', 'delete', 'done', 'restore'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $repo->markDone($id);
    } elseif ($action === 'restore' && $id) {
        $repo->restore($id);
    } elseif ($action === 'delete' && $id) {
        $repo->delete($id);
    }
    header('Location: .');
    exit;
}

$view = $_GET['view'] ?? 'todo';
if ($view !== 'done') {
    $view = 'todo';
}
$filter = $_GET['category'] ?? null;
$tasks = $view === 'todo' ? $repo->findIncomplete($filter) : $repo->findDone();
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
  <link rel="icon" href="favicon.php">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <header>
      <h1>TaskFlow</h1>
      <span class="count"><?= count($tasks) ?> tâche<?= count($tasks) > 1 ? 's' : '' ?></span>
      <a class="admin-link" href="admin.php" title="Gérer les catégories">⚙</a>
    </header>

    <nav class="filters">
      <a href="." class="<?= $filter === null ? 'active' : '' ?>">Toutes</a>
      <?php foreach ($categories as $cat => $_): ?>
        <a href="?category=<?= urlencode($cat) ?><?= $view === 'done' ? '&view=done' : '' ?>" class="<?= $filter === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="view-toggle">
      <a href="." class="<?= $view === 'todo' ? 'active' : '' ?>">À faire</a>
      <a href="?view=done" class="<?= $view === 'done' ? 'active' : '' ?>">Terminées</a>
    </div>

    <div class="stack">
      <?php if (empty($tasks)): ?>
        <p class="empty"><?= $view === 'todo' ? 'Aucune tâche en cours.' : 'Aucune tâche terminée.' ?></p>
      <?php endif; ?>
      <?php foreach ($tasks as $task): ?>
        <article class="task-card priority-<?= (int) $task['priority'] ?> <?= $view === 'done' ? 'done' : '' ?>">
          <div class="task-content">
            <h2><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="task-meta">
              <span class="chip"><?= htmlspecialchars($task['category']) ?><?= $task['subcategory'] ? ' · ' . htmlspecialchars($task['subcategory']) : '' ?></span>
              <?php if ($task['due_at']): ?><span class="chip due-chip"><?= htmlspecialchars($task['due_at']) ?></span><?php endif; ?>
              <?php if ($view === 'done' && $task['done_at']): ?><span class="chip">Terminée <?= htmlspecialchars(date('d/m/Y', strtotime($task['done_at']))) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="task-actions">
            <?php if ($view === 'todo'): ?>
              <form method="post" class="action-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="done">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="btn btn-success" title="Terminer">✓</button>
              </form>
              <a class="btn btn-primary" href="?action=edit&id=<?= (int) $task['id'] ?>" title="Éditer">✎</a>
            <?php endif; ?>
            <form method="post" class="action-form" onsubmit="return confirm('Supprimer cette tâche ?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
              <button type="submit" class="btn btn-danger" title="Supprimer">✕</button>
            </form>
            <?php if ($view === 'done'): ?>
              <form method="post" class="action-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="btn btn-primary" title="Remettre en cours">↺</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($view === 'todo'): ?>
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
  <?php endif; ?>

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
