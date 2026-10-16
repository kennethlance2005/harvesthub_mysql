<?php
/**
 * tools/sync_schema.php
 * Brings an existing HarvestHub database up to date with schema.sql by adding
 * only what is missing: tables, columns, the foreign keys added by ALTERs,
 * built-in roles/permissions/crops, and the audit-log triggers. Columns that
 * schema.sql allows to be empty are relaxed to allow NULL. Nothing is dropped
 * and no demo data is inserted, so it is safe to run on every deploy (the
 * Dockerfile runs it at start-up) and on any teammate's local database.
 *
 *   php tools/sync_schema.php            apply the missing pieces
 *   php tools/sync_schema.php --dry-run  only list what would change
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../db.php';

// Reference rows the app needs; every other INSERT in schema.sql is demo data
// and is skipped. Built-in roles and permissions are upserts, so they always
// run. Role permissions are only seeded when new permissions appear (or none
// exist yet), so ticks an admin removed on the Roles page stay removed. The
// crop catalog and plot map are seeded only while empty, so deleted rows
// don't come back.
const SEED_ALWAYS_TABLES = ['ROLE', 'PERMISSION'];
const SEED_ON_NEW_PERMISSIONS_TABLES = ['ROLE_PERMISSION'];
const SEED_WHEN_EMPTY_TABLES = ['CROP_CATALOG', 'COMMUNITY_PLOTS'];
const TABLE_LEVEL_WORDS = ['PRIMARY', 'KEY', 'INDEX', 'UNIQUE', 'CONSTRAINT', 'FOREIGN', 'CHECK', 'FULLTEXT', 'SPATIAL'];

$dryRun = in_array('--dry-run', $argv, true);
$changes = 0;
$failures = 0;

function say(string $message): void {
    fwrite(STDOUT, "[schema] $message\n");
}

/** Splits SQL into statements, dropping comments and honouring DELIMITER blocks. */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $delimiter = ';';
    $length = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $atLineStart = $i === 0 || $sql[$i - 1] === "\n";
        if ($quote === null) {
            if (in_array(substr($sql, $i, 3), ['-- ', "--\r", "--\n", "--\t"], true)) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $current .= "\n";
                continue;
            }
            if ($atLineStart && preg_match('/\GDELIMITER[ \t]+(\S+)[^\n]*/i', $sql, $match, 0, $i)) {
                $delimiter = $match[1];
                $i += strlen($match[0]);
                continue;
            }
            if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                if (trim($current) !== '') $statements[] = trim($current);
                $current = '';
                $i += strlen($delimiter) - 1;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') $quote = $char;
        } elseif ($char === '\\') {
            $current .= $char . ($sql[$i + 1] ?? '');
            $i++;
            continue;
        } elseif ($char === $quote) {
            if (($sql[$i + 1] ?? '') === $quote) {
                $current .= $char . $char;
                $i++;
                continue;
            }
            $quote = null;
        }
        $current .= $char;
    }
    if (trim($current) !== '') $statements[] = trim($current);
    return $statements;
}

/** Splits a comma-separated list at the top level (ignores commas in brackets/quotes). */
function splitTopLevel(string $text): array {
    $parts = [];
    $current = '';
    $depth = 0;
    $quote = null;
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];
        if ($quote !== null) {
            if ($char === $quote) $quote = null;
        } elseif ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
        } elseif ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $char;
    }
    if (trim($current) !== '') $parts[] = trim($current);
    return $parts;
}

function firstWord(string $text): string {
    preg_match('/^`?([A-Za-z0-9_]+)`?/', $text, $match);
    return $match[1] ?? '';
}

/** A column definition without parts that must not be repeated in MODIFY COLUMN. */
function definitionForModify(string $definition): string {
    $definition = preg_replace('/\s+REFERENCES\s+.*$/is', '', $definition);
    $definition = preg_replace('/\s+(UNIQUE(\s+KEY)?|PRIMARY\s+KEY)\b/i', '', $definition);
    return trim($definition);
}

function run(PDO $pdo, string $label, string $sql): void {
    global $dryRun, $changes, $failures;
    $changes++;
    if ($dryRun) {
        say("would $label");
        return;
    }
    try {
        $pdo->exec($sql);
        say($label);
    } catch (PDOException $e) {
        $failures++;
        say("FAILED to $label: " . $e->getMessage());
    }
}

