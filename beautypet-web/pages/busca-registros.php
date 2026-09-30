<?php
/**
 * ARQUIVO: busca-registros.php
 * RESPONSABILIDADE: Endpoint de busca dinâmica.
 * Recebe termos de pesquisa via requisição, consulta pets, tutores, pacotes ou registros gerais e devolve resultados em JSON para os campos de busca do sistema.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
header('Content-Type: application/json; charset=utf-8');

// Armazena em "tipo" o valor necessário para as próximas etapas deste fluxo.
$tipo = (string) ($_GET['tipo'] ?? '');
// Armazena o texto digitado pelo usuário para filtrar os registros consultados.
$termo = trim((string) ($_GET['q'] ?? ''));
// Armazena em "limite" o valor necessário para as próximas etapas deste fluxo.
$limite = 18;

// Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
try {
    // Verifica a condição abaixo antes de executar este bloco.
    if ($tipo === 'pet') {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($termo === '') {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Monta a data e hora completas de início no formato esperado pelo banco de dados.
        $inicio = $termo . '%';
        // Armazena em "contem" o valor necessário para as próximas etapas deste fluxo.
        $contem = '%' . $termo . '%';

$stmt = $pdo->prepare(
            'SELECT p.id, p.nome, c.nome AS tutor, r.nome AS raca
             FROM pets p
             JOIN clientes c ON c.id = p.cliente_id
             LEFT JOIN racas r ON r.id = p.raca_id
             WHERE p.ativo = TRUE
               AND c.ativo = TRUE
               AND (
                    p.nome LIKE ?
                    OR c.nome LIKE ?
                    OR r.nome LIKE ?
               )
             ORDER BY
                CASE
                    WHEN p.nome LIKE ? THEN 0
                    WHEN c.nome LIKE ? THEN 1
                    WHEN r.nome LIKE ? THEN 2
                    ELSE 3
                END,
                p.nome,
                c.nome
             LIMIT ' . $limite
        );
        $stmt->execute([$contem, $contem, $contem, $inicio, $inicio, $inicio]);

        // Armazena o resultado final da operação ou consulta que será devolvido ou exibido pela página.
        $resultado = array_map(
            static function (array $item): array {
                $raca = trim((string) ($item['raca'] ?? ''));
                $partes = [(string) $item['nome']];

                if ($raca !== '') {
                    $partes[] = $raca;
                }

                $partes[] = (string) $item['tutor'];

                return [
                    'id' => (int) $item['id'],
                    'label' => implode(' — ', $partes),
                    'detail' => $raca !== ''
                        ? 'Pet • Raça: ' . $raca . ' • tutor responsável'
                        : 'Pet • raça não informada • tutor responsável',
                ];
            },
            $stmt->fetchAll()
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($tipo === 'tutor') {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($termo === '') {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Monta a data e hora completas de início no formato esperado pelo banco de dados.
        $inicio = $termo . '%';
        // Armazena em "contem" o valor necessário para as próximas etapas deste fluxo.
        $contem = '%' . $termo . '%';
        // Armazena a coleção "numeros" carregada para ser percorrida e exibida/processada a seguir.
        $numeros = preg_replace('/\D+/', '', $termo);
        // Armazena em "cpfBusca" o valor necessário para as próximas etapas deste fluxo.
        $cpfBusca = $numeros !== '' ? '%' . $numeros . '%' : $contem;

        // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            'SELECT id, nome, cpf, email, telefone_principal
             FROM clientes
             WHERE ativo = TRUE
               AND (
                    nome LIKE ?
                    OR cpf LIKE ?
                    OR email LIKE ?
                    OR telefone_principal LIKE ?
               )
             ORDER BY
                CASE WHEN nome LIKE ? THEN 0 ELSE 1 END,
                nome
             LIMIT ' . $limite
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([
            $contem,
            $cpfBusca,
            $contem,
            $cpfBusca,
            $inicio,
        ]);

        // Armazena o resultado final da operação ou consulta que será devolvido ou exibido pela página.
        $resultado = array_map(
            static function (array $item): array {
                // Armazena a coleção "detalhes" carregada para ser percorrida e exibida/processada a seguir.
                $detalhes = [];

                // Verifica a condição abaixo antes de executar este bloco.
                if (!empty($item['cpf'])) {
                    $detalhes[] = formatarCpf((string) $item['cpf']);
                }

                // Verifica a condição abaixo antes de executar este bloco.
                if (!empty($item['telefone_principal'])) {
                    $detalhes[] = formatarTelefone((string) $item['telefone_principal']);
                }

                // Verifica a condição abaixo antes de executar este bloco.
                if (!empty($item['email'])) {
                    $detalhes[] = (string) $item['email'];
                }

                // Devolve o resultado calculado para o ponto que chamou esta função.
                return [
                    'id' => (int) $item['id'],
                    'label' => (string) $item['nome'],
                    'detail' => implode(' • ', $detalhes),
                ];
            },
            // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
            $stmt->fetchAll()
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($tipo === 'geral') {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($termo === '') {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Armazena em "contem" o valor necessário para as próximas etapas deste fluxo.
        $contem = '%' . $termo . '%';
        // Armazena a coleção "numeros" carregada para ser percorrida e exibida/processada a seguir.
        $numeros = preg_replace('/\D+/', '', $termo);
        // Armazena em "cpfBusca" o valor necessário para as próximas etapas deste fluxo.
        $cpfBusca = $numeros !== '' ? '%' . $numeros . '%' : $contem;

        // Armazena em "petsStmt" o valor necessário para as próximas etapas deste fluxo.
        $petsStmt = $pdo->prepare(
            'SELECT p.id, p.nome, c.nome AS tutor, r.nome AS raca
             FROM pets p
             JOIN clientes c ON c.id = p.cliente_id
             LEFT JOIN racas r ON r.id = p.raca_id
             WHERE p.ativo = TRUE
               AND c.ativo = TRUE
               AND (p.nome LIKE ? OR c.nome LIKE ? OR r.nome LIKE ?)
             ORDER BY p.nome
             LIMIT 8'
        );
        $petsStmt->execute([$contem, $contem, $contem]);

        // Armazena em "tutoresStmt" o valor necessário para as próximas etapas deste fluxo.
        $tutoresStmt = $pdo->prepare(
            'SELECT id, nome, cpf
             FROM clientes
             WHERE ativo = TRUE
               AND (nome LIKE ? OR cpf LIKE ? OR email LIKE ?)
             ORDER BY nome
             LIMIT 8'
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $tutoresStmt->execute([$contem, $cpfBusca, $contem]);

        // Armazena o resultado final da operação ou consulta que será devolvido ou exibido pela página.
        $resultado = [];

        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($petsStmt->fetchAll() as $item) {
            $raca = trim((string) ($item['raca'] ?? ''));

            $resultado[] = [
                'id' => (int) $item['id'],
                'label' => (string) $item['nome'],
                'detail' => 'Pet'
                    . ($raca !== '' ? ' • ' . $raca : ' • raça não informada')
                    . ' • Tutor: ' . (string) $item['tutor'],
                'url' => 'ficha-pet.php?id=' . (int) $item['id'],
                'kind' => 'pet',
            ];
        }

        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($tutoresStmt->fetchAll() as $item) {
            // Armazena em "detalhe" o valor necessário para as próximas etapas deste fluxo.
            $detalhe = 'Tutor';
            // Verifica a condição abaixo antes de executar este bloco.
            if (!empty($item['cpf'])) {
                $detalhe .= ' • ' . formatarCpf((string) $item['cpf']);
            }

            $resultado[] = [
                'id' => (int) $item['id'],
                'label' => (string) $item['nome'],
                'detail' => $detalhe,
                'url' => 'ficha-tutor.php?id=' . (int) $item['id'],
                'kind' => 'tutor',
            ];
        }

        echo json_encode(array_slice($resultado, 0, $limite), JSON_UNESCAPED_UNICODE);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }


    // Verifica a condição abaixo antes de executar este bloco.
    if ($tipo === 'saldo-debito') {
        // Converte o identificador do pet selecionado para um inteiro válido.
        $petId = inteiroOuNull($_GET['pet_id'] ?? null);

        // Verifica a condição abaixo antes de executar este bloco.
        if ($petId === null) {
            echo json_encode(['saldo' => 0], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            "SELECT
                c.id AS cliente_id,
                COALESCE(SUM(
                    CASE
                        WHEN m.tipo_saldo = 'DEBITO' AND m.operacao = 'ENTRADA' THEN m.valor
                        WHEN m.tipo_saldo = 'DEBITO' AND m.operacao = 'SAIDA' THEN -m.valor
                        ELSE 0
                    END
                ), 0) AS saldo
             FROM pets p
             JOIN clientes c ON c.id = p.cliente_id
             LEFT JOIN movimentacoes_cliente m ON m.cliente_id = c.id
             WHERE p.id = :pet_id
               AND p.ativo = TRUE
               AND c.ativo = TRUE
             GROUP BY c.id"
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([':pet_id' => $petId]);
        // Obtém a próxima linha retornada pela consulta como um registro associativo.
        $dados = $stmt->fetch();

        echo json_encode([
            'saldo' => max(0, (float) ($dados['saldo'] ?? 0)),
        ], JSON_UNESCAPED_UNICODE);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($tipo === 'registro-fechamento') {
        // Armazena em "registro" o valor necessário para as próximas etapas deste fluxo.
        $registro = trim((string) ($_GET['registro'] ?? ''));
        // Guarda o ID do fechamento diário referente à data selecionada.
        $fechamentoId = inteiroOuNull($_GET['fechamento_id'] ?? null);

        // Verifica a condição abaixo antes de executar este bloco.
        if ($registro === '' || $fechamentoId === null) {
            http_response_code(400);
            echo json_encode(['erro' => 'Registro inválido.'], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Reúne os IDs dos lançamentos que pertencem ao mesmo atendimento ou registro do fechamento.
        $lancamentoIds = [];

        // Verifica a condição abaixo antes de executar este bloco.
        if (preg_match('/^a:(\d+)$/', $registro, $partes)) {
            // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM lancamentos_diarios
                 WHERE fechamento_diario_id = :fechamento
                   AND atendimento_id = :atendimento
                 ORDER BY id'
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([
                ':fechamento' => $fechamentoId,
                ':atendimento' => (int) $partes[1],
            ]);
            // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
            $lancamentoIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
        } elseif (preg_match('/^l:(\d+)$/', $registro, $partes)) {
            // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM lancamentos_diarios
                 WHERE fechamento_diario_id = :fechamento
                   AND id = :id
                 LIMIT 1'
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([
                ':fechamento' => $fechamentoId,
                ':id' => (int) $partes[1],
            ]);
            // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
            $id = $stmt->fetchColumn();
            // Verifica a condição abaixo antes de executar este bloco.
            if ($id) {
                // Reúne os IDs dos lançamentos que pertencem ao mesmo atendimento ou registro do fechamento.
                $lancamentoIds = [(int) $id];
            }
        }

        // Verifica a condição abaixo antes de executar este bloco.
        if (!$lancamentoIds) {
            http_response_code(404);
            echo json_encode(['erro' => 'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Monta os marcadores necessários para usar uma quantidade variável de IDs em uma consulta preparada.
        $placeholders = implode(',', array_fill(0, count($lancamentoIds), '?'));

        // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            "SELECT
                l.id,
                l.pet_id,
                p.nome AS pet,
                c.nome AS tutor,
                r.nome AS raca,
                l.servico_id,
                ...
             FROM lancamentos_diarios l
             JOIN pets p ON p.id = l.pet_id
             JOIN clientes c ON c.id = p.cliente_id
             LEFT JOIN racas r ON r.id = p.raca_id
             WHERE l.id IN ($placeholders)
             ORDER BY l.id"
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute($lancamentoIds);
        // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
        $linhas = $stmt->fetchAll();

        // Verifica a condição abaixo antes de executar este bloco.
        if (!$linhas) {
            http_response_code(404);
            echo json_encode(['erro' => 'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Reúne os serviços ativos disponíveis para seleção, classificação ou resumo.
        $servicos = [];

        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($linhas as $linha) {
            // Prepara uma consulta em "lancamento_adicionais" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                'SELECT
                    tipo_adicional_id AS tipo_id,
                    valor,
                    descricao
                 FROM lancamento_adicionais
                 WHERE lancamento_diario_id = :id
                 ORDER BY id'
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':id' => (int) $linha['id']]);

            $servicos[] = [
                'lancamento_id' => (int) $linha['id'],
                'servico_id' => (int) $linha['servico_id'],
                'pet_pacote_id' => $linha['pet_pacote_id'] !== null
                    ? (int) $linha['pet_pacote_id']
                    : null,
                'creditos_pacote_usados' => (int) $linha['creditos_pacote_usados'],
                'valor_servico' => (float) $linha['valor_servico'],
                'descricao' => (string) ($linha['descricao'] ?? ''),
                'adicionais' => array_map(
                    static fn (array $item): array => [
                        'tipo_id' => (int) $item['tipo_id'],
                        'valor' => (float) $item['valor'],
                        'descricao' => (string) ($item['descricao'] ?? ''),
                    ],
                    // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
                    $stmt->fetchAll()
                ),
            ];
        }

        // Prepara uma consulta em "pagamentos" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN fp.nome = 'Dinheiro' THEN p.valor ELSE 0 END), 0) AS dinheiro,
                COALESCE(SUM(CASE WHEN fp.nome = 'Pix' THEN p.valor ELSE 0 END), 0) AS pix,
                COALESCE(SUM(CASE WHEN LOWER(fp.nome) LIKE 'cart%' THEN p.valor ELSE 0 END), 0) AS cartao
             FROM pagamentos p
             JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
             WHERE p.estornado = FALSE
               AND p.lancamento_diario_id IN ($placeholders)"
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute($lancamentoIds);
        // Obtém a próxima linha retornada pela consulta como um registro associativo.
        $pagamentos = $stmt->fetch() ?: [];

        // Prepara uma consulta em "movimentacoes_cliente" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(valor), 0)
             FROM movimentacoes_cliente
             WHERE tipo_saldo = 'CREDITO'
               AND operacao = 'SAIDA'
               AND lancamento_diario_id IN ($placeholders)"
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute($lancamentoIds);
        // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
        $creditoUtilizado = (float) $stmt->fetchColumn();

        echo json_encode([
            'registro' => $registro,
            'pet_id' => (int) $linhas[0]['pet_id'],
            'pet_label' => implode(
                ' — ',
                array_values(array_filter([
                    (string) $linhas[0]['pet'],
                    trim((string) ($linhas[0]['raca'] ?? '')),
                    (string) $linhas[0]['tutor'],
                ], static fn (string $parte): bool => $parte !== ''))
            ),
            'servicos' => $servicos,
            'pagamentos' => [
                'dinheiro' => (float) ($pagamentos['dinheiro'] ?? 0),
                'pix' => (float) ($pagamentos['pix'] ?? 0),
                'cartao' => (float) ($pagamentos['cartao'] ?? 0),
            ],
            'credito_utilizado' => $creditoUtilizado,
        ], JSON_UNESCAPED_UNICODE);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($tipo === 'pacote') {
        // Converte o identificador do pet selecionado para um inteiro válido.
        $petId = inteiroOuNull($_GET['pet_id'] ?? null);
        // Converte o identificador do serviço selecionado para um inteiro válido.
        $servicoId = inteiroOuNull($_GET['servico_id'] ?? null);
        // Armazena a data "data" usada nas validações e consultas desta página.
        $data = (string) ($_GET['data'] ?? date('Y-m-d'));
        // Guarda o ID do fechamento diário referente à data selecionada.
        $fechamentoId = inteiroOuNull($_GET['fechamento_id'] ?? null);
        // Armazena em "ignorarRegistro" o valor necessário para as próximas etapas deste fluxo.
        $ignorarRegistro = trim((string) ($_GET['ignorar_registro'] ?? ''));

        // Verifica a condição abaixo antes de executar este bloco.
        if ($petId === null || $servicoId === null || !dataSqlValida($data)) {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // Armazena em "idsIgnorar" o valor necessário para as próximas etapas deste fluxo.
        $idsIgnorar = [];

        // Verifica a condição abaixo antes de executar este bloco.
        if ($ignorarRegistro !== '' && $fechamentoId !== null) {
            // Verifica a condição abaixo antes de executar este bloco.
            if (preg_match('/^a:(\d+)$/', $ignorarRegistro, $partes)) {
                // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM lancamentos_diarios
                     WHERE fechamento_diario_id = :fechamento
                       AND atendimento_id = :atendimento'
                );
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute([
                    ':fechamento' => $fechamentoId,
                    ':atendimento' => (int) $partes[1],
                ]);
                // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
                $idsIgnorar = array_map('intval', array_column($stmt->fetchAll(), 'id'));
            // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
            } elseif (preg_match('/^l:(\d+)$/', $ignorarRegistro, $partes)) {
                // Armazena em "idsIgnorar" o valor necessário para as próximas etapas deste fluxo.
                $idsIgnorar = [(int) $partes[1]];
            }
        }

        // Armazena a coleção "joinLancamentos" carregada para ser percorrida e exibida/processada a seguir.
        $joinLancamentos = 'LEFT JOIN lancamentos_diarios l ON l.pet_pacote_id = pp.id';

        // Verifica a condição abaixo antes de executar este bloco.
        if ($idsIgnorar) {
            $joinLancamentos .= ' AND l.id NOT IN (' . implode(',', $idsIgnorar) . ')';
        }

        // Prepara uma consulta em "pet_pacotes" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            "SELECT
                pp.id,
                pa.nome AS pacote,
                pp.quantidade_creditos,
                COALESCE(SUM(l.creditos_pacote_usados), 0) AS usados
             FROM pet_pacotes pp
             JOIN pacotes pa ON pa.id = pp.pacote_id
             $joinLancamentos
             WHERE pp.status IN ('ATIVO', 'ESGOTADO')
               AND pp.pet_id = :pet_id
               AND pa.servico_id = :servico_id
               AND pp.data_compra <= :data_compra
               AND (pp.data_validade IS NULL OR pp.data_validade >= :data_validade)
             GROUP BY pp.id, pa.nome, pp.quantidade_creditos
             HAVING pp.quantidade_creditos - COALESCE(SUM(l.creditos_pacote_usados), 0) > 0
             ORDER BY pa.nome"
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([
            ':pet_id' => $petId,
            ':servico_id' => $servicoId,
            ':data_compra' => $data,
            ':data_validade' => $data,
        ]);

        // Armazena o resultado final da operação ou consulta que será devolvido ou exibido pela página.
        $resultado = [];
        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($stmt->fetchAll() as $item) {
            // Armazena a coleção "restantes" carregada para ser percorrida e exibida/processada a seguir.
            $restantes = (int) $item['quantidade_creditos'] - (int) $item['usados'];
            $resultado[] = [
                'id' => (int) $item['id'],
                'label' => (string) $item['pacote'],
                'remaining' => $restantes,
            ];
        }

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    http_response_code(400);
    echo json_encode(['erro' => 'Tipo de busca inválido.'], JSON_UNESCAPED_UNICODE);
// Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
} catch (Throwable $erro) {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível realizar a busca.'], JSON_UNESCAPED_UNICODE);
}
