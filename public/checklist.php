<?php

declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\ChecklistRepository;
use TaskFlow\Database;

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
$pdo = Database::get($dbPath);

$repo = new ChecklistRepository($pdo);

$action = $_POST['action'] ?? '';
if (in_array($action, ['create', 'rename', 'delete_checklist', 'add_item', 'toggle_item', 'delete_item', 'update_item_label'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $newId = $repo->create($title);
            header('Location: checklist.php?checklist_id=' . (int) $newId);
            exit;
        }
    } elseif ($action === 'rename' && isset($_POST['checklist_id'])) {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $repo->rename((int) $_POST['checklist_id'], $title);
        }
    } elseif ($action === 'delete_checklist' && isset($_POST['checklist_id'])) {
        $repo->deleteChecklist((int) $_POST['checklist_id']);
    } elseif ($action === 'add_item' && isset($_POST['checklist_id'])) {
        $cid = (int) $_POST['checklist_id'];
        $label = trim($_POST['label'] ?? '');
        if ($cid && $label !== '') {
            $repo->addItem($cid, $label);
        }
    } elseif ($action === 'toggle_item' && isset($_POST['item_id'])) {
        $repo->toggleItem((int) $_POST['item_id']);
    } elseif ($action === 'delete_item' && isset($_POST['item_id'])) {
        $repo->deleteItem((int) $_POST['item_id']);
    } elseif ($action === 'update_item_label' && isset($_POST['item_id'])) {
        $label = trim($_POST['label'] ?? '');
        if ($label !== '') {
            $repo->updateItemLabel((int) $_POST['item_id'], $label);
        }
    }
    $redirect = 'checklist.php';
    if (isset($_POST['checklist_id'])) {
        $redirect .= '?checklist_id=' . (int) $_POST['checklist_id'];
    }
    header('Location: ' . $redirect);
    exit;
}

$selectedId = isset($_GET['checklist_id']) ? (int) $_GET['checklist_id'] : 0;
$all = $repo->listAll();
$active = $selectedId > 0 ? $repo->findById($selectedId) : ($all ? $all[0] : null);
$activeId = $active ? (int) $active['id'] : 0;
$items = $activeId > 0 ? $repo->itemsFor($activeId) : [];
$stats = $activeId > 0 ? $repo->statsFor($activeId) : ['total' => 0, 'done' => 0];
$pct = $stats['total'] > 0 ? round($stats['done'] / $stats['total'] * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow &mdash; Checklists</title>
  <link rel="stylesheet" href="style.css?v=4">
  <link rel="icon" href="favicon.php">
</head>
<body>
  <div class="container">
    <header>
      <h1>TaskFlow</h1>
      <a class="admin-link header-icon" href="discipline.php" title="Discipline">
        <span class="header-icon-symbol">💪</span>
        <span class="header-icon-label">Discipline</span>
      </a>
      <a class="admin-link header-icon active" href="checklist.php" title="Checklists">
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

    <?php if (empty($all)): ?>
      <section class="empty-create">
        <p class="empty">Aucune checklist. Cr&eacute;es-en une premi&egrave;re.</p>
        <form method="post" class="checklist-new-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="create">
          <input type="text" name="title" placeholder="Nom de la checklist..." required autofocus>
          <button type="submit" class="btn btn-primary">Cr&eacute;er</button>
        </form>
      </section>
    <?php else: ?>
      <div class="cl-layout">
        <nav class="cl-sidebar">
          <div class="cl-sidebar-header">
            <h2>Mes listes</h2>
            <form method="post" class="cl-inline-create">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="create">
              <input type="text" name="title" placeholder="Nouvelle..." required>
              <button type="submit" title="Cr&eacute;er">+</button>
            </form>
          </div>
          <ul class="cl-list">
            <?php foreach ($all as $cl): ?>
              <li class="<?= (int) $cl['id'] === $activeId ? 'active' : '' ?>">
                <a href="?checklist_id=<?= (int) $cl['id'] ?>">
                  <span class="cl-name"><?= htmlspecialchars($cl['title']) ?></span>
                  <?php $s = $repo->statsFor((int) $cl['id']); ?>
                  <span class="cl-count"><?= $s['done'] ?>/<?= $s['total'] ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>

        <?php if ($active): ?>
          <main class="cl-content">
            <section class="checklist-meta">
              <form method="post" class="checklist-rename">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="checklist_id" value="<?= $activeId ?>">
                <h2 class="checklist-title-display" onclick="this.style.display='none';this.nextElementSibling.style.display='flex'"><?= htmlspecialchars($active['title']) ?> <small>✎</small></h2>
                <div class="checklist-title-edit" style="display:none">
                  <input type="text" name="title" value="<?= htmlspecialchars($active['title']) ?>" required>
                  <button type="submit">OK</button>
                  <button type="button" onclick="this.parentElement.style.display='none';this.parentElement.previousElementSibling.style.display='block'">✕</button>
                </div>
              </form>
              <div class="checklist-submeta">
                <span class="checklist-progress"><?= $stats['done'] ?>/<?= $stats['total'] ?></span>
                <form method="post" class="checklist-delete-form" onsubmit="return confirm('Supprimer cette checklist ?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="delete_checklist">
                  <input type="hidden" name="checklist_id" value="<?= $activeId ?>">
                  <button type="submit" class="btn-text-danger" title="Supprimer la checklist">Supprimer</button>
                </form>
              </div>
              <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
            </section>

            <ul class="checklist">
              <?php foreach ($items as $item): ?>
                <li class="checklist-item <?= (int) $item['done'] ? 'done' : '' ?>">
                  <form method="post" class="checklist-toggle">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="toggle_item">
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="checklist_id" value="<?= $activeId ?>">
                    <button type="submit" class="checkbox" aria-label="Cocher" aria-pressed="<?= (int) $item['done'] ? 'true' : 'false' ?>">
                      <?= (int) $item['done'] ? '✓' : '' ?>
                    </button>
                  </form>
                  <span class="checklist-label <?= (int) $item['done'] ? 'done' : '' ?>"><?= htmlspecialchars($item['label']) ?></span>
                  <form method="post" class="checklist-delete" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="checklist_id" value="<?= $activeId ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">✕</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>

            <form method="post" class="checklist-form">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="add_item">
              <input type="hidden" name="checklist_id" value="<?= $activeId ?>">
              <input type="text" name="label" placeholder="Nouvelle action" required autofocus>
              <button type="submit">Ajouter</button>
            </form>
          </main>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
