<?php
// Defaults for Laragon. Use environment variables or the ignored config.local.php.
$config = [
    'host' => getenv('CANTINA_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('CANTINA_DB_PORT') ?: 3306),
    'banco' => getenv('CANTINA_DB_NAME') ?: 'gestao_cantina',
    'usuario' => getenv('CANTINA_DB_USER') ?: 'root',
    'senha' => getenv('CANTINA_DB_PASSWORD') !== false ? getenv('CANTINA_DB_PASSWORD') : '',
];
$local = __DIR__.'/config.local.php';
if (is_file($local)) {
    $overrides = require $local;
    if (!is_array($overrides)) throw new RuntimeException('config.local.php deve retornar um array.');
    $config = array_replace($config, $overrides);
}
return $config;