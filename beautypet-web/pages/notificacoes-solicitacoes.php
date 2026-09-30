<?php // Executa esta instrução como parte da regra de negócio desta página.
/**
 * ARQUIVO: notificacoes-solicitacoes.php
 * RESPONSABILIDADE: endpoint leve usado pelo sino do cabeçalho.
 * Retorna a quantidade de solicitações MOBILE que ainda estão pendentes.
 */
require __DIR__ . '/../config/config.php'; // Carrega a configuração ou função compartilhada necessária nesta página.

header('Content-Type: application/json; charset=utf-8'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.

try { // Executa esta instrução como parte da regra de negócio desta página.
    $stmt = $pdo->query( // Executa uma consulta direta ao PostgreSQL.
        "SELECT -- Continua a instrução SQL iniciada nas linhas relacionadas.
            COUNT(*) AS total, -- Continua a instrução SQL iniciada nas linhas relacionadas.
            MAX(id) AS ultimo_id -- Continua a instrução SQL iniciada nas linhas relacionadas.
         FROM agendamentos -- Define a origem principal dos dados desta consulta.
         WHERE origem = 'MOBILE' -- Filtra os registros conforme esta condição.
           AND status = 'SOLICITADO'" // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

    $resumo = $stmt->fetch() ?: []; // Obtém uma linha retornada pelo banco.

    echo json_encode( // Escreve este conteúdo na resposta enviada ao navegador.
        [ // Executa esta instrução como parte da regra de negócio desta página.
            'sucesso'   => true, // Executa esta instrução como parte da regra de negócio desta página.
            'total'     => (int) ($resumo['total'] ?? 0), // Executa esta instrução como parte da regra de negócio desta página.
            'ultimo_id' => isset($resumo['ultimo_id']) // Executa esta instrução como parte da regra de negócio desta página.
                ? (int) $resumo['ultimo_id'] // Executa esta instrução como parte da regra de negócio desta página.
                : null, // Executa esta instrução como parte da regra de negócio desta página.
        ], // Executa esta instrução como parte da regra de negócio desta página.
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} catch (Throwable $erro) { // Executa esta instrução como parte da regra de negócio desta página.
    http_response_code(500); // Executa esta instrução como parte da regra de negócio desta página.

    echo json_encode( // Escreve este conteúdo na resposta enviada ao navegador.
        [ // Executa esta instrução como parte da regra de negócio desta página.
            'sucesso' => false, // Executa esta instrução como parte da regra de negócio desta página.
            'total'   => 0, // Executa esta instrução como parte da regra de negócio desta página.
            'mensagem' => 'Não foi possível consultar as solicitações agora.', // Executa esta instrução como parte da regra de negócio desta página.
        ], // Executa esta instrução como parte da regra de negócio desta página.
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES // Executa esta instrução como parte da regra de negócio desta página.
    ); // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
