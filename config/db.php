<?php

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$host = getenv('DB_HOST') ?: '10.114.16.89';
$db = getenv('DB_NAME') ?: 'gestion_fichiers2';
$user = getenv('DB_USER') ?: 'user_paie';
$pass = getenv('DB_PASS') ?: 'abdessamadeKH';
$port = getenv('DB_PORT') ?: '3306';

// Liste des instructions types pour l'administration
$instructions_list = [
    'attribution' => 'Pour attribution / Traitement',
    'avis' => 'Pour avis et retour',
    'info' => 'Pour information / Classement',
    'signature' => 'Pour signature',
    'urgent' => 'URGENT - Prioritaire'
];

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $serverDsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $serverPdo = new PDO($serverDsn, $user, $pass, $options);
    $db_was_installed_now = install_database_if_needed($serverPdo, $db);
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

function install_database_if_needed(PDO $serverPdo, string $dbName): bool
{
    $quotedDbName = quote_sql_identifier($dbName);

    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    $checkStmt = $serverPdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = :db AND table_name = 'users'"
    );
    $checkStmt->execute(['db' => $dbName]);
    $usersTableExists = (int) $checkStmt->fetchColumn() > 0;
    if ($usersTableExists) {
        return false;
    }

    $sqlDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR;
    $schemaPath = $sqlDir . 'install_complete.sql';
    if (!is_file($schemaPath) || !is_readable($schemaPath)) {
        $schemaPath = $sqlDir . 'schema.sql';
    }
    if (!is_file($schemaPath) || !is_readable($schemaPath)) {
        throw new RuntimeException("Schema SQL introuvable: {$schemaPath}");
    }

    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        throw new RuntimeException("Lecture impossible du schema SQL: {$schemaPath}");
    }

    $schemaSql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?[^`\s;]+`?\s+CHARACTER\s+SET\s+[^;]+;/i', '', $schemaSql);
    $schemaSql = preg_replace('/USE\s+`?[^`\s;]+`?\s*;/i', '', $schemaSql);
    if ($schemaSql === null) {
        throw new RuntimeException('Erreur lors du pretraitement du schema SQL.');
    }

    $serverPdo->exec("USE {$quotedDbName}");
    foreach (split_sql_statements($schemaSql) as $statement) {
        $serverPdo->exec($statement);
    }

    return true;
}

function quote_sql_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);

    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $nextChar = $i + 1 < $length ? $sql[$i + 1] : '';
        $prevChar = $i > 0 ? $sql[$i - 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $nextChar === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            if ($char === '-' && $nextChar === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $nextChar === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === "'" && !$inDoubleQuote && !$inBacktick && $prevChar !== '\\') {
            $inSingleQuote = !$inSingleQuote;
            $buffer .= $char;
            continue;
        }

        if ($char === '"' && !$inSingleQuote && !$inBacktick && $prevChar !== '\\') {
            $inDoubleQuote = !$inDoubleQuote;
            $buffer .= $char;
            continue;
        }

        if ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
            $inBacktick = !$inBacktick;
            $buffer .= $char;
            continue;
        }

        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $lastStatement = trim($buffer);
    if ($lastStatement !== '') {
        $statements[] = $lastStatement;
    }

    return $statements;
}