try {
    $pdo = getDb();
} catch (Throwable $e) {
    say('cannot reach the database, skipping: ' . $e->getMessage());
    exit(0);
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schemaFile = __DIR__ . '/../schema.sql';
if (!is_file($schemaFile)) {
    say('schema.sql not found, skipping');
    exit(0);
}

$tableExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
$columnInfo = $pdo->prepare('SELECT IS_NULLABLE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
$constraintExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
$triggerExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?');

$hasTable = function (string $table) use ($tableExists): bool {
    $tableExists->execute([$table]);
    return (int) $tableExists->fetchColumn() > 0;
};
$column = function (string $table, string $name) use ($columnInfo): ?array {
    $columnInfo->execute([$table, $name]);
    return $columnInfo->fetch(PDO::FETCH_ASSOC) ?: null;
};

$createdTables = [];

foreach (splitSqlStatements(file_get_contents($schemaFile)) as $statement) {
    // CREATE TABLE: create it if missing, otherwise add missing columns.
    if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.*)\)[^)]*$/is', $statement, $match)) {
        [$all, $table, $body] = $match;
        if (!$hasTable($table)) {
            $createSql = preg_replace('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?/i', 'CREATE TABLE IF NOT EXISTS ', $statement);
            run($pdo, "create table $table", $createSql);
            $createdTables[$table] = true;
            continue;
        }
        foreach (splitTopLevel($body) as $item) {
            $name = firstWord($item);
            if ($name === '' || in_array(strtoupper($name), TABLE_LEVEL_WORDS, true)) continue;
            $existing = $column($table, $name);
            if ($existing === null) {
                run($pdo, "add column $table.$name", "ALTER TABLE `$table` ADD COLUMN $item");
            } elseif ($existing['IS_NULLABLE'] === 'NO' && $existing['COLUMN_KEY'] !== 'PRI'
                && !preg_match('/\bNOT\s+NULL\b|\bPRIMARY\s+KEY\b|^\S+\s+TIMESTAMP\b/i', $item)) {
                // (TIMESTAMP columns are NOT NULL by default on MariaDB, so they're left alone.)
                run($pdo, "allow empty values in $table.$name", "ALTER TABLE `$table` MODIFY COLUMN " . definitionForModify($item));
            }
        }
        continue;
    }

    // ALTER TABLE ... ADD COLUMN / ADD CONSTRAINT: add each missing piece on its own.
    if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+(.*)$/is', $statement, $match)) {
        [$all, $table, $clauses] = $match;
        if (!$hasTable($table)) continue;
        foreach (splitTopLevel($clauses) as $clause) {
            if (preg_match('/^ADD\s+COLUMN\s+(.*)$/is', $clause, $add)) {
                $name = firstWord($add[1]);
                if ($column($table, $name) === null) {
                    run($pdo, "add column $table.$name", "ALTER TABLE `$table` ADD COLUMN {$add[1]}");
                }
            } elseif (preg_match('/^ADD\s+CONSTRAINT\s+`?(\w+)`?/is', $clause, $add)) {
                $constraintExists->execute([$table, $add[1]]);
                if ((int) $constraintExists->fetchColumn() === 0) {
                    run($pdo, "add constraint {$add[1]} on $table", "ALTER TABLE `$table` $clause");
                }
            }
        }
        continue;
    }

    // Triggers: create only when missing (the DROPs in schema.sql are skipped).
    if (preg_match('/^CREATE\s+TRIGGER\s+`?(\w+)`?/i', $statement, $match)) {
        $triggerExists->execute([$match[1]]);
        if ((int) $triggerExists->fetchColumn() === 0) {
            run($pdo, "create trigger {$match[1]}", $statement);
        }
        continue;
    }

    // Seed rows the app depends on (see the constants at the top).
    if (preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i', $statement, $match)) {
        $table = $match[1];
        $isEmpty = fn () => isset($createdTables[$table]) || (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 0;
        if (in_array($table, SEED_ALWAYS_TABLES, true)) {
            if ($dryRun) continue;
            $permissionsBefore = $hasTable('PERMISSION') ? (int) $pdo->query('SELECT COUNT(*) FROM PERMISSION')->fetchColumn() : 0;
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                $failures++;
                say("FAILED to refresh $table: " . $e->getMessage());
            }
            if ($table === 'PERMISSION' && (int) $pdo->query('SELECT COUNT(*) FROM PERMISSION')->fetchColumn() > $permissionsBefore) {
                $newPermissions = true;
                $changes++;
                say('added new permissions');
            }
        } elseif (in_array($table, SEED_ON_NEW_PERMISSIONS_TABLES, true)) {
            if (!empty($newPermissions) || $isEmpty()) run($pdo, "give built-in roles their $table rows", $statement);
        } elseif (in_array($table, SEED_WHEN_EMPTY_TABLES, true) && $isEmpty()) {
            run($pdo, "fill $table", $statement);
        }
    }
}

if ($changes === 0) {
    say('database already matches schema.sql');
} elseif ($failures > 0) {
    say("done with $failures problem(s); the site will still start");
} else {
    say($dryRun ? "$changes change(s) needed" : 'database is up to date');
}
