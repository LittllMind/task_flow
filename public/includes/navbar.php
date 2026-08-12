<?php
declare(strict_types=1);

$navItems = [
    ['url' => 'checklist.php', 'label' => 'Tâches',      'icon' => '&#9997;&#65039;'],
    ['url' => 'checklist.php', 'label' => 'Checklists',  'icon' => '&#9745;&#65039;'],
    ['url' => 'mining.php',   'label' => 'Mining',      'icon' => '&#9889;'],
    ['url' => 'discipline.php', 'label' => 'Discipline', 'icon' => '&#128170;'],
    ['url' => 'seedlings.php', 'label' => 'Cultures',    'icon' => '&#127793;'],
    ['url' => 'admin.php',     'label' => 'Catégories',  'icon' => '&#127991;&#65039;'],
    ['url' => 'stats.php',     'label' => 'Stats',       'icon' => '&#128202;'],
    ['url' => 'logout.php',    'label' => 'Quitter',     'icon' => '&#10005;'],
];

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<nav class="main-navbar">
  <div class="nav-inner">
    <a class="nav-brand" href="index.php" title="TaskFlow">
      <span class="nav-brand-icon">&#9939;&#65039;</span>
      <span class="nav-brand-text">TaskFlow</span>
    </a>
    <ul class="nav-links">
      <?php foreach ($navItems as $item):
        $isActive = $current === $item['url'] ? ' active' : '';
        $isLogout = $item['url'] === 'logout.php' ? ' nav-logout' : '';
      ?>
      <li><a class="nav-item<?= $isActive . $isLogout ?>" href="<?= $item['url'] ?>" title="<?= htmlspecialchars($item['label']) ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>
