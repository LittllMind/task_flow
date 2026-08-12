<?php
declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

use TaskFlow\Database;
use TaskFlow\DisciplineRepository;
use TaskFlow\MiningScoreRepository;

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
$repo = new DisciplineRepository($pdo);
$scoreRepo = new MiningScoreRepository($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$activeTab = $_GET['tab'] ?? 'corps';
if (!in_array($activeTab, ['corps', 'mental', 'stats'], true)) {
    $activeTab = 'corps';
}

$error = '';
if (in_array($action, ['add_habit', 'log', 'delete_habit', 'reset_today', 'update_habit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfOk($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
    if ($action === 'add_habit') {
        $type = $_POST['type'] ?? 'corps';
        $title = trim($_POST['title'] ?? '');
        $target = (int) ($_POST['target_value'] ?? 0);
        $unit = $_POST['unit'] ?? 'reps';
        $step = (int) ($_POST['step'] ?? 1);
        if ($title && $target > 0 && in_array($type, ['corps', 'mental'], true)) {
            $repo->addHabit($type, $title, $target, $unit, max(1, $step));
        }
    } elseif ($action === 'log' && $id) {
        $value = (int) ($_POST['value'] ?? 0);
        if ($value > 0) {
            $repo->logToday($id, $value);
            // Bonus Mining↔Discipline
            if ($scoreRepo->hasMiningHarvestToday() && $scoreRepo->hasOtherHabitActivityToday()) {
                $scoreRepo->addBonus(25, 'Journée productive 💪⛏️');
            }
        }
    } elseif ($action === 'reset_today' && $id) {
        $repo->setLog($id, date('Y-m-d'), 0);
    } elseif ($action === 'update_habit' && $id) {
        $changes = [];
        if (isset($_POST['title'])) {
            $changes['title'] = trim($_POST['title']);
        }
        if (isset($_POST['target_value'])) {
            $changes['target_value'] = (int) $_POST['target_value'];
        }
        if (isset($_POST['step'])) {
            $changes['step'] = max(1, (int) $_POST['step']);
        }
        if (isset($_POST['unit']) && in_array($_POST['unit'], ['reps', 'sessions'], true)) {
            $changes['unit'] = $_POST['unit'];
        }
        if (!empty($changes)) {
            $repo->updateHabit($id, $changes);
        }
    } elseif ($action === 'delete_habit' && $id) {
        $repo->deleteHabit($id);
    }
    header('Location: discipline.php?tab=' . $activeTab);
    exit;
}

$habits = $repo->listHabits();
$corpsHabits = array_filter($habits, fn($h) => $h['type'] === 'corps');
$mentalHabits = array_filter($habits, fn($h) => $h['type'] === 'mental');
$score = $repo->score();
$stats = $repo->stats();

// Historique hebdo global
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $weekDays[] = [
        'date' => $d,
        'label' => date('D', strtotime($d)),
        'num' => (int) date('d', strtotime($d)),
    ];
}

// Calcul du taux journalier sur la semaine
$weeklyRates = [];
foreach ($weekDays as $day) {
    $dayReached = 0;
    foreach ($habits as $h) {
        $progress = $repo->progressFor((int) $h['id'], $day['date']);
        if ($progress['reached']) $dayReached++;
    }
    $totalH = count($habits);
    $weeklyRates[] = $totalH > 0 ? (int) round(($dayReached / $totalH) * 100) : 0;
}

// Taux par catégorie
$corpsReached = count(array_filter($corpsHabits, fn($h) => $h['today_reached']));
$corpsTotal = count($corpsHabits);
$corpsRate = $corpsTotal > 0 ? (int) round(($corpsReached / $corpsTotal) * 100) : 0;

$mentalReached = count(array_filter($mentalHabits, fn($h) => $h['today_reached']));
$mentalTotal = count($mentalHabits);
$mentalRate = $mentalTotal > 0 ? (int) round(($mentalReached / $mentalTotal) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow — Discipline</title>
  <link rel="stylesheet" href="style.css?v=3">
  <link rel="icon" href="favicon.php">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <header>
      <h1>Discipline</h1>
      <div class="header-center-group">
        <span class="discipline-global-score" title="Score discipline">💪 <?= (int)$score['score'] ?></span>
        <?php if ($score['avgStreak'] >= 1): ?>
          <span class="mining-streak-badge"><?= number_format($score['avgStreak'], 1) ?> 🔥</span>
        <?php endif; ?>
      </div>
      <a class="back-link" href="checklist.php" title="Retour">&lsaquo; Retour aux checklists</a>
    </header>

    <div class="view-toggle discipline-tabs">
      <a href="?tab=corps" class="<?= $activeTab === 'corps' ? 'active' : '' ?>">💪 Corps</a>
      <a href="?tab=mental" class="<?= $activeTab === 'mental' ? 'active' : '' ?>">🧠 Mental</a>
      <a href="?tab=stats" class="<?= $activeTab === 'stats' ? 'active' : '' ?>">📊 Stats</a>
    </div>

    <?php if ($activeTab === 'stats'): ?>
      <!-- Score global -->
      <section class="score-section">
        <div class="score-header">
          <span class="score-value"><?= (int) $score['score'] ?></span>
          <span class="score-label">/ 100</span>
        </div>
        <div class="score-track">
          <div class="score-fill" style="width: <?= (int)$score['score'] ?>%"></div>
        </div>
        <div class="score-breakdown">
          <span><?= (int)$score['rate'] ?>% objectifs aujourd'hui</span>
          <span>🔥 <?= number_format($score['avgStreak'], 1) ?> moy</span>
          <span>+<?= (int)$score['streakFactor'] ?>% régularité</span>
        </div>
      </section>

      <!-- Sparkline hebdo global -->
      <section class="admin-section">
        <h2>Complétion sur 7 jours</h2>
        <div class="discipline-sparkline">
          <?php foreach ($weeklyRates as $i => $rate): ?>
            <div class="spark-bar-wrap">
              <div class="spark-bar" style="height: <?= $rate ?>%"></div>
              <span class="spark-label"><?= $weekDays[$i]['label'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Stats par catégorie -->
      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-value"><?= (int)$stats['rate'] ?>%</span>
          <span class="stat-label">Objectifs aujourd'hui</span>
        </div>
        <div class="stat-card">
          <span class="stat-value"><?= (int)$stats['reached'] ?>/<?= (int)$stats['total'] ?></span>
          <span class="stat-label">Atteints</span>
        </div>
        <div class="stat-card">
          <span class="stat-value"><?= (int)$stats['corps'] ?></span>
          <span class="stat-label">Corps</span>
        </div>
        <div class="stat-card">
          <span class="stat-value"><?= (int)$stats['mental'] ?></span>
          <span class="stat-label">Mental</span>
        </div>
      </div>

      <!-- Complétion par catégorie -->
      <section class="admin-section">
        <h2>Complétion par catégorie</h2>
        <div class="category-completion">
          <div class="cat-complete-row">
            <span>💪 Corps</span>
            <div class="cat-complete-track">
              <div class="cat-complete-fill" style="width: <?= $corpsRate ?>%; background: var(--accent)"></div>
            </div>
            <span><?= $corpsRate ?>%</span>
          </div>
          <div class="cat-complete-row">
            <span>🧠 Mental</span>
            <div class="cat-complete-track">
              <div class="cat-complete-fill" style="width: <?= $mentalRate ?>%; background: #8b5cf6"></div>
            </div>
            <span><?= $mentalRate ?>%</span>
          </div>
        </div>
      </section>

        <!-- Meilleures streaks -->
      <section class="admin-section">
        <h2>Meilleures séries</h2>
        <?php
          usort($score['streakDetails'], fn($a, $b) => $b['streak'] <=> $a['streak']);
          $topStreaks = array_slice($score['streakDetails'], 0, 5);
        ?>
        <?php if (empty($topStreaks)): ?>
          <p class="empty">Aucune série encore. Commence dès aujourd'hui !</p>
        <?php else: ?>
          <ul class="streak-leaderboard">
            <?php foreach ($topStreaks as $i => $st): ?>
              <li>
                <span class="st-rank">#<?= $i + 1 ?></span>
                <span class="st-title"><?= htmlspecialchars($st['title']) ?></span>
                <span class="st-days"><?= (int)$st['streak'] ?> 🔥</span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <!-- Heatmap globale -->
      <section class="admin-section">
        <h2>Régularité annuelle</h2>
        <?php
          $globalHeatmap = [];
          foreach ($habits as $h) {
            foreach ($repo->heatmapFor((int)$h['id']) as $weekNum => $week) {
              foreach ($week as $dateStr => $day) {
                $globalHeatmap[$dateStr] = max($globalHeatmap[$dateStr] ?? 0, $day['level']);
              }
            }
          }
          // Regroupement par semaine
          $globalWeeks = [];
          $currentWeek = [];
          $todayObj = new \DateTimeImmutable('today');
          $startObj = $todayObj->modify('-364 days');
          for ($i = 0; $i < 365; $i++) {
            $dateObj = $startObj->modify("+{$i} days");
            $dateStr = $dateObj->format('Y-m-d');
            $weekday = (int) $dateObj->format('w');
            $currentWeek[$dateStr] = [
              'date' => $dateStr,
              'level' => $globalHeatmap[$dateStr] ?? 0,
              'weekday' => $weekday,
            ];
            if ($weekday === 6 || $i === 364) {
              $globalWeeks[] = $currentWeek;
              $currentWeek = [];
            }
          }
        ?>
        <div class="discipline-heatmap global-heatmap" title="Intensité maximale de toutes les habitudes">
          <?php foreach ($globalWeeks as $week): ?>
            <div class="heatmap-week">
              <?php foreach ($week as $day): ?>
                <div class="heatmap-day level-<?= $day['level'] ?>" title="<?= $day['date'] ?>"></div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="heatmap-legend">
          <span>Moins</span>
          <div class="heatmap-day level-1"></div>
          <div class="heatmap-day level-2"></div>
          <div class="heatmap-day level-3"></div>
          <div class="heatmap-day level-4"></div>
          <span>Plus</span>
        </div>
      </section>

      <section class="admin-section">
        <h2>Nouvelle habitude</h2>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="add_habit">
          <input type="text" name="title" placeholder="Ex : Pompes, Wim Hof..." required>
          <select name="type" required>
            <option value="corps">Corps</option>
            <option value="mental">Mental</option>
          </select>
          <div class="row">
            <input type="number" name="target_value" placeholder="Cible" min="1" required>
            <select name="unit" required>
              <option value="reps">Répétitions</option>
              <option value="sessions">Sessions</option>
            </select>
          </div>
          <input type="number" name="step" placeholder="Pas d’incrément (ex: 5)" min="1" value="1">
          <button type="submit">Ajouter l’habitude</button>
        </form>
      </section>

      <section class="admin-section">
        <h2>Toutes les habitudes</h2>
        <?php if (empty($habits)): ?>
          <p class="empty">Aucune habitude.</p>
        <?php else: ?>
          <ul class="sub-list">
            <?php foreach ($habits as $h): ?>
              <li>
                <span><?= htmlspecialchars($h['title']) ?> (<?= $h['target_value'] ?> <?= $h['unit'] ?>) — 💪 <?= $repo->streakFor((int)$h['id']) ?></span>
                <form method="post" class="action-form" onsubmit="return confirm('Supprimer cette habitude ?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="delete_habit">
                  <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">✕</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <?php $list = $activeTab === 'corps' ? $corpsHabits : $mentalHabits; ?>
      <?php if (empty($list)): ?>
        <p class="empty">Aucune habitude dans cette section. <br>Ajoute-en dans l'onglet Stats.</p>
      <?php endif; ?>

      <?php foreach ($list as $h): ?>
      <article class="discipline-card <?= $h['today_reached'] ? 'done' : '' ?>">
        <div class="discipline-header">
          <h2><?= htmlspecialchars($h['title']) ?></h2>
          <div class="header-badges">
            <span class="streak-badge" title="Série consécutive">🔥 <?= $repo->streakFor((int)$h['id']) ?> jours</span>
            <span class="regularity-badge" title="Régularité 30j"><?= $repo->regularityScore((int)$h['id'], 30) ?>%</span>
          </div>
        </div>
        <div class="progress-row">
          <div class="progress-track">
            <div class="progress-fill" style="width: <?= (int)$h['today_percent'] ?>%;"></div>
          </div>
          <span class="progress-label"><?= (int)$h['today_current'] ?>/<?= (int)$h['target_value'] ?> <?= htmlspecialchars($h['unit']) ?></span>
        </div>
        <!-- Mini sparkline 30j -->
        <div class="discipline-sparkline mini-sparkline">
          <?php
            $last30 = [];
            $todayDay = new \DateTimeImmutable('today');
            $startDay = $todayDay->modify('-29 days');
            $stmt = $pdo->prepare('SELECT log_date, SUM(value) as total FROM discipline_logs WHERE habit_id = ? AND log_date >= ? GROUP BY log_date');
            $stmt->execute([(int)$h['id'], $startDay->format('Y-m-d')]);
            $rows = $stmt->fetchAll();
            $logMap = [];
            foreach ($rows as $row) { $logMap[$row['log_date']] = (int)$row['total']; }
            $target = (int)$h['target_value'];
            for ($i = 0; $i < 30; $i++) {
              $dObj = $startDay->modify("+{$i} days");
              $dStr = $dObj->format('Y-m-d');
              $v = $logMap[$dStr] ?? 0;
              $pct = $target > 0 ? min(100, (int) round(($v / $target) * 100)) : ($v > 0 ? 100 : 0);
              $last30[] = ['date' => $dStr, 'pct' => $pct, 'reached' => $v >= $target];
            }
          ?>
          <?php foreach ($last30 as $spark): ?>
            <div class="spark-bar-wrap">
              <div class="spark-bar <?= $spark['reached'] ? 'reached' : '' ?>" style="height: <?= max(4, $spark['pct']) ?>%" title="<?= $spark['date'] ?>"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="discipline-actions">
          <form method="post" class="increment-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="log">
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
            <input type="hidden" name="value" value="<?= (int)$h['step'] ?>">
            <button type="submit" class="btn btn-primary increment-btn">+<?= (int)$h['step'] ?></button>
          </form>
          <form method="post" class="increment-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="log">
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
            <input type="hidden" name="value" value="<?= 2 * (int)$h['step'] ?>">
            <button type="submit" class="btn btn-primary increment-btn">+<?= 2 * (int)$h['step'] ?></button>
          </form>
          <form method="post" class="action-form" onsubmit="return confirm('Remettre à zéro pour aujourd’hui ?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reset_today">
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
            <button type="submit" class="btn btn-danger" title="Reset aujourd'hui">↺</button>
          </form>
        </div>
        <div class="week-history">
          <?php foreach ($repo->weekHistoryFor((int)$h['id']) as $day): ?>
            <span class="week-day <?= $day['reached'] ? 'reached' : '' ?>" title="<?= htmlspecialchars($day['date']) ?>">
              <?= htmlspecialchars($day['day']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>