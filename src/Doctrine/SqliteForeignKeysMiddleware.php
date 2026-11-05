<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Schaltet Fremdschlüssel nur bei SQLite ein (lokal und in Tests). MySQL (live) prüft sie ohnehin,
 * dort würde das PRAGMA der DBAL-Middleware nur einen Syntaxfehler auslösen.
 */
final class SqliteForeignKeysMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return $driver instanceof AbstractSQLiteDriver ? (new EnableForeignKeys())->wrap($driver) : $driver;
    }
}
