<?php
// Nettoyage fichiers orphelins prod — s'auto-supprime
@unlink(__DIR__ . '/php_part_only.php');
@unlink(__DIR__ . '/picoclaw.php');
@unlink(__DIR__ . '/cleanup.php');
die('OK');
