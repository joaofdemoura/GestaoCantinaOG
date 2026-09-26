<?php
date_default_timezone_set('America/Sao_Paulo');

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $c = require __DIR__ . '/config.php';
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['banco']};charset=utf8mb4",
            $c['usuario'],
            $c['senha'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function centavos($valor): int
{
    return (int) round(((float) $valor) * 100);
}

function reais(int $centavos): string
{
    return number_format($centavos / 100, 2, '.', '');
}

function hhmm(?string $hora): string
{
    return substr((string) $hora, 0, 5);
}
