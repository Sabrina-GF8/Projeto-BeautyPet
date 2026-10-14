<?php
// Beauty Pet - conexão única da API com o MySQL/MariaDB do XAMPP.

date_default_timezone_set('America/Sao_Paulo');

$DB_HOST = '127.0.0.1';
$DB_NAME = 'beauty_pet';
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP padrão normalmente usa root sem senha.

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $erro) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível conectar ao banco beauty_pet no XAMPP.',
        'detalhe' => $erro->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
