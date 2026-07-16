<?php

declare(strict_types=1);

namespace TaskFlow;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function get(string $path): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }
}
