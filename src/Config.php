<?php
declare(strict_types=1);

function pinFile(): string
{
    return __DIR__ . '/../config/pin.txt';
}

function getPinHash(): string
{
    $file = pinFile();
    if (!file_exists($file)) {
        return password_hash('0000', PASSWORD_DEFAULT);
    }
    return trim(file_get_contents($file));
}

function setPin(string $pin): bool
{
    $file = pinFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!preg_match('/^\d{4}$/', $pin)) {
        return false;
    }
    return file_put_contents($file, password_hash($pin, PASSWORD_DEFAULT)) !== false;
}

function checkPin(string $pin): bool
{
    return password_verify($pin, getPinHash());
}

function requirePin(): void
{
    session_start();
    if (empty($_SESSION['auth']) || $_SESSION['auth'] !== 'ok') {
        header('Location: login.php');
        exit;
    }
}
