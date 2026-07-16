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
require __DIR__ . '/../src/Config.php';
requirePin();
$repo = new TaskRepository(Database::get($dbPath));

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=taskflow.csv');
    $tasks = $repo->findIncomplete();
    $tasks = array_merge($tasks, $repo->findDone());
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'title', 'category', 'subcategory', 'priority', 'due_at', 'done', 'done_at', 'created_at']);
    foreach ($tasks as $t) {
        fputcsv($out, [$t['id'], $t['title'], $t['category'], $t['subcategory'], $t['priority'], $t['due_at'], $t['done'], $t['done_at'], $t['created_at']]);
    }
    fclose($out);
    exit;
}

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
    header('Location: admin.php');
    exit;
}

$categories = $repo->categories();

function cleanEmpty(array $cats): array
{
    foreach ($cats as $cat => &$subs) {
        $subs = array_values(array_filter($subs, fn(string $s): bool => $s !== ''));
        if (empty($subs)) {
            unset($cats[$cat]);
        }
    }
    return $cats;
}

$categories = cleanEmpty($categories);
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
      <a class="logout-link" href="?export_csv=1">Export CSV</a>
      <a class="logout-link" href="logout.php">Déconnexion</a>
    </header>

    <div class="admin-section">
      <h2>Changer le PIN</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="change_pin">
        <input type="password" name="pin" inputmode="numeric" maxlength="4" placeholder="Nouveau PIN 4 chiffres" required>
        <input type="password" name="pin_confirm" inputmode="numeric" maxlength="4" placeholder="Confirmer" required>
        <button type="submit">Changer</button>
      </form>
      <?php if (!empty($_SESSION['pin_msg'])): ?><p class="pin-msg"><?= htmlspecialchars($_SESSION['pin_msg']); unset($_SESSION['pin_msg']); ?></p><?php endif; ?>
    </div>
<div class="admin-section">
      <h2>Ajouter une catégorie</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add_category">
        <input type="text" name="category" placeholder="Nouvelle catégorie" required>
        <button type="submit">Ajouter</button>
      </form>
    </div>

    <div class="admin-section">
      <h2>Ajouter une sous-catégorie</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add_subcategory">
        <select name="category" required>
          <option value="">Catégorie</option>
          <?php foreach (array_keys($categories) as $cat): ?>
            <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="subcategory" placeholder="Sous-catégorie" required>
        <button type="submit">Ajouter</button>
      </form>
    </div>

    <div class="admin-section">
      <h2>Liste actuelle</h2>
      <?php if (empty($categories)): ?>
        <p class="empty">Aucune catégorie.</p>
      <?php endif; ?>
      <?php foreach ($categories as $cat => $subs): ?>
        <div class="category-group">
          <div class="category-header">
            <span><?= htmlspecialchars($cat) ?></span>
            <form method="post" class="action-form" onsubmit="return confirm('Supprimer cette catégorie et toutes ses sous-catégories ?')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="remove_category">
              <input type="hidden" name="category" value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-danger">✕</button>
            </form>
          </div>
          <ul class="sub-list">
            <?php foreach ($subs as $sub): ?>
              <li>
                <span><?= htmlspecialchars($sub) ?></span>
                <form method="post" class="action-form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="remove_subcategory">
                  <input type="hidden" name="category" value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="subcategory" value="<?= htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn btn-danger">✕</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>

    <a class="back-link" href="index.php">← Retour aux tâches</a>
  </div>
</body>
</html>
