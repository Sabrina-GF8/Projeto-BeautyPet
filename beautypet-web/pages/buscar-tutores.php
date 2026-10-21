<?php
/**
 * ARQUIVO: buscar-tutores.php
 * Retorna lista de tutores ativos para o autocomplete.
 */
require __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$termo = trim($_GET['q'] ?? '');

if (mb_strlen($termo) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Consulta simplificada focada em 'nome' para evitar erros de colunas inexistentes
    $sql = 'SELECT id, nome 
            FROM clientes 
            WHERE ativo = TRUE 
              AND nome ILIKE :busca 
            ORDER BY nome 
            LIMIT 10';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':busca' => '%' . $termo . '%']);
    $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($tutores);
} catch (Throwable $e) {
    // HEURÍSTICA 9: o detalhe técnico vai para o log do servidor; a tela recebe uma mensagem amigável.
    error_log('[BeautyPet] buscar-tutores: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível buscar tutores agora. Tente novamente.']);
}