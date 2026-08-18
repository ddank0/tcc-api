<?php

/**
 * Bootstrap da suíte.
 *
 * O `<env>` do phpunit.xml não sobrescreve variável já presente no ambiente:
 * o Dotenv do Laravel lê `$_SERVER`, que o PHPUnit não altera, e o container
 * define DB_DATABASE=tcc e APP_ENV=local. O resultado foi um TRUNCATE atingir
 * a base real de 91 milhões de linhas.
 *
 * Forçar aqui é o único ponto que roda antes de o Laravel resolver
 * configuração, e por isso é onde a escolha do banco de teste precisa morar.
 */
$forcados = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'tcc_api_test',
];

foreach ($forcados as $chave => $valor) {
    $_ENV[$chave] = $valor;
    $_SERVER[$chave] = $valor;
    putenv("{$chave}={$valor}");
}

require __DIR__.'/../vendor/autoload.php';
