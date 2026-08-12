<?php

declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\Database;
use TaskFlow\DisciplineRepository;
use TaskFlow\SeedlingRepository;
use TaskFlow\VarietyRepository;

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
$seedRepo = new SeedlingRepository($pdo);
$varRepo = new VarietyRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$disciplineScore = $disciplineRepo->score()['score'];

$action = $_POST['action'] ?? '';
if (in_array($action, ['create', 'update', 'water', 'unwater', 'delete'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }

    $redirect = 'seedlings.php';
    if (isset($_GET['id'])) {
        $redirect .= '?id=' . (int) $_GET['id'];
    } elseif (isset($_POST['id'])) {
        $redirect .= '?id=' . (int) $_POST['id'];
    }

    if ($action === 'create') {
        $id = $seedRepo->create([
            'name' => $_POST['name'] ?? '',
            'variety_id' => $_POST['variety_id'] ?? null,
            'variety' => $_POST['variety'] ?? '',
            'quantity' => $_POST['quantity'] ?? 1,
            'seeded_at' => $_POST['seeded_at'] ?? null,
            'repotted_at' => $_POST['repotted_at'] ?? null,
            'location' => $_POST['location'] ?? '',
            'origin' => $_POST['origin'] ?? '',
            'note' => $_POST['note'] ?? '',
        ]);
        $redirect = 'seedlings.php?id=' . $id;
    } elseif ($action === 'update' && isset($_POST['id'])) {
        $seedRepo->update((int) $_POST['id'], [
            'name' => $_POST['name'] ?? '',
            'variety_id' => $_POST['variety_id'] ?? null,
            'variety' => $_POST['variety'] ?? '',
            'quantity' => $_POST['quantity'] ?? 1,
            'seeded_at' => $_POST['seeded_at'] ?? null,
            'repotted_at' => $_POST['repotted_at'] ?? null,
            'location' => $_POST['location'] ?? '',
            'origin' => $_POST['origin'] ?? '',
            'note' => $_POST['note'] ?? '',
        ]);
    } elseif ($action === 'water' && isset($_POST['id'])) {
        $date = $_POST['date'] ?? null;
        $seedRepo->water((int) $_POST['id'], is_string($date) ? $date : null);
    } elseif ($action === 'unwater' && isset($_POST['log_id'])) {
        $seedRepo->unwater((int) $_POST['log_id']);
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $seedRepo->delete((int) $_POST['id']);
        $redirect = 'seedlings.php';
    }

    header('Location: ' . $redirect);
    exit;
}

$seedlings = $seedRepo->listAll();
$detailId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detail = $detailId > 0 ? $seedRepo->findById($detailId) : null;
$isEdit = isset($_GET['edit']);
$editSeedling = ($isEdit && $detailId) ? $detail : null;
$isCreate = isset($_GET['create']);
$varieties = $varRepo->listAll();
$varietyPeriods = [];
if ($detail && !empty($detail['variety_id'])) {
    $varietyPeriods = $varRepo->allPeriods((int) $detail['variety_id']);
}

if ($detail) {
    $calendar = $seedRepo->wateringCalendar($detailId, 42);
    $history = $seedRepo->wateringHistory($detailId, 120);
    $stats = $seedRepo->stats($detailId);
    $streak = $seedRepo->streak($detailId);
}

function fmt(?string $d): string
{
    return $d ? (date_create($d) ? date_create($d)->format('d/m/Y') : '—') : '—';
}

function dayLetter(string $d): string
{
    $date = date_create($d);
    return $date ? $date->format('D')[0] : '';
}

function dayNum(string $d): string
{
    $date = date_create($d);
    return $date ? $date->format('j') : '';
}

function needsWater(?string $last): bool
{
    if (!$last) return true;
    $lastDt = date_create($last);
    if (!$lastDt) return true;
    $diff = (int) date_diff($lastDt, date_create())->format('%a');
    return $diff >= 2;
}

function labelForPeriod(string $type): string
{
    return [
        'sow' => 'Semis',
        'transplant' => 'Repiquage',
        'harvest' => 'Récolte',
        'bolt' => 'Montée',
        'flowering' => 'Floraison',
        'seed' => 'Graines',
        'cutting' => 'Bouture',
        'planting' => 'Plantation',
    ][$type] ?? $type;
}

function monthActive(array $periods, int $month, string $type): bool
{
    foreach ($periods as $p) {
        if ($p['period_type'] !== $type) continue;
        $start = (int) $p['start_month'];
        $end = (int) $p['end_month'];
        if ($start <= $end) {
            if ($month >= $start && $month <= $end) return true;
        } else {
            if ($month >= $start || $month <= $end) return true;
        }
    }
    return false;
}

function monthShort(int $m): string
{
    return ['J','F','M','A','M','J','J','A','S','O','N','D'][$m - 1] ?? '';
}

function varietyDisplay(array $s): string
{
    $parts = [];
    if (!empty($s['species_name'])) $parts[] = $s['species_name'];
    if (!empty($s['cultivar_name'])) $parts[] = $s['cultivar_name'];
    return implode(' · ', $parts);
}

function varietyPeriodsByYear(array $periods): array
{
    $lines = [];
    foreach ($periods as $p) {
        $lines[$p['period_type']][] = $p;
    }
    return $lines;
}

function formatMinMax(?int $min, ?int $max, string $unit): string
{
    if ($min === null && $max === null) return '—';
    if ($min === null) return 'jusqu\'à ' . $max . ' ' . $unit;
    if ($max === null) return 'dès ' . $min . ' ' . $unit;
    if ($min === $max) return $min . ' ' . $unit;
    return $min . '-' . $max . ' ' . $unit;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>TaskFlow — Cultures</title>
  <link rel="stylesheet" href="style.css?v=4">
  <link rel="icon" href="favicon.php">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <header>
      <h1>Cultures</h1>
      <span class="count"><?= count($seedlings) ?> <?= count($seedlings) > 1 ? 'cultures' : 'culture' ?></span>
      <a class="admin-link header-icon" href="checklist.php" title="Checklists">
        <span class="header-icon-symbol">‹</span>
        <span class="header-icon-label">Checklists</span>
      </a>
    </header>

    <?php if (!$detail): ?>
      <button class="add-btn" type="button" title="Ajouter une culture" onclick="location.href='?create=1'">+</button>

      <?php if (empty($seedlings)): ?>
        <p class="empty">Aucune culture enregistrée. Clique sur le + pour ajouter ta première culture.</p>
      <?php else: ?>
        <div class="seedling-grid">
          <?php foreach ($seedlings as $s): ?>
            <div class="seedling-card <?= needsWater($s['last_watered_at']) ? 'thirsty' : 'watered' ?>">

              <a class="card-body" href="?id=<?= (int) $s['id'] ?>">
                <div class="seedling-header"
                  <h2><?= htmlspecialchars($s['name']) ?></h2>
                  <span class="seedling-qty"><?= (int) $s['quantity'] ?> <?= (int) $s['quantity'] > 1 ? 'plants' : 'plant' ?></span>
                </div>
                <?php if ($s['variety'] || !empty($s['cultivar_name'])): ?>
                  <div class="seedling-variety"><?= htmlspecialchars($s['variety'] ?: $s['cultivar_name']) ?></div>
                <?php endif; ?>
                <ul class="seedling-meta">
                  <li>🌱 Semé le <strong><?= fmt($s['seeded_at']) ?></strong></li>
                  <?php if ($s['repotted_at']): ?>
                    <li>🪴 Rempoté le <strong><?= fmt($s['repotted_at']) ?></strong></li>
                  <?php endif; ?>
                  <li class="water-row">
                    <span>💧 Arrosé le <strong><?= fmt($s['last_watered_at']) ?></strong></span>
                  </li>
                </ul>
              </a>

              <div class="card-actions">
                <form method="post" class="quick-water-form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="water">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <input type="hidden" name="date" value="today">
                  <button type="submit" class="btn-water" title="Marquer arrosé aujourd'hui">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                    </svg>
                    Arroser
                  </button>
                </form>
                <a class="btn-detail" href="?id=<?= (int) $s['id'] ?>">Voir</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="seedling-detail">
        <div class="detail-header">
          <a class="back-link" href="seedlings.php">← Liste</a>
          <div class="detail-actions">
            <button class="menu-toggle" type="button" aria-label="Actions" onclick="this.nextElementSibling.classList.toggle('open')">⋮</button>
            <div class="action-menu">
              <a href="?id=<?= $detailId ?>&amp;edit=1" class="menu-edit">✎ Modifier</a>
              <form method="post" onsubmit="return confirm('Supprimer cette culture ?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $detailId ?>">
                <button type="submit" class="menu-delete">🗑 Supprimer</button>
              </form>
            </div>
          </div>
        </div>

        <?php if (!empty($detail['variety_id'])): ?>
          <section class="variety-sheet">
            <details class="variety-details" <?= !empty($_GET['variety']) ? 'open' : '' ?>>
              <summary>
                <span class="vs-title">Fiche variété</span>
                <span class="vs-subtitle"><?= htmlspecialchars(varietyDisplay($detail)) ?></span>
                <div class="culture-compact-meta">
                  <span class="cc-name"><?= htmlspecialchars($detail['name']) ?></span>
                  <span class="cc-qty"><?= (int) $detail['quantity'] ?> <?= (int) $detail['quantity'] > 1 ? 'plants' : 'plant' ?></span>
                  <?php if ($detail['location']): ?>
                    <span>📍 <?= htmlspecialchars($detail['location']) ?></span>
                  <?php endif; ?>
                  <span>🌱 Semé le <strong><?= fmt($detail['seeded_at']) ?></strong></span>
                  <?php if ($detail['repotted_at']): ?>
                    <span>🪴 Rempoté le <strong><?= fmt($detail['repotted_at']) ?></strong></span>
                  <?php endif; ?>
                  <span>💧 Dernier arrosage <strong><?= fmt($detail['last_watered_at']) ?></strong></span>
                  <?php if ($detail['origin']): ?>
                    <span>🧺 Origine <strong><?= htmlspecialchars($detail['origin']) ?></strong></span>
                  <?php endif; ?>
                </div>
              </summary>
              <div class="variety-body">
                <?php if ($detail['note']): ?>
                  <div class="culture-note"><?= htmlspecialchars($detail['note']) ?></div>
                <?php endif; ?>

                <div class="variety-calendar">
                  <div class="vc-header">
                    <span class="vc-label">Période</span>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                      <span class="vc-month" title="<?= $m ?>"><?= monthShort($m) ?></span>
                    <?php endfor; ?>
                  </div>
                  <?php foreach (varietyPeriodsByYear($varietyPeriods) as $type => $ranges): ?>
                    <div class="vc-row">
                      <span class="vc-label"><?= labelForPeriod($type) ?></span>
                      <?php for ($m = 1; $m <= 12; $m++): ?>
                        <span class="vc-month <?= monthActive($varietyPeriods, $m, $type) ? 'active' : '' ?>"></span>
                      <?php endfor; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="variety-quickfacts">
                  <?php if ($detail['germination_days_min'] || $detail['germination_days_max']): ?>
                    <div class="vqf-item">
                      <span class="vqf-label">Levée</span>
                      <span class="vqf-value"><?= formatMinMax($detail['germination_days_min'], $detail['germination_days_max'], 'jours') ?></span>
                    </div>
                  <?php endif; ?>
                  <?php if ($detail['spacing_cm_min'] || $detail['spacing_cm_max']): ?>
                    <div class="vqf-item"
                      <span class="vqf-label">Espacement</span>
                      <span class="vqf-value"><?= formatMinMax($detail['spacing_cm_min'], $detail['spacing_cm_max'], 'cm') ?></span>
                    </div>
                  <?php endif; ?>
                  <?php if ($detail['harvest_days_min'] || $detail['harvest_days_max']): ?>
                    <div class="vqf-item">
                      <span class="vqf-label">Récolte</span>
                      <span class="vqf-value"><?= formatMinMax($detail['harvest_days_min'], $detail['harvest_days_max'], 'jours') ?></span>
                    </div>
                  <?php endif; ?>
                  <?php if ($detail['cycle']): ?>
                    <div class="vqf-item">
                      <span class="vqf-label">Cycle</span>
                      <span class="vqf-value"><?= htmlspecialchars($detail['cycle']) ?></span>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if ($detail['essential_advice']): ?>
                  <div class="variety-advice">
                    <strong>À retenir</strong>
                    <p><?= htmlspecialchars($detail['essential_advice']) ?></p>
                  </div>
                <?php endif; ?>

                <details class="variety-more">
                  <summary>Production de graines &amp; details</summary>
                  <?php if ($detail['propagation_notes']): ?>
                    <div class="variety-more-section">
                      <strong>Reproduction</strong>
                      <p><?= htmlspecialchars($detail['propagation_notes']) ?></p>
                    </div>
                  <?php endif; ?>
                  <?php if ($detail['seed_production']): ?>
                    <div class="variety-more-section">
                      <strong>Production de graines</strong>
                      <p><?= htmlspecialchars($detail['seed_production']) ?></p>
                    </div>
                  <?php endif; ?>
                  <?php if ($detail['warnings']): ?>
                    <div class="variety-more-section variety-warning">
                      <strong>Attention</strong>
                      <p><?= htmlspecialchars($detail['warnings']) ?></p>
                    </div>
                  <?php endif; ?>
                </details>
              </div>
            </details>
          </section>
        <?php endif; ?>

        <section class="watering-actions">
          <form method="post" class="water-today">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="water">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <input type="hidden" name="date" value="today">
            <button type="submit" class="water-today-btn" title="Marquer arrosé aujourd'hui">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
              </svg>
              Arroser aujourd'hui
            </button>
          </form>

          <div class="watering-stats">
            <div class="watering-stat">
              <span class="watering-stat-value"><?= $stats['total'] ?></span>
              <span class="watering-stat-label">arrosages</span>
            </div>
            <div class="watering-stat">
              <span class="watering-stat-value"><?= $streak ?></span>
              <span class="watering-stat-label">streak jours</span>
            </div>
            <div class="watering-stat">
              <span class="watering-stat-value"><?= $stats['days'] ?></span>
              <span class="watering-stat-label">jours arrosés</span>
            </div>
          </div>

          <div class="water-past">
            <span class="water-past-label">Arrosage dans le passé :</span>
            <form method="post" class="water-past-form">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="water">
              <input type="hidden" name="id" value="<?= $detailId ?>">
              <input type="hidden" name="date" value="<?= date('Y-m-d', strtotime('-1 day')) ?>">
              <button type="submit" class="btn btn-sm">Hier</button>
            </form>
            <form method="post" class="water-past-form">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="water">
              <input type="hidden" name="id" value="<?= $detailId ?>">
              <input type="hidden" name="date" value="<?= date('Y-m-d', strtotime('-2 days')) ?>">
              <button type="submit" class="btn btn-sm">-2 jours</button>
            </form>
            <form method="post" class="water-past-form water-date-form">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="water">
              <input type="hidden" name="id" value="<?= $detailId ?>">
              <input type="date" name="date" max="<?= date('Y-m-d') ?>" required class="input-date-sm">
              <button type="submit" class="btn btn-sm">OK</button>
            </form>
          </div>
        </section>

        <section class="watering-calendar">
          <h3>Calendrier d'arrosage (42 jours)</h3>
          <div class="watering-calendar-grid">
            <?php foreach ($calendar as $day): ?>
              <div class="watering-day <?= $day['isToday'] ? 'today' : '' ?> <?= $day['count'] > 0 ? 'watered' : '' ?>">
                <span class="wd-letter"><?= dayLetter($day['date']) ?></span>
                <span class="wd-num"><?= dayNum($day['date']) ?></span>
                <?php if ($day['count'] > 0): ?>
                  <span class="wd-dot"></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="watering-history">
          <h3>Historique</h3>
          <?php if (empty($history)): ?>
            <p class="empty">Aucun arrosage enregistré.</p>
          <?php else: ?>
            <ul>
              <?php foreach ($history as $log): ?>
                <li>
                  <span><?= fmt($log['watered_at']) ?></span>
                  <form method="post" onsubmit="return confirm('Retirer cet arrosage ?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="unwater">
                    <input type="hidden" name="log_id" value="<?= (int) $log['id'] ?>">
                    <button type="submit" class="btn-text-danger" title="Retirer">✕</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>
    <?php endif; ?>

    <?php if ($isCreate || $editSeedling): ?>
      <div class="overlay open" id="seedlingForm">
        <div class="modal">
          <h3><?= $editSeedling ? 'Modifier ' . htmlspecialchars($editSeedling['name']) : 'Nouvelle culture' ?></h3>
          <form method="post" class="seedling-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editSeedling ? 'update' : 'create' ?>">
            <?php if ($editSeedling): ?>
              <input type="hidden" name="id" value="<?= (int) $editSeedling['id'] ?>">
            <?php endif; ?>

            <input type="text" name="name" placeholder="Nom de la plante *" required value="<?= htmlspecialchars($editSeedling['name'] ?? '') ?>">

            <select name="variety_id">
              <option value="">— Variété (optionnel) —</option>
              <?php foreach ($varieties as $v): ?>
                <option value="<?= (int) $v['id'] ?>" <?= ($editSeedling['variety_id'] ?? null) == $v['id'] ? 'selected' : '' ?>">
                  <?= htmlspecialchars($v['species_name']) ?><?= $v['cultivar_name'] ? ' — ' . htmlspecialchars($v['cultivar_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>

            <input type="text" name="variety" placeholder="Variété libre (si option non listée)" value="<?= htmlspecialchars($editSeedling['variety'] ?? '') ?>">

            <div class="row">
              <input type="number" name="quantity" min="1" placeholder="Nb plants" required value="<?= (int) ($editSeedling['quantity'] ?? 1) ?>">
              <input type="text" name="location" placeholder="Emplacement" value="<?= htmlspecialchars($editSeedling['location'] ?? '') ?>">
            </div>

            <div class="row">
              <input type="date" name="seeded_at" value="<?= htmlspecialchars($editSeedling['seeded_at'] ?? '') ?>">
              <input type="date" name="repotted_at" value="<?= htmlspecialchars($editSeedling['repotted_at'] ?? '') ?>">
            </div>

            <input type="text" name="origin" placeholder="Origine / prix (optionnel)" value="<?= htmlspecialchars($editSeedling['origin'] ?? '') ?>">

            <textarea name="note" rows="2" placeholder="Note (optionnel)"><?= htmlspecialchars($editSeedling['note'] ?? '') ?></textarea>

            <div class="modal-actions">
              <button type="submit"><?= $editSeedling ? 'Enregistrer' : 'Créer' ?></button>
              <a class="close-modal" href="seedlings.php<?= $editSeedling ? '?id=' . $editSeedling['id'] : '' ?>">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

  </div>
</body>
</html>
