<?php
declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\ChecklistRepository;
use TaskFlow\Database;
use TaskFlow\MiningScoreRepository;

session_start();
if (empty($_SESSION['mining_session_skips'])) {
    $_SESSION['mining_session_skips'] = [];
}

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
$checklistRepo = new ChecklistRepository($pdo);
$taskRepo = new ChecklistRepository($pdo);
$scoreRepo = new MiningScoreRepository($pdo);

// Actions POST
$action = $_POST['action'] ?? '';
if (in_array($action, ['done','delete','skip'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403); exit('Forbidden');
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $stack = $_SESSION['mining_stack'] ?? [];

    if ($action === 'done' && $id) {
        $checklistRepo->markItemDone($id);
        $firstHarvest = ($scoreRepo->get()['last_session_date'] ?? '') !== date('Y-m-d');
        $scoreRepo->recordAction($id, 'harvest', false, $firstHarvest);
        $_SESSION['mining_stack'] = array_values(array_diff($stack, [$id]));
    } elseif ($action === 'delete' && $id) {
        // Destroy : on supprime l'item de checklist (irréversible)
        $checklistRepo->deleteItem($id);
        $scoreRepo->recordAction($id, 'destroy');
        $_SESSION['mining_stack'] = array_values(array_diff($stack, [$id]));
    } elseif ($action === 'skip' && $id) {
        $scoreRepo->recordAction($id, 'skip');
        $stack = array_values(array_diff($stack, [$id]));
        array_unshift($stack, $id);
        $_SESSION['mining_stack'] = $stack;
        $_SESSION['mining_session_skips'][$id] = ($_SESSION['mining_session_skips'][$id] ?? 0) + 1;
    }
    header('Location: mining.php');
    exit;
}

// Rebuild stack from open checklist items
$items = $checklistRepo->findOpenItems();
$itemIds = array_column($items, 'id');
$stack = [];

if (!empty($_SESSION['mining_stack'])) {
    // Garder l'ordre existant, éliminer ceux cochés entre temps
    foreach ($_SESSION['mining_stack'] as $sid) {
        if (in_array($sid, $itemIds)) {
            $stack[] = $sid;
        }
    }
    // Ajouter les nouveaux items à la fin
    foreach ($itemIds as $iid) {
        if (!in_array($iid, $stack)) {
            $stack[] = $iid;
        }
    }
} else {
    // Initiale : ordre aléatoire pondéré pour varier les checklists
    usort($items, fn() => random_int(-1, 1));
    $stack = array_column($items, 'id');
}

$_SESSION['mining_stack'] = $stack;

$totalCards = count($stack);
$harvestedToday = $scoreRepo->dailyStats()['harvest'] ?? 0;
$score = $scoreRepo->get();
$streak = (int) $score['streak'];
$scoreValue = (int) $score['score'];
$skipMap = $_SESSION['mining_session_skips'] ?? [];

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow — Mining Deck</title>
  <link rel="stylesheet" href="style.css?v=4">
  <link rel="icon" href="favicon.php">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/includes/navbar.php'; ?>
  <div class="container mining-page">
    <!-- navbar -->
<?php if ($totalCards > 0): ?>
      <div class="mining-progress-bar">
        <div class="mining-progress-fill" style="width:<?= (int)(($harvestedToday / max($totalCards + $harvestedToday, 1)) * 100) ?>%" data-progress="<?= $harvestedToday ?>/<?= $totalCards + $harvestedToday ?>"></div>
      </div>
      <div class="mining-progress-label"><?= $harvestedToday ?> coch<?= $harvestedToday > 1 ? 'ées' : 'ée' ?> — <?= $totalCards ?> restante<?= $totalCards > 1 ? 's' : '' ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($stack)): ?>
      <div class="mining-empty">
        <div class="mining-empty-icon">&#x26CF;&#xFE0F;</div>
        <h2>La mine est vide !</h2>
        <p>Toutes les actions sont terminées. </p>
        <a href="checklist.php" class="btn btn-primary empty-cta">Retour aux checklists</a>
      </div>
    <?php else: ?>
      <div class="mining-stack">
        <?php
        $visible = array_slice($stack, -3);
        $totalVisible = count($visible);
        foreach ($visible as $c => $itemId):
          $item = $checklistRepo->findItemById($itemId);
          if (!$item) continue;
          $isTop = $c === $totalVisible - 1;
          $posClass = 'mining-pos-' . min($c, 5);
          $skipCount = $skipMap[(int)$item['id']] ?? 0;
        ?>
        <div class="mining-card <?= $posClass ?> <?= $isTop ? 'active' : '' ?>" data-item-id="<?= (int)$item['id'] ?>" data-skip-count="<?= $skipCount ?>">
          <div class="mining-card-inner" style="border-top-color:var(--accent)">
            <div class="mining-card-header">
              <h2><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></h2>
              <span class="priority-badge" style="background:var(--accent-soft);color:var(--accent)"><?= htmlspecialchars($item['checklist_title'] ?? '') ?></span>
            </div>
            <div class="mining-card-body">
              <div class="mining-meta">
                <span class="chip"><?= htmlspecialchars($item['checklist_title'] ?? '') ?></span>
              </div>
              <?php if ($skipCount > 0): ?>
                <span class="skip-badge">Remise en pile <?= $skipCount ?> fois</span>
              <?php endif; ?>
            </div>
            <?php if ($isTop): ?>
              <div class="mining-card-shine"></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="mining-actions">
        <?php $topId = (int) $stack[count($stack) - 1]; ?>

        <form method="post" class="mining-action mining-destroy">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $topId ?>">
          <button type="submit" title="Supprimer" onclick="return confirm('Supprimer cette action ?');"></button>
        </form>

        <form method="post" class="mining-action mining-skip">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="skip">
          <input type="hidden" name="id" value="<?= $topId ?>">
          <button type="submit" title="Remettre en pile">&#x23ED;</button>
        </form>

        <form method="post" class="mining-action mining-harvest">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="done">
          <input type="hidden" name="id" value="<?= $topId ?>">
          <button type="submit" title="Cocher (+10 pts)">&#x2713;</button>
        </form>
      </div>
      <div class="mining-hint" id="miningHint">← skip · → done · ↓ suppr</div>
    <?php endif; ?>
  </div>

  <script src="mining.js?v=4"></script>
</body>
</html>
