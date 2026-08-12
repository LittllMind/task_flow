<?php
declare(strict_types=1);

$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;
$configPath = __DIR__ . '/src/Config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../src/Config.php';
}
require $configPath;

session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pin'])) {
    if (checkPin($_POST['pin'])) {
        $_SESSION['auth'] = 'ok';
        header('Location: admin.php');
        exit;
    }
    $error = 'PIN incorrect.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskFlow — PIN</title>
  <link rel="stylesheet" href="style.css?v=3">
  <link rel="icon" href="favicon.php">
</head>
<body>
  <div class="container" style="display:flex; flex-direction:column; justify-content:center; min-height:100vh;">
    <form method="post" class="modal" style="margin:0 auto;">
      <h3>Saisir le PIN</h3>
      <input type="password" name="pin" inputmode="numeric" maxlength="4" placeholder="0000" required autofocus>
      <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <button type="submit">Valider</button>
    </form>
  </div>
</body>
</html>
