<?php
declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\Database;
use TaskFlow\DisciplineRepository;
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
$disciplineRepo = new DisciplineRepository($pdo);
$disciplineScore = $disciplineRepo->score()['score'];

if ($needsInit) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL, subcategory TEXT, priority INTEGER CHECK(priority BETWEEN 1 AND 3), due_at TEXT, done INTEGER DEFAULT 0, done_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
}

try {
    $pdo->exec('ALTER TABLE tasks ADD COLUMN done_at TEXT');
} catch (\PDOException $e) {
}

$categories = $repo->categories();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$filter = $_GET['category'] ?? null;

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
        $newId = $repo->create(normalizeTask($_POST));
        $blockedId = isset($_POST['blocked_id']) ? (int) $_POST['blocked_id'] : 0;
        if ($newId && $blockedId) {
            try {
                $repo->addDependency($newId, $blockedId);
            } catch (\RuntimeException $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
    } elseif ($action === 'update' && $id) {
        $repo->update($id, normalizeTask($_POST));
    } elseif ($action === 'done' && $id) {
        try {
            $repo->markDone($id);
        } catch (\RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    } elseif ($action === 'restore' && $id) {
        $repo->restore($id);
    } elseif ($action === 'delete' && $id) {
        $repo->delete($id);
    }
    header('Location: .');
    exit;
}

if (in_array($action, ['add_dependency', 'remove_dependency'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
    $blockerId = isset($_POST['blocker_id']) ? (int) $_POST['blocker_id'] : null;
    if ($action === 'add_dependency' && $id && $blockerId) {
        try {
            $repo->addDependency($blockerId, $id);
        } catch (\RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    } elseif ($action === 'remove_dependency' && $id && $blockerId) {
        $repo->removeDependency($blockerId, $id);
    }
    header('Location: .');
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$view = $_GET['view'] ?? 'todo';
if ($view !== 'done') {
    $view = 'todo';
}

$editTask = null;
$editBlockers = [];
$candidatesModal = [];
if ($action === 'edit' && isset($_GET['id'])) {
    $editTask = $repo->find((int) $_GET['id']);
    if ($editTask) {
        $editBlockers = $repo->blockersFor((int) $editTask['id']);
        $blockedIds = array_column($editBlockers, 'id');
        foreach ($repo->findIncomplete() as $t) {
            if ((int) $t['id'] === (int) $editTask['id'] || in_array((int) $t['id'], $blockedIds, true)) {
                continue;
            }
            $candidatesModal[] = $t;
        }
    }
}

function buildDecks(array $tasks, array $blockersMap): array
{
    $assigned = [];
    $decks = [];
    foreach ($tasks as $t) {
        $id = (int) $t['id'];
        if (!empty($blockersMap[$id])) {
            continue;
        }
        $chain = [$t];
        $current = $t;
        while (true) {
            $next = null;
            foreach ($tasks as $c) {
                foreach ($blockersMap[(int) $c['id']] as $b) {
                    if ((int) $b['id'] === (int) $current['id']) {
                        $next = $c;
                        break 2;
                    }
                }
            }
            if (!$next || isset($assigned[(int) $next['id']])) {
                break;
            }
            $chain[] = $next;
            $assigned[(int) $next['id']] = true;
            $current = $next;
        }
        $decks[] = $chain;
        $assigned[$id] = true;
    }
    foreach ($tasks as $t) {
        if (!isset($assigned[(int) $t['id']])) {
            $decks[] = [$t];
        }
    }
    return $decks;
}

if ($view === 'todo') {
    $tasksRaw = $repo->findIncomplete($filter);
    $blockersMap = [];
    $tasksById = [];
    foreach ($tasksRaw as $t) {
        $id = (int) $t['id'];
        $tasksById[$id] = $t;
        $blockersMap[$id] = $repo->blockersFor($id);
    }
    $decks = buildDecks($tasksRaw, $blockersMap);

    // candidates per task: any incomplete task not in same deck and not already a blocker
    $candidatesByTask = [];
    foreach ($decks as $deck) {
        $deckIds = array_column($deck, 'id');
        foreach ($deck as $task) {
            $tid = (int) $task['id'];
            $existing = array_column($blockersMap[$tid], 'id');
            $candidatesByTask[$tid] = [];
            foreach ($tasksById as $cid => $c) {
                if ($cid === $tid || in_array($cid, $deckIds, true) || in_array($cid, $existing, true)) {
                    continue;
                }
                $candidatesByTask[$tid][] = $c;
            }
        }
    }
} else {
    $tasksRaw = $repo->findDone();
    $decks = [];
    foreach ($tasksRaw as $t) {
        $decks[] = [$t];
    }
    $blockersMap = [];
    $candidatesByTask = [];
}

$priorities = [1 => 'Haute', 2 => 'Moyenne', 3 => 'Basse'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow</title>
  <link rel="stylesheet" href="style.css?v=4">
  <link rel="icon" href="favicon.php">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <header>
      <h1>TaskFlow</h1>
      <span class="count"><?= count($tasksRaw) ?> tâche<?= count($tasksRaw) > 1 ? 's' : '' ?></span>
      <a class="admin-link header-icon" href="discipline.php" title="Discipline">
        <span class="header-icon-symbol">💪</span>
        <span class="header-icon-label"><?= (int) $disciplineScore ?></span>
      </a>
      <a class="admin-link header-icon" href="checklist.php" title="Checklists">
        <span class="header-icon-symbol">☑</span>
        <span class="header-icon-label">Checklists</span>
      </a>
      <a class="admin-link header-icon" href="mining.php" title="Mining Deck">
        <span class="header-icon-symbol">⛏️</span>
        <span class="header-icon-label">Mining</span>
      </a>
      <a class="admin-link header-icon" href="seedlings.php" title="Semis">
        <span class="header-icon-symbol">🌱</span>
        <span class="header-icon-label">Semis</span>
      </a>
      <a class="admin-link header-icon" href="admin.php" title="Catégories">
        <span class="header-icon-symbol">⚙</span>
        <span class="header-icon-label">Admin</span>
      </a>
      <a class="admin-link header-icon" href="logout.php" title="Quitter">
        <span class="header-icon-symbol">✕</span>
        <span class="header-icon-label">Quitter</span>
      </a>
    </header>

    <?php if ($error): ?>
      <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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

    <?php if ($view === 'done'): ?>
      <div class="stack">
        <?php if (empty($decks)): ?>
          <p class="empty">Aucune tâche terminée.</p>
        <?php endif; ?>
        <?php foreach ($decks as $deck): ?>
          <?php $task = $deck[0]; ?>
          <article class="task-card done priority-<?= (int) $task['priority'] ?>">
            <div class="task-content">
              <h2><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h2>
              <div class="task-meta">
                <span class="chip"><?= htmlspecialchars($task['category']) ?><?= $task['subcategory'] ? ' · ' . htmlspecialchars($task['subcategory']) : '' ?></span>
                <?php if ($task['done_at']): ?><span class="chip">Terminée <?= htmlspecialchars(date('d/m/Y', strtotime($task['done_at']))) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="task-actions">
              <form method="post" class="action-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="btn btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette tâche ?');">✕</button>
              </form>
              <form method="post" class="action-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button type="submit" class="btn btn-primary" title="Remettre en cours">↺</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>

      <div class="deck-list">
        <?php if (empty($decks)): ?>
          <p class="empty">Aucune tâche en cours.</p>
        <?php endif; ?>

        <?php foreach ($decks as $deck): ?>
          <?php
          $deck = array_reverse($deck);
          $count = count($deck);
          ?>
          <div class="deck" style="--count: <?= $count ?>; --active-index: <?= (int) ($count - 1) ?>">
            <?php foreach ($deck as $i => $task): ?>
              <?php
              $isTopMost = $i === 0;
              $isBottomMost = $i === $count - 1;
              $blockers = $blockersMap[(int) $task['id']] ?? [];
              $tid = (int) $task['id'];
              ?>
              <article class="deck-card priority-<?= (int) $task['priority'] ?> <?= $isBottomMost ? 'active' : '' ?>" data-task-id="<?= $tid ?>" style="--index: <?= (int) $i ?>">
                <div class="deck-card-inner">
                  <div class="deck-card-header">
                    <h2><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if (!$isBottomMost): ?>
                      <span class="deck-card-peek"><?= count($blockers) ?> prérequis</span>
                    <?php endif; ?>
                  </div>

                  <div class="deck-card-body">
                    <div class="task-meta">
                      <span class="chip"><?= htmlspecialchars($task['category']) ?><?= $task['subcategory'] ? ' · ' . htmlspecialchars($task['subcategory']) : '' ?></span>
                      <?php if ($task['due_at']): ?><span class="chip due-chip"><?= htmlspecialchars($task['due_at']) ?></span><?php endif; ?>
                      <?php if ($repo->isOverdue($tid)): ?><span class="chip overdue-chip">En retard</span><?php endif; ?>
                      <span class="chip"><?= $priorities[(int) $task['priority']] ?></span>
                    </div>

                    <?php if (!empty($blockers)): ?>
                      <div class="blocked-hint">
                        <span class="blocked-hint-label">Bloquée par :</span>
                        <?php foreach ($blockers as $b): ?>
                          <button type="button" class="blocked-hint-chip" data-blocker-id="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['title']) ?></button>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <div class="deck-actions">
                      <form method="post" class="action-form">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="done">
                        <input type="hidden" name="id" value="<?= $tid ?>">
                        <button type="submit" class="btn btn-success" title="Terminer">✓</button>
                      </form>
                      <a class="btn btn-primary" href="?action=edit&id=<?= $tid ?><?= $filter ? '&category=' . urlencode($filter) : '' ?>" title="Éditer">✎</a>
                      <button type="button" class="btn btn-sm menu-btn" data-id="<?= $tid ?>" title="Plus">⋯</button>
                      <div class="task-menu" id="menu-<?= $tid ?>">
                        <form method="post" class="action-form">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= $tid ?>">
                          <button type="submit" class="btn btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette tâche ?');">✕ Supprimer</button>
                        </form>
                        <button type="button" class="btn btn-primary blocker-add-toggle" title="Ajouter un bloqueur" data-blocked-id="<?= $tid ?>">⊕ Bloquer</button>
                      </div>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="add-btn" id="openModal" data-blocked-id="">+</button>

      <div class="overlay" id="modal">
        <form method="post" action="?action=create" class="modal" id="taskForm">
          <input type="hidden" name="action" id="formAction" value="create">
          <input type="hidden" name="id" id="formId" value="">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <h3 id="modalTitle">Nouvelle tâche</h3>

          <div id="blockedLinkSection" class="blocked-link-section">
            <div id="blockedLinkReadonly" class="blocked-link-readonly">
              <span class="blocked-link-label">Bloque :</span>
              <strong id="blockedLinkTitle"></strong>
            </div>
            <div id="blockedLinkSelectWrapper" class="blocked-link-select-wrapper">
              <span class="blocked-link-label">Sélectionne une tâche à bloquer :</span>
              <select id="blockedLinkSelect" name="blocked_id">
                <option value="">Aucune — tâche simple</option>
                <?php foreach ($tasksById as $cid => $c): ?>
                  <option value="<?= (int) $cid ?>"><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

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
        window.editBlockers = <?= json_encode(array_column($editBlockers, 'id'), JSON_UNESCAPED_UNICODE) ?>;
      </script>
    <?php endif; ?>

    <script>
      let subcats = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
      let tasksById = <?= json_encode($tasksById, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="app.js?v=4"></script>
  </div>
</body>
</html>
