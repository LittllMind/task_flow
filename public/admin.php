<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;

require __DIR__ . '/src/Config.php';
requirePin();

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
if (isset($_GET['export_csv'])) {
    $repo = new TaskRepository(Database::get($dbPath));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=taskflow.csv');
    $tasks = array_merge($repo->findIncomplete(), $repo->findDone());
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'title', 'category', 'subcategory', 'priority', 'due_at', 'done', 'done_at', 'created_at']);
    foreach ($tasks as $t) {
        fputcsv($out, [$t['id'], $t['title'], $t['category'], $t['subcategory'], $t['priority'], $t['due_at'], $t['done'], $t['done_at'], $t['created_at']]);
    }
    fclose($out);
    exit;
}

$repo = new TaskRepository(Database::get($dbPath));

$action = $_POST['action'] ?? '';
if (in_array($action, ['add_category', 'remove_category', 'add_subcategory', 'remove_subcategory', 'change_pin'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
    if ($action === 'add_category' && !empty($_POST['category'])) {
        $repo->addCategory($_POST['category']);
    } elseif ($action === 'remove_category' && !empty($_POST['category'])) {
        $repo->removeCategory($_POST['category']);
    } elseif ($action === 'add_subcategory' && !empty($_POST['category']) && !empty($_POST['subcategory'])) {
        $repo->addSubcategory($_POST['category'], $_POST['subcategory']);
    } elseif ($action === 'remove_subcategory' && !empty($_POST['category']) && isset($_POST['subcategory'])) {
        $repo->removeSubcategory($_POST['category'], $_POST['subcategory']);
    } elseif ($action === 'change_pin' && !empty($_POST['pin']) && $_POST['pin'] === ($_POST['pin_confirm'] ?? '')) {
        if (setPin($_POST['pin'])) {
            $_SESSION['pin_msg'] = 'PIN mis à jour.';
        } else {
            $_SESSION['pin_msg'] = 'PIN invalide.';
        }
    }
    header('Location: admin.php' . (!empty($_POST['selected']) ? '?cat=' . urlencode($_POST['selected']) : ''));
    exit;
}

$categories = $repo->categories();
foreach ($categories as $cat => &$subs) {
    $subs = array_values(array_filter($subs, fn(string $s): bool => $s !== ''));
}
$selected = $_GET['cat'] ?? '';
$selected = isset($categories[$selected]) ? $selected : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow — Catégories</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" href="favicon.php">
</head>
<body>
  <div class="container">
    <header>
      <h1>Catégories</h1>
      <div class="header-actions">
        <a class="admin-link" href="?export_csv=1" title="Export CSV">↓</a>
        <a class="admin-link" href="logout.php" title="Déconnexion">✕</a>
      </div>
    </header>

    <div class="admin-section">
      <h2>Changer le PIN</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="change_pin">
        <div class="row">
          <input type="password" name="pin" inputmode="numeric" maxlength="4" placeholder="Nouveau PIN 4 chiffres" required>
          <input type="password" name="pin_confirm" inputmode="numeric" maxlength="4" placeholder="Confirmer" required>
        </div>
        <button type="submit">Changer</button>
      </form>
      <?php if (!empty($_SESSION['pin_msg'])): ?><p class="pin-msg"><?= htmlspecialchars($_SESSION['pin_msg']); unset($_SESSION['pin_msg']); ?></p><?php endif; ?>
    </div>

    <div class="admin-section">
      <h2>Sélectionner une catégorie</h2>
      <div class="category-selector">
        <?php foreach (array_keys($categories) as $cat): ?>
          <div class="cat-chip <?= $selected === $cat ? 'active' : '' ?>">
            <a href="?cat=<?= urlencode($cat) ?>"><?= htmlspecialchars($cat) ?></a>
            <form method="post" class="action-form" onsubmit="return confirm('Supprimer cette catégorie ?')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="remove_category">
              <input type="hidden" name="category" value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="selected" value="<?= htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-danger" title="Supprimer">×</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" class="admin-form" id="addForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="selected" id="formSelected" value="<?= htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" id="formAction" value="<?= $selected === '' ? 'add_category' : 'add_subcategory' ?>">
        <?php if ($selected !== ''): ?>
          <input type="hidden" name="category" value="<?= htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <input type="text" id="addInput" name="<?= $selected === '' ? 'category' : 'subcategory' ?>" class="add-input" placeholder="<?= $selected === '' ? 'Nouvelle catégorie' : 'Nouvelle sous-catégorie' ?>" required>
        <button type="submit" id="addBtn"><?= $selected === '' ? 'Ajouter la catégorie' : 'Ajouter la sous-catégorie' ?></button>
      </form>
    </div>

    <div class="admin-section">
      <h2><?= $selected === '' ? 'Catégories' : htmlspecialchars($selected) ?></h2>
      <?php if ($selected === ''): ?>
        <p class="empty">Sélectionne une catégorie ci-dessus pour voir ses sous-catégories.</p>
      <?php else: ?>
        <a href="admin.php" class="back-link">← Toutes les catégories</a>
        <ul class="sub-list">
          <?php if (empty($categories[$selected])): ?>
            <li class="empty">Aucune sous-catégorie.</li>
          <?php else: ?>
            <?php foreach ($categories[$selected] as $sub): ?>
              <li>
                <span><?= htmlspecialchars($sub) ?></span>
                <form method="post" class="action-form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="remove_subcategory">
                  <input type="hidden" name="category" value="<?= htmlspecialchars($selected, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="subcategory" value="<?= htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn btn-danger" title="Supprimer">✕</button>
                </form>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>

    <a class="back-link" href="index.php">← Retour aux tâches</a>
  </div>
</body>
</html>
