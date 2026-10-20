<?php
/**
 * ARQUIVO: fechamento-diario.php
 * RESPONSABILIDADE: Fechamento diário e lançamentos financeiros.
 * Controla os atendimentos do dia, múltiplos serviços, adicionais, pacotes, pagamentos, créditos, débitos, edição de linhas e finalização/reabertura do caixa.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Recupera os dados do usuário autenticado para saber quem está operando o sistema.
$usuarioAtual = obterUsuarioAtual($pdo);
// Guarda somente o ID do usuário atual para registrar autoria das operações no banco.
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;
// Indica se o usuário possui perfil de administrador e, portanto, acesso às funções restritas.
$administrador = ($usuarioAtual['perfil'] ?? '') === 'ADMINISTRADOR';

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
$abrirModalFinalizacao = false;
// Define a data que a página irá consultar/exibir, usando a data recebida na URL ou o dia atual.
$dataSelecionada = (string) ($_GET['data'] ?? date('Y-m-d'));

// Confere se a data recebida pela URL é válida; se não for, a página volta para a data atual.
if (!dataSqlValida($dataSelecionada)) {
    // Define a data que a página irá consultar/exibir, usando a data recebida na URL ou o dia atual.
    $dataSelecionada = date('Y-m-d');
}

/**
 * Converte um valor recebido do formulário para número decimal seguro, usando zero quando o campo vier vazio.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function valorFechamento(mixed $valor): float
{
    // Verifica a condição abaixo antes de executar este bloco.
    if ($valor === null || $valor === '') {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 0.0;
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return max(0, round((float) str_replace(',', '.', (string) $valor), 2));
}

/**
 * Agrupa o serviço em uma categoria resumida usada nas colunas e totais do fechamento.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function categoriaFechamento(string $categoria, string $servico = ''): string
{
    // Armazena em "texto" o valor necessário para as próximas etapas deste fluxo.
    $texto = normalizarCategoriaAgenda($categoria . ' ' . $servico);

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($texto, 'creche') || str_contains($texto, 'hotel')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'creche_hotel';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($texto, 'veterin') || str_contains($texto, 'consulta')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'vet';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($texto, 'taxi')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'taxidog';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($texto, 'loja')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'loja';
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return 'banho_tosa';
}


/**
 * Localiza todos os lançamentos financeiros que pertencem à mesma linha visual do fechamento.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function idsLancamentosRegistroFechamento(PDO $pdo, int $fechamentoId, string $registro): array
{
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

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (preg_match('/^l:(\d+)$/', $registro, $partes)) {
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

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return $id ? [(int) $id] : [];
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [];
}

/**
 * Descobre qual atendimento está ligado ao conjunto de lançamentos informado.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function atendimentoDoRegistroFechamento(PDO $pdo, array $lancamentoIds): ?int
{
    // Verifica a condição abaixo antes de executar este bloco.
    if (!$lancamentoIds) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return null;
    }

    // Monta os marcadores necessários para usar uma quantidade variável de IDs em uma consulta preparada.
    $placeholders = implode(',', array_fill(0, count($lancamentoIds), '?'));
    // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "SELECT atendimento_id
         FROM lancamentos_diarios
         WHERE id IN ($placeholders)
           AND atendimento_id IS NOT NULL
         LIMIT 1"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute($lancamentoIds);

    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    $id = $stmt->fetchColumn();

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $id !== false && $id !== null ? (int) $id : null;
}

/**
 * Gera a quantidade correta de placeholders ? para consultas SQL que recebem vários IDs.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function placeholdersIds(array $ids): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return implode(',', array_fill(0, count($ids), '?'));
}

/**
 * Calcula o saldo atual de crédito do tutor a partir das movimentações registradas.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function saldoCreditoTutorFechamento(PDO $pdo, int $clienteId): float
{
    // Prepara uma consulta em "movimentacoes_cliente" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(
            CASE
                WHEN tipo_saldo = 'CREDITO' AND operacao = 'ENTRADA' THEN valor
                WHEN tipo_saldo = 'CREDITO' AND operacao = 'SAIDA' THEN -valor
                ELSE 0
            END
        ), 0)
        FROM movimentacoes_cliente
        WHERE cliente_id = :cliente_id"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':cliente_id' => $clienteId]);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return (float) $stmt->fetchColumn();
}

/**
 * Calcula quanto o tutor ainda deve, considerando entradas e baixas de débito.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function saldoDebitoTutorFechamento(PDO $pdo, int $clienteId): float
{
    // Prepara uma consulta em "movimentacoes_cliente" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(
            CASE
                WHEN tipo_saldo = 'DEBITO' AND operacao = 'ENTRADA' THEN valor
                WHEN tipo_saldo = 'DEBITO' AND operacao = 'SAIDA' THEN -valor
                ELSE 0
            END
        ), 0)
        FROM movimentacoes_cliente
        WHERE cliente_id = :cliente_id"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':cliente_id' => $clienteId]);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return max(0, (float) $stmt->fetchColumn());
}

/**
 * Identifica o serviço financeiro especial utilizado apenas para pagamento de dívida anterior.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function ehPagamentoDebitoFechamento(array $servico): bool
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return normalizarCategoriaAgenda((string) ($servico['categoria'] ?? '')) === 'financeiro'
        && normalizarCategoriaAgenda((string) ($servico['nome'] ?? '')) === 'pagar debito';
}

/**
 * Atualiza o status do pacote para ativo, vencido ou esgotado conforme validade e créditos utilizados.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function recalcularStatusPacoteFechamento(PDO $pdo, int $petPacoteId, string $dataReferencia): void
{
    // Prepara uma consulta em "pet_pacotes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "SELECT
            pp.id,
            pp.status,
            pp.data_validade,
            pp.quantidade_creditos,
            COALESCE(SUM(l.creditos_pacote_usados), 0) AS usados
         FROM pet_pacotes pp
         LEFT JOIN lancamentos_diarios l ON l.pet_pacote_id = pp.id
         WHERE pp.id = :id
         GROUP BY
            pp.id,
            pp.status,
            pp.data_validade,
            pp.quantidade_creditos"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $petPacoteId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $pacote = $stmt->fetch();

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$pacote || $pacote['status'] === 'CANCELADO') {
        return;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (!empty($pacote['data_validade']) && $pacote['data_validade'] < $dataReferencia) {
        // Guarda o estado atual ou o novo estado do registro que será exibido ou gravado.
        $status = 'VENCIDO';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ((int) $pacote['usados'] >= (int) $pacote['quantidade_creditos']) {
        // Guarda o estado atual ou o novo estado do registro que será exibido ou gravado.
        $status = 'ESGOTADO';
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else {
        // Guarda o estado atual ou o novo estado do registro que será exibido ou gravado.
        $status = 'ATIVO';
    }

    // Prepara uma consulta em "pacotes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare('UPDATE pet_pacotes SET status = :status WHERE id = :id');
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([
        ':status' => $status,
        ':id' => $petPacoteId,
    ]);
}

/**
 * Cria/reutiliza um modelo de pacote e registra a contratação de um novo pacote para o pet.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function iniciarPacotePetFechamento(
    PDO $pdo,
    int $petId,
    int $servicoId,
    string $nomeServico,
    int $quantidade,
    ?int $validadeDias,
    string $dataCompra
): int {
    // Guarda a quantidade informada e usada para vagas, créditos, itens ou repetições.
    $quantidade = max(1, min(100, $quantidade));
    // Armazena a coleção "validadeDias" carregada para ser percorrida e exibida/processada a seguir.
    $validadeDias = $validadeDias !== null ? max(1, min(3650, $validadeDias)) : null;

    // Armazena em "nomePacote" o valor necessário para as próximas etapas deste fluxo.
    $nomePacote = 'Pacote ' . $quantidade . 'x ' . $nomeServico;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($validadeDias !== null) {
        $nomePacote .= ' - ' . $validadeDias . ' dias';
    }

    // Prepara uma consulta em "pacotes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT id
         FROM pacotes
         WHERE servico_id = :servico_id
           AND nome = :nome
         LIMIT 1'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([
        ':servico_id' => $servicoId,
        ':nome' => $nomePacote,
    ]);
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    $pacoteId = $stmt->fetchColumn();

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$pacoteId) {
        // Prepara a inclusão de um novo registro em "pacotes" usando os valores já validados.
        $stmt = $pdo->prepare(
            'INSERT INTO pacotes (
                servico_id,
                nome,
                quantidade_creditos,
                validade_dias,
                ativo
             ) VALUES (
                :servico_id,
                :nome,
                :quantidade,
                :validade,
                TRUE
             )'
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([
            ':servico_id' => $servicoId,
            ':nome' => $nomePacote,
            ':quantidade' => $quantidade,
            ':validade' => $validadeDias,
        ]);

        // Guarda o ID do modelo de pacote que será utilizado ou criado.
        $pacoteId = (int) $pdo->lastInsertId();
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else {
        // Guarda o ID do modelo de pacote que será utilizado ou criado.
        $pacoteId = (int) $pacoteId;
    }

    // Calcula ou guarda a data limite até a qual o pacote poderá ser utilizado.
    $dataValidade = null;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($validadeDias !== null) {
        // Calcula ou guarda a data limite até a qual o pacote poderá ser utilizado.
        $dataValidade = (new DateTime($dataCompra))
            ->modify('+' . $validadeDias . ' days')
            ->format('Y-m-d');
    }

    // Prepara a inclusão de um novo registro em "pet_pacotes" usando os valores já validados.
    $stmt = $pdo->prepare(
        "INSERT INTO pet_pacotes (
            pet_id,
            pacote_id,
            data_compra,
            data_validade,
            quantidade_creditos,
            status,
            observacoes
         ) VALUES (
            :pet_id,
            :pacote_id,
            :data_compra,
            :data_validade,
            :quantidade,
            'ATIVO',
            :observacoes
         )"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([
        ':pet_id' => $petId,
        ':pacote_id' => $pacoteId,
        ':data_compra' => $dataCompra,
        ':data_validade' => $dataValidade,
        ':quantidade' => $quantidade,
        ':observacoes' => 'Pacote iniciado pelo fechamento diário',
    ]);

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return (int) $pdo->lastInsertId();
}

/**
 * Confere se o pacote pertence ao pet/serviço e se ainda possui créditos suficientes.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function validarUsoPacoteFechamento(
    PDO $pdo,
    int $petPacoteId,
    int $petId,
    int $servicoId,
    int $creditos
): array {
    // Prepara uma consulta em "pet_pacotes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "SELECT
            pp.id,
            pp.pet_id,
            pp.quantidade_creditos,
            pp.status,
            pp.data_validade,
            pa.servico_id,
            COALESCE(SUM(l.creditos_pacote_usados), 0) AS usados
         FROM pet_pacotes pp
         JOIN pacotes pa ON pa.id = pp.pacote_id
         LEFT JOIN lancamentos_diarios l ON l.pet_pacote_id = pp.id
         WHERE pp.id = :id
         GROUP BY
            pp.id,
            pp.pet_id,
            pp.quantidade_creditos,
            pp.status,
            pp.data_validade,
            pa.servico_id"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $petPacoteId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $pacote = $stmt->fetch();

    // Armazena a coleção "restantes" carregada para ser percorrida e exibida/processada a seguir.
    $restantes = $pacote
        ? (int) $pacote['quantidade_creditos'] - (int) $pacote['usados']
        : 0;

    // Armazena em "valido" o valor necessário para as próximas etapas deste fluxo.
    $valido = $pacote
        && in_array($pacote['status'], ['ATIVO', 'ESGOTADO'], true)
        && (int) $pacote['pet_id'] === $petId
        && (int) $pacote['servico_id'] === $servicoId
        && $creditos > 0
        && $creditos <= $restantes;

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [$valido, $restantes];
}

/**
 * Remove pagamentos e movimentos financeiros ligados aos lançamentos que serão refeitos durante uma edição.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function apagarFinanceiroLancamentos(PDO $pdo, int $fechamentoId, array $lancamentoIds): void
{
    // Verifica a condição abaixo antes de executar este bloco.
    if (!$lancamentoIds) {
        return;
    }

    // Monta os marcadores necessários para usar uma quantidade variável de IDs em uma consulta preparada.
    $placeholders = placeholdersIds($lancamentoIds);

    // Prepara a remoção do registro correspondente em "pagamentos" conforme a ação solicitada.
    $stmt = $pdo->prepare(
        "DELETE FROM pagamentos
         WHERE fechamento_diario_id = ?
           AND lancamento_diario_id IN ($placeholders)"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute(array_merge([$fechamentoId], $lancamentoIds));

    // Prepara a inclusão de um novo registro em "pagamentos" usando os valores já validados.
    $stmt = $pdo->prepare(
        "DELETE FROM movimentacoes_cliente
         WHERE fechamento_diario_id = ?
           AND lancamento_diario_id IN ($placeholders)"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute(array_merge([$fechamentoId], $lancamentoIds));
}

/**
 * Executa a responsabilidade específica de "pagamentosPostFechamento" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function pagamentosPostFechamento(array $post): array
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return [
        'dinheiro' => valorFechamento($post['pagamento_dinheiro'] ?? 0),
        'pix' => valorFechamento($post['pagamento_pix'] ?? 0),
        'cartao' => valorFechamento($post['pagamento_cartao'] ?? 0),
    ];
}

/**
 * Executa a responsabilidade específica de "inserirFinanceiroRegistroFechamento" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */

/**
 * Calcula os números que devem ser conferidos antes de encerrar o dia.
 *
 * A contagem usa pets únicos por serviço. Assim, se o mesmo pet tiver dois
 * lançamentos do mesmo serviço, ele não infla a quantidade conferida.
 * Os pagamentos são lidos diretamente da tabela "pagamentos".
 */
function resumoConferenciaFinalFechamento(
    PDO $pdo,
    int $fechamentoId,
    float $valorAbertura
): array {
    $petsPorGrupo = [
        'banho' => [],
        'banho_tosa' => [],
        'higienica' => [],
        'creche' => [],
        'hotel' => [],
    ];

    $stmt = $pdo->prepare(
        "SELECT DISTINCT
            l.pet_id,
            s.nome AS servico,
            cs.nome AS categoria
         FROM lancamentos_diarios l
         JOIN servicos s ON s.id = l.servico_id
         JOIN categorias_servico cs ON cs.id = s.categoria_id
         WHERE l.fechamento_diario_id = :fechamento
           AND LOWER(cs.nome) <> 'financeiro'"
    );
    $stmt->execute([':fechamento' => $fechamentoId]);

    foreach ($stmt->fetchAll() as $linha) {
        $petId = (int) $linha['pet_id'];
        $servico = normalizarCategoriaAgenda((string) ($linha['servico'] ?? ''));
        $categoria = normalizarCategoriaAgenda((string) ($linha['categoria'] ?? ''));
        $texto = trim($categoria . ' ' . $servico);

        if (str_contains($texto, 'creche')) {
            $petsPorGrupo['creche'][$petId] = true;
            continue;
        }

        if (str_contains($texto, 'hotel')) {
            $petsPorGrupo['hotel'][$petId] = true;
            continue;
        }

        if (str_contains($servico, 'higien')) {
            $petsPorGrupo['higienica'][$petId] = true;
            continue;
        }

        if (str_contains($servico, 'banho') && str_contains($servico, 'tosa')) {
            $petsPorGrupo['banho_tosa'][$petId] = true;
            continue;
        }

        if (str_contains($servico, 'banho')) {
            $petsPorGrupo['banho'][$petId] = true;
        }
    }

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE
                WHEN LOWER(fp.nome) = 'dinheiro' THEN p.valor
                ELSE 0
            END), 0) AS dinheiro,
            COALESCE(SUM(CASE
                WHEN LOWER(fp.nome) = 'pix' THEN p.valor
                ELSE 0
            END), 0) AS pix,
            COALESCE(SUM(CASE
                WHEN fp.nome IN ('Cartão', 'Cartão de débito', 'Cartão de crédito')
                    THEN p.valor
                ELSE 0
            END), 0) AS cartao
         FROM pagamentos p
         JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
         WHERE p.estornado = FALSE
           AND p.fechamento_diario_id = :fechamento"
    );
    $stmt->execute([':fechamento' => $fechamentoId]);
    $pagamentos = $stmt->fetch() ?: [];

    return [
        'banho' => count($petsPorGrupo['banho']),
        'banho_tosa' => count($petsPorGrupo['banho_tosa']),
        'higienica' => count($petsPorGrupo['higienica']),
        'creche' => count($petsPorGrupo['creche']),
        'hotel' => count($petsPorGrupo['hotel']),
        'dinheiro' => round(
            $valorAbertura + (float) ($pagamentos['dinheiro'] ?? 0),
            2
        ),
        'pix' => round((float) ($pagamentos['pix'] ?? 0), 2),
        'cartao' => round((float) ($pagamentos['cartao'] ?? 0), 2),
    ];
}

function inserirFinanceiroRegistroFechamento(
    PDO $pdo,
    int $fechamentoId,
    int $clienteId,
    int $lancamentoPrincipalId,
    array $pagamentos,
    float $creditoUtilizado,
    array $formasPorNome,
    ?int $usuarioId,
    string $dataSelecionada
): void {
    // Armazena em "stmtPagamento" o valor necessário para as próximas etapas deste fluxo.
    $stmtPagamento = $pdo->prepare(
        'INSERT INTO pagamentos (
            fechamento_diario_id,
            cliente_id,
            lancamento_diario_id,
            forma_pagamento_id,
            data_hora_pagamento,
            valor,
            recebido_por
         ) VALUES (
            :fechamento,
            :cliente,
            :lancamento,
            :forma,
            :data_hora,
            :valor,
            :usuario
         )'
    );

    // Percorre cada item da coleção para processar os registros individualmente.
    foreach ($pagamentos as $nomeForma => $valorPagamento) {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($valorPagamento <= 0) {
            continue;
        }

        // Armazena o identificador "formaId" utilizado para relacionar ou localizar o registro no banco.
        $formaId = $formasPorNome[$nomeForma] ?? null;

        // Verifica a condição abaixo antes de executar este bloco.
        if ($formaId === null) {
            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
            throw new RuntimeException('Forma de pagamento não encontrada no banco.');
        }

        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmtPagamento->execute([
            ':fechamento' => $fechamentoId,
            ':cliente' => $clienteId,
            ':lancamento' => $lancamentoPrincipalId,
            ':forma' => $formaId,
            ':data_hora' => $dataSelecionada . ' ' . date('H:i:s'),
            ':valor' => $valorPagamento,
            ':usuario' => $usuarioId,
        ]);
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($creditoUtilizado > 0) {
        // Prepara a inclusão de um novo registro em "movimentacoes_cliente" usando os valores já validados.
        $stmt = $pdo->prepare(
            "INSERT INTO movimentacoes_cliente (
                fechamento_diario_id,
                cliente_id,
                lancamento_diario_id,
                tipo_saldo,
                operacao,
                valor,
                data_hora_movimentacao,
                descricao,
                criado_por
             ) VALUES (
                :fechamento,
                :cliente,
                :lancamento,
                'CREDITO',
                'SAIDA',
                :valor,
                :data_hora,
                :descricao,
                :usuario
             )"
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([
            ':fechamento' => $fechamentoId,
            ':cliente' => $clienteId,
            ':lancamento' => $lancamentoPrincipalId,
            ':valor' => $creditoUtilizado,
            ':data_hora' => $dataSelecionada . ' ' . date('H:i:s'),
            ':descricao' => 'Crédito utilizado no fechamento diário',
            ':usuario' => $usuarioId,
        ]);
    }
}

// O fechamento é criado automaticamente na primeira vez que a data é aberta.
$stmt = $pdo->prepare(
    "INSERT INTO fechamentos_diarios (
        data_fechamento,
        status,
        valor_abertura_caixa,
        aberto_por
    ) VALUES (
        :data_fechamento,
        'ABERTO',
        0,
        :aberto_por
    )
    ON CONFLICT (data_fechamento) DO NOTHING"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([
    ':data_fechamento' => $dataSelecionada,
    ':aberto_por' => $usuarioId,
]);

// Prepara uma consulta em "fechamentos_diarios" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare('SELECT * FROM fechamentos_diarios WHERE data_fechamento = :data LIMIT 1');
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':data' => $dataSelecionada]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$fechamento = $stmt->fetch();
// Guarda o ID do fechamento diário referente à data selecionada.
$fechamentoId = (int) $fechamento['id'];
// Verifica se o fechamento já foi encerrado para impedir alterações que exigem o caixa aberto.
$fechamentoFechado = $fechamento['status'] === 'FECHADO';

// 1. Garante a existência da categoria "Financeiro"
$stmtCat = $pdo->query("SELECT id FROM categorias_servico WHERE nome = 'Financeiro'");
$categoriaId = $stmtCat ? $stmtCat->fetchColumn() : false;

if (!$categoriaId) {
    $pdo->exec("INSERT INTO categorias_servico (nome, ativo) VALUES ('Financeiro', TRUE)");
    $categoriaId = $pdo->lastInsertId();
} else {
    $pdo->exec("UPDATE categorias_servico SET ativo = TRUE WHERE id = " . (int)$categoriaId);
}

// 2. Garante a existência do serviço "Pagar débito"
$stmtServ = $pdo->prepare("SELECT id FROM servicos WHERE nome = 'Pagar débito' AND categoria_id = :cat_id");
$stmtServ->execute([':cat_id' => $categoriaId]);
$servicoId = $stmtServ->fetchColumn();

if (!$servicoId) {
    $stmtIns = $pdo->prepare("INSERT INTO servicos (categoria_id, nome, descricao, aceita_pacote, ativo) VALUES (:cat_id, 'Pagar débito', 'Recebimento de débito antigo do tutor', FALSE, TRUE)");
    $stmtIns->execute([':cat_id' => $categoriaId]);
} else {
    $stmtUpd = $pdo->prepare("UPDATE servicos SET ativo = TRUE, aceita_pacote = FALSE WHERE id = :serv_id");
    $stmtUpd->execute([':serv_id' => $servicoId]);
}
// Armazena o identificador "servicoPagarDebitoId" utilizado para relacionar ou localizar o registro no banco.
$servicoPagarDebitoId = (int) $pdo->query(
    "SELECT s.id
     FROM servicos s
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     WHERE cs.nome = 'Financeiro'
       AND s.nome = 'Pagar débito'
     LIMIT 1"
// Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
)->fetchColumn();

// A partir desta versão, crédito e débito aparecem apenas como "Cartão".
// Os registros antigos continuam válidos e entram normalmente nos totais.
$pdo->exec(
    "INSERT INTO formas_pagamento (nome, ativo)
     VALUES ('Cartão', TRUE)
     ON CONFLICT (nome) DO UPDATE SET ativo = TRUE"
);
$pdo->exec(
    "UPDATE formas_pagamento
     SET ativo = FALSE
     WHERE nome IN ('Cartão de débito', 'Cartão de crédito')"
);

// Armazena em "formasPagamento" o valor necessário para as próximas etapas deste fluxo.
$formasPagamento = $pdo->query(
    'SELECT id, nome FROM formas_pagamento WHERE ativo = TRUE ORDER BY id'
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
)->fetchAll();

// Armazena em "formasPorNome" o valor necessário para as próximas etapas deste fluxo.
$formasPorNome = [];
// Percorre cada item da coleção para processar os registros individualmente.
foreach ($formasPagamento as $forma) {
    $formasPorNome[normalizarCategoriaAgenda((string) $forma['nome'])] = (int) $forma['id'];
}

$conferenciaFechamento = resumoConferenciaFinalFechamento(
    $pdo,
    $fechamentoId,
    (float) ($fechamento['valor_abertura_caixa'] ?? 0)
);

// Armazena em "tiposAdicional" o valor necessário para as próximas etapas deste fluxo.
$tiposAdicional = $pdo->query(
    'SELECT id, nome FROM tipos_adicional WHERE ativo = TRUE ORDER BY nome'
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
)->fetchAll();

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação o formulário solicitou para direcionar o processamento correto.
    $acao = (string) ($_POST['acao'] ?? '');

    // Verifica se o fechamento já foi encerrado para impedir alterações que exigem o caixa aberto.
    if ($fechamentoFechado && !in_array($acao, ['reabrir_fechamento'], true)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Esse fechamento já foi finalizado. Reabra o dia para fazer alterações.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    }

    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    elseif ($acao === 'salvar_abertura') {
        // Armazena o valor financeiro "valorAbertura" já normalizado para os cálculos seguintes.
        $valorAbertura = valorFechamento($_POST['valor_abertura_caixa'] ?? 0);

        // Prepara a atualização do registro correspondente em "fechamentos_diarios" com os novos dados.
        $stmt = $pdo->prepare(
            'UPDATE fechamentos_diarios
             SET valor_abertura_caixa = :valor
             WHERE id = :id'
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([
            ':valor' => $valorAbertura,
            ':id' => $fechamentoId,
        ]);

        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: fechamento-diario.php?data=' . urlencode($dataSelecionada) . '&abertura=1');
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    elseif ($acao === 'salvar_registro') {
        // Armazena em "registro" o valor necessário para as próximas etapas deste fluxo.
        $registro = trim((string) ($_POST['registro'] ?? ''));
        // Converte o identificador do pet selecionado para um inteiro válido.
        $petId = inteiroOuNull($_POST['pet_id'] ?? null);
        // Guarda quanto do saldo de crédito do tutor será usado para abater o atendimento.
        $creditoUtilizado = valorFechamento($_POST['credito_utilizado'] ?? 0);
        // Reúne os pagamentos reais vinculados aos lançamentos para calcular os totais por forma de pagamento.
        $pagamentosRecebidos = pagamentosPostFechamento($_POST);
        // Armazena em "servicosPost" o valor necessário para as próximas etapas deste fluxo.
        $servicosPost = is_array($_POST['servicos'] ?? null) ? $_POST['servicos'] : [];
        // Armazena em "idsServicosPost" o valor necessário para as próximas etapas deste fluxo.
        $idsServicosPost = [];

        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($servicosPost as $servicoPostTemporario) {
            // Verifica a condição abaixo antes de executar este bloco.
            if (is_array($servicoPostTemporario)) {
                // Armazena em "idTemporario" o valor necessário para as próximas etapas deste fluxo.
                $idTemporario = inteiroOuNull($servicoPostTemporario['servico_id'] ?? null);
                // Verifica a condição abaixo antes de executar este bloco.
                if ($idTemporario !== null) {
                    $idsServicosPost[] = $idTemporario;
                }
            }
        }

        // Armazena em "pagamentoDebito" o valor necessário para as próximas etapas deste fluxo.
        $pagamentoDebito = in_array($servicoPagarDebitoId, $idsServicosPost, true);

        // Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            'SELECT p.id, p.cliente_id, p.nome, c.nome AS tutor
             FROM pets p
             JOIN clientes c ON c.id = p.cliente_id
             WHERE p.id = :id
               AND p.ativo = TRUE
               AND c.ativo = TRUE'
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([':id' => $petId]);
        // Obtém a próxima linha retornada pela consulta como um registro associativo.
        $pet = $stmt->fetch();

        // Verifica a condição abaixo antes de executar este bloco.
        if (!$pet) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Selecione um pet válido.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
        } elseif (!$servicosPost) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Adicione pelo menos um serviço ao registro.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
        } elseif ($pagamentoDebito && count($idsServicosPost) !== 1) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Pagar débito deve ficar sozinho na linha, sem outros serviços.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Calcula/armazena o total de "totalInformado" que será usado nos resumos e na interface.
            $totalInformado = 0.0;

            // Percorre cada item da coleção para processar os registros individualmente.
            foreach ($servicosPost as $servicoPost) {
                // Verifica a condição abaixo antes de executar este bloco.
                if (!is_array($servicoPost)) {
                    continue;
                }

                // Armazena em "idServicoAtual" o valor necessário para as próximas etapas deste fluxo.
                $idServicoAtual = inteiroOuNull($servicoPost['servico_id'] ?? null);

                // Verifica a condição abaixo antes de executar este bloco.
                if ($idServicoAtual === $servicoPagarDebitoId) {
                    continue;
                }

                $totalInformado += valorFechamento($servicoPost['valor_servico'] ?? 0);

                // Percorre cada item da coleção para processar os registros individualmente.
                foreach (($servicoPost['adicionais'] ?? []) as $adicionalPost) {
                    // Verifica a condição abaixo antes de executar este bloco.
                    if (is_array($adicionalPost)) {
                        $totalInformado += valorFechamento($adicionalPost['valor'] ?? 0);
                    }
                }
            }

            // Soma tudo o que foi efetivamente pago para comparar com o valor cobrado.
            $totalRecebido = round(array_sum($pagamentosRecebidos), 2);
            // Soma pagamentos e créditos utilizados para verificar quanto do atendimento já foi quitado.
            $valorCoberto = round($totalRecebido + $creditoUtilizado, 2);

            // Verifica a condição abaixo antes de executar este bloco.
            if ($pagamentoDebito) {
                // Verifica a condição abaixo antes de executar este bloco.
                if ($creditoUtilizado > 0.009) {
                    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                    $mensagem = 'Para pagar débito, use Dinheiro, Pix ou Cartão. Crédito do tutor não é usado nessa operação.';
                    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                    $tipoMensagem = 'error';
                // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
                } elseif ($totalRecebido <= 0) {
                    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                    $mensagem = 'Informe quanto o tutor está pagando do débito.';
                    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                    $tipoMensagem = 'error';
                }
            // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
            } elseif ($valorCoberto > $totalInformado + 0.009) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'O valor recebido mais o crédito utilizado não pode ultrapassar o total do registro.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            }
        }

        // Só continua para a próxima etapa quando nenhuma validação anterior gerou mensagem de erro.
        if ($mensagem === '' && $registro === '') {
            // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM (
                    SELECT
                        CASE
                            WHEN atendimento_id IS NOT NULL
                                THEN CONCAT('a:', atendimento_id)
                            ELSE CONCAT('l:', id)
                        END AS registro
                    FROM lancamentos_diarios
                    WHERE fechamento_diario_id = :fechamento
                    GROUP BY registro
                 ) registros_dia"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':fechamento' => $fechamentoId]);

            // Verifica a condição abaixo antes de executar este bloco.
            if ((int) $stmt->fetchColumn() >= 80) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Este fechamento já atingiu as 80 linhas do dia.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            }
        }

        // Só continua para a próxima etapa quando nenhuma validação anterior gerou mensagem de erro.
        if ($mensagem === '') {
            // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
            try {
                // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
                $pdo->beginTransaction();

                // Armazena a coleção "idsAntigos" carregada para ser percorrida e exibida/processada a seguir.
                $idsAntigos = $registro !== ''
                    ? idsLancamentosRegistroFechamento($pdo, $fechamentoId, $registro)
                    : [];

                // Verifica a condição abaixo antes de executar este bloco.
                if ($registro !== '' && !$idsAntigos) {
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('O registro selecionado não foi encontrado.');
                }

                // Guarda o ID do atendimento usado para agrupar vários serviços na mesma linha do fechamento.
                $atendimentoId = $idsAntigos
                    ? atendimentoDoRegistroFechamento($pdo, $idsAntigos)
                    : null;

                // Reúne os pacotes que tiveram consumo alterado para recalcular seus status ao final da operação.
                $pacotesAfetados = [];

                // Verifica a condição abaixo antes de executar este bloco.
                if ($idsAntigos) {
                    // Monta os marcadores necessários para usar uma quantidade variável de IDs em uma consulta preparada.
                    $placeholders = placeholdersIds($idsAntigos);
                    // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
                    $stmt = $pdo->prepare(
                        "SELECT DISTINCT pet_pacote_id
                         FROM lancamentos_diarios
                         WHERE id IN ($placeholders)
                           AND pet_pacote_id IS NOT NULL"
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute($idsAntigos);
                    // Reúne os pacotes que tiveram consumo alterado para recalcular seus status ao final da operação.
                    $pacotesAfetados = array_map(
                        'intval',
                        // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
                        array_column($stmt->fetchAll(), 'pet_pacote_id')
                    );

                    apagarFinanceiroLancamentos($pdo, $fechamentoId, $idsAntigos);

                    // Prepara a inclusão de um novo registro em "atendimentos" usando os valores já validados.
                    $stmt = $pdo->prepare(
                        "DELETE FROM lancamentos_diarios
                         WHERE id IN ($placeholders)"
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute($idsAntigos);
                }

                // Verifica a condição abaixo antes de executar este bloco.
                if ($atendimentoId === null) {
                    // Prepara a inclusão de um novo registro em "atendimentos" usando os valores já validados.
                    $stmt = $pdo->prepare(
                        "INSERT INTO atendimentos (
                            pet_id,
                            data_hora_entrada,
                            status,
                            observacoes_gerais,
                            criado_por
                         ) VALUES (
                            :pet_id,
                            :entrada,
                            'ABERTO',
                            :observacoes,
                            :usuario
                         )"
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute([
                        ':pet_id' => (int) $pet['id'],
                        ':entrada' => $dataSelecionada . ' ' . date('H:i:s'),
                        ':observacoes' => 'Registro criado no fechamento diário',
                        ':usuario' => $usuarioId,
                    ]);

                    // Guarda o ID do atendimento usado para agrupar vários serviços na mesma linha do fechamento.
                    $atendimentoId = (int) $pdo->lastInsertId();
                // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                } else {
                    // Prepara a atualização do registro correspondente em "atendimentos" com os novos dados.
                    $stmt = $pdo->prepare(
                        'UPDATE atendimentos
                         SET pet_id = :pet_id,
                             data_hora_entrada = :entrada
                         WHERE id = :id'
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute([
                        ':pet_id' => (int) $pet['id'],
                        ':entrada' => $dataSelecionada . ' ' . date('H:i:s'),
                        ':id' => $atendimentoId,
                    ]);
                }

                // Armazena a coleção "lancamentosNovos" carregada para ser percorrida e exibida/processada a seguir.
                $lancamentosNovos = [];
                // Calcula o valor total da linha ou atendimento considerando serviços e adicionais relacionados.
                $totalRegistro = 0.0;

                // Percorre cada item da coleção para processar os registros individualmente.
                foreach ($servicosPost as $servicoPost) {
                    // Verifica a condição abaixo antes de executar este bloco.
                    if (!is_array($servicoPost)) {
                        continue;
                    }

                    // Converte o identificador do serviço selecionado para um inteiro válido.
                    $servicoId = inteiroOuNull($servicoPost['servico_id'] ?? null);
                    // Guarda o valor cobrado pelo serviço atual, já convertido para número decimal.
                    $valorServico = valorFechamento($servicoPost['valor_servico'] ?? 0);
                    // Armazena em "descricao" o valor necessário para as próximas etapas deste fluxo.
                    $descricao = textoOuNull($servicoPost['descricao'] ?? null);
                    // Armazena em "modo" o valor necessário para as próximas etapas deste fluxo.
                    $modo = (string) ($servicoPost['modo'] ?? 'avulso');

                    // Carrega os dados do serviço selecionado para validar categoria, regras e comportamento do formulário.
                    $servico = null;

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($servicoId !== null) {
                        // Prepara uma consulta em "servicos" para carregar os dados necessários a esta etapa.
                        $stmt = $pdo->prepare(
                            'SELECT
                                s.id,
                                s.nome,
                                s.aceita_pacote,
                                cs.nome AS categoria
                             FROM servicos s
                             JOIN categorias_servico cs ON cs.id = s.categoria_id
                             WHERE s.id = :id
                               AND s.ativo = TRUE
                               AND cs.ativo = TRUE
                             LIMIT 1'
                        );
                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                        $stmt->execute([':id' => $servicoId]);
                        // Obtém a próxima linha retornada pela consulta como um registro associativo.
                        $servico = $stmt->fetch() ?: null;
                    }

                    // Verifica a condição abaixo antes de executar este bloco.
                    if (!$servico) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException('Um dos serviços selecionados não é válido.');
                    }

                    // Armazena em "servicoEhPagamentoDebito" o valor necessário para as próximas etapas deste fluxo.
                    $servicoEhPagamentoDebito = ehPagamentoDebitoFechamento($servico);

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($servicoEhPagamentoDebito) {
                        // Guarda o valor cobrado pelo serviço atual, já convertido para número decimal.
                        $valorServico = 0.0;
                        // Armazena em "modo" o valor necessário para as próximas etapas deste fluxo.
                        $modo = 'avulso';
                        // Armazena em "descricao" o valor necessário para as próximas etapas deste fluxo.
                        $descricao = $descricao ?: 'Pagamento de débito do tutor';
                    }

                    // Armazena em "aceitaPacote" o valor necessário para as próximas etapas deste fluxo.
                    $aceitaPacote = !$servicoEhPagamentoDebito && (bool) $servico['aceita_pacote'];

                    // Guarda o ID do pacote contratado especificamente para este pet.
                    $petPacoteId = null;
                    // Guarda quantos créditos do pacote serão consumidos neste serviço.
                    $creditosPacote = 0;

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($modo === 'pacote') {
                        // Verifica a condição abaixo antes de executar este bloco.
                        if (!$aceitaPacote) {
                            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                            throw new RuntimeException('Esse serviço não aceita pacote.');
                        }

                        // Guarda o ID do pacote contratado especificamente para este pet.
                        $petPacoteId = inteiroOuNull($servicoPost['pet_pacote_id'] ?? null);
                        // Guarda quantos créditos do pacote serão consumidos neste serviço.
                        $creditosPacote = inteiroOuNull($servicoPost['creditos_pacote_usados'] ?? null) ?? 1;

                        // Verifica a condição abaixo antes de executar este bloco.
                        if ($petPacoteId === null) {
                            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                            throw new RuntimeException('Selecione o pacote ativo que deseja usar.');
                        }

                        [$pacoteValido] = validarUsoPacoteFechamento(
                            $pdo,
                            $petPacoteId,
                            (int) $pet['id'],
                            (int) $servicoId,
                            $creditosPacote
                        );

                        // Verifica a condição abaixo antes de executar este bloco.
                        if (!$pacoteValido) {
                            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                            throw new RuntimeException('Um dos pacotes selecionados não possui créditos disponíveis.');
                        }
                    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
                    } elseif ($modo === 'iniciar') {
                        // Verifica a condição abaixo antes de executar este bloco.
                        if (!$aceitaPacote) {
                            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                            throw new RuntimeException('Esse serviço não aceita pacote.');
                        }

                        // Armazena em "quantidadePacote" o valor necessário para as próximas etapas deste fluxo.
                        $quantidadePacote = inteiroOuNull($servicoPost['novo_pacote_quantidade'] ?? null) ?? 4;
                        // Armazena em "validadePacote" o valor necessário para as próximas etapas deste fluxo.
                        $validadePacote = inteiroOuNull($servicoPost['novo_pacote_validade'] ?? null);

                        // Guarda o ID do pacote contratado especificamente para este pet.
                        $petPacoteId = iniciarPacotePetFechamento(
                            $pdo,
                            (int) $pet['id'],
                            (int) $servicoId,
                            (string) $servico['nome'],
                            $quantidadePacote,
                            $validadePacote,
                            $dataSelecionada
                        );
                        // Guarda quantos créditos do pacote serão consumidos neste serviço.
                        $creditosPacote = 1;
                    }

                    // Prepara a inclusão de um novo registro em "lancamentos_diarios" usando os valores já validados.
                    $stmt = $pdo->prepare(
                        'INSERT INTO lancamentos_diarios (
                            fechamento_diario_id,
                            pet_id,
                            atendimento_id,
                            servico_id,
                            pet_pacote_id,
                            creditos_pacote_usados,
                            valor_servico,
                            descricao,
                            criado_por
                         ) VALUES (
                            :fechamento,
                            :pet,
                            :atendimento,
                            :servico,
                            :pacote,
                            :creditos,
                            :valor,
                            :descricao,
                            :usuario
                         )'
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute([
                        ':fechamento' => $fechamentoId,
                        ':pet' => (int) $pet['id'],
                        ':atendimento' => $atendimentoId,
                        ':servico' => (int) $servicoId,
                        ':pacote' => $petPacoteId,
                        ':creditos' => $creditosPacote,
                        ':valor' => $valorServico,
                        ':descricao' => $descricao,
                        ':usuario' => $usuarioId,
                    ]);

                    // Armazena o identificador "lancamentoId" utilizado para relacionar ou localizar o registro no banco.
                    $lancamentoId = (int) $pdo->lastInsertId();
                    $lancamentosNovos[] = $lancamentoId;

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($petPacoteId !== null) {
                        $pacotesAfetados[] = $petPacoteId;
                    }

                    // Calcula/armazena o total de "totalAdicionais" que será usado nos resumos e na interface.
                    $totalAdicionais = 0.0;
                    // Armazena em "adicionaisPost" o valor necessário para as próximas etapas deste fluxo.
                    $adicionaisPost = $servicoEhPagamentoDebito
                        ? []
                        : (is_array($servicoPost['adicionais'] ?? null)
                            ? $servicoPost['adicionais']
                            : []);

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($adicionaisPost) {
                        // Armazena em "stmtAdicional" o valor necessário para as próximas etapas deste fluxo.
                        $stmtAdicional = $pdo->prepare(
                            'INSERT INTO lancamento_adicionais (
                                lancamento_diario_id,
                                tipo_adicional_id,
                                descricao,
                                valor,
                                criado_por
                             ) VALUES (
                                :lancamento,
                                :tipo,
                                :descricao,
                                :valor,
                                :usuario
                             )'
                        );

                        // Percorre cada item da coleção para processar os registros individualmente.
                        foreach ($adicionaisPost as $adicionalPost) {
                            // Verifica a condição abaixo antes de executar este bloco.
                            if (!is_array($adicionalPost)) {
                                continue;
                            }

                            // Armazena o identificador "tipoAdicionalId" utilizado para relacionar ou localizar o registro no banco.
                            $tipoAdicionalId = inteiroOuNull($adicionalPost['tipo_id'] ?? null);
                            // Armazena o valor financeiro "valorAdicional" já normalizado para os cálculos seguintes.
                            $valorAdicional = valorFechamento($adicionalPost['valor'] ?? 0);
                            // Armazena em "descricaoAdicional" o valor necessário para as próximas etapas deste fluxo.
                            $descricaoAdicional = textoOuNull($adicionalPost['descricao'] ?? null);

                            // Verifica a condição abaixo antes de executar este bloco.
                            if ($tipoAdicionalId === null || $valorAdicional <= 0) {
                                continue;
                            }

                            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                            $stmtAdicional->execute([
                                ':lancamento' => $lancamentoId,
                                ':tipo' => $tipoAdicionalId,
                                ':descricao' => $descricaoAdicional,
                                ':valor' => $valorAdicional,
                                ':usuario' => $usuarioId,
                            ]);

                            $totalAdicionais += $valorAdicional;
                        }
                    }

                    $totalRegistro += $valorServico + $totalAdicionais;
                }

                // Verifica a condição abaixo antes de executar este bloco.
                if (!$lancamentosNovos) {
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('Adicione pelo menos um serviço ao registro.');
                }

                // Soma tudo o que foi efetivamente pago para comparar com o valor cobrado.
                $totalRecebido = round(array_sum($pagamentosRecebidos), 2);
                // Soma pagamentos e créditos utilizados para verificar quanto do atendimento já foi quitado.
                $valorCoberto = round($totalRecebido + $creditoUtilizado, 2);

                // Verifica a condição abaixo antes de executar este bloco.
                if ($pagamentoDebito) {
                    // Guarda o valor de débito ainda pendente do tutor.
                    $saldoDebito = saldoDebitoTutorFechamento($pdo, (int) $pet['cliente_id']);

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($totalRecebido > $saldoDebito + 0.009) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException(
                            'O tutor possui ' . formatarMoeda($saldoDebito) . ' de débito. O pagamento não pode ultrapassar esse valor.'
                        );
                    }
                // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
                } elseif ($valorCoberto > $totalRegistro + 0.009) {
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('O valor recebido mais o crédito utilizado não pode ultrapassar o total do registro.');
                }

                // Verifica a condição abaixo antes de executar este bloco.
                if (!$pagamentoDebito && $creditoUtilizado > 0) {
                    // Guarda o crédito disponível do tutor antes de registrar uma nova utilização.
                    $saldoCredito = saldoCreditoTutorFechamento(
                        $pdo,
                        (int) $pet['cliente_id']
                    );

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($creditoUtilizado > $saldoCredito + 0.009) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException('O tutor não possui crédito suficiente para esse valor.');
                    }
                }

                inserirFinanceiroRegistroFechamento(
                    $pdo,
                    $fechamentoId,
                    (int) $pet['cliente_id'],
                    $lancamentosNovos[0],
                    $pagamentosRecebidos,
                    $pagamentoDebito ? 0.0 : $creditoUtilizado,
                    $formasPorNome,
                    $usuarioId,
                    $dataSelecionada
                );

                // Verifica a condição abaixo antes de executar este bloco.
                if ($pagamentoDebito && $totalRecebido > 0) {
                    // Prepara a inclusão de um novo registro em "movimentacoes_cliente" usando os valores já validados.
                    $stmt = $pdo->prepare(
                        "INSERT INTO movimentacoes_cliente (
                            fechamento_diario_id,
                            cliente_id,
                            lancamento_diario_id,
                            tipo_saldo,
                            operacao,
                            valor,
                            data_hora_movimentacao,
                            descricao,
                            criado_por
                         ) VALUES (
                            :fechamento,
                            :cliente,
                            :lancamento,
                            'DEBITO',
                            'SAIDA',
                            :valor,
                            :data_hora,
                            'Pagamento de débito',
                            :usuario
                         )"
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute([
                        ':fechamento' => $fechamentoId,
                        ':cliente' => (int) $pet['cliente_id'],
                        ':lancamento' => $lancamentosNovos[0],
                        ':valor' => $totalRecebido,
                        ':data_hora' => $dataSelecionada . ' ' . date('H:i:s'),
                        ':usuario' => $usuarioId,
                    ]);
                }

                // Percorre cada item da coleção para processar os registros individualmente.
                foreach (array_unique($pacotesAfetados) as $petPacoteId) {
                    recalcularStatusPacoteFechamento(
                        $pdo,
                        (int) $petPacoteId,
                        $dataSelecionada
                    );
                }

                // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
                $pdo->commit();

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header(
                    'Location: fechamento-diario.php?data='
                    . urlencode($dataSelecionada)
                    . ($registro !== '' ? '&editado=1' : '&lancado=1')
                );
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
            } catch (Throwable $erro) {
                // Confere se existe uma transação aberta antes de tentar desfazê-la.
                if ($pdo->inTransaction()) {
                    // Desfaz as alterações da transação atual porque alguma etapa falhou.
                    $pdo->rollBack();
                }

                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = $erro instanceof RuntimeException
                    ? $erro->getMessage()
                    : 'Não foi possível salvar o registro.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            }
        }
    }

    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    elseif ($acao === 'atualizar_pagamento') {
        // Armazena em "registro" o valor necessário para as próximas etapas deste fluxo.
        $registro = trim((string) ($_POST['registro'] ?? ''));
        // Guarda quanto do saldo de crédito do tutor será usado para abater o atendimento.
        $creditoUtilizado = valorFechamento($_POST['credito_utilizado'] ?? 0);
        // Reúne os pagamentos reais vinculados aos lançamentos para calcular os totais por forma de pagamento.
        $pagamentosRecebidos = pagamentosPostFechamento($_POST);
        // Reúne os IDs dos lançamentos que pertencem ao mesmo atendimento ou registro do fechamento.
        $lancamentoIds = idsLancamentosRegistroFechamento(
            $pdo,
            $fechamentoId,
            $registro
        );

        // Verifica a condição abaixo antes de executar este bloco.
        if (!$lancamentoIds) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'O registro selecionado não foi encontrado.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
            try {
                // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
                $pdo->beginTransaction();

                // Monta os marcadores necessários para usar uma quantidade variável de IDs em uma consulta preparada.
                $placeholders = placeholdersIds($lancamentoIds);

                // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
                $stmt = $pdo->prepare(
                    "SELECT
                        MIN(l.id) AS primeiro_lancamento,
                        MIN(p.cliente_id) AS cliente_id,
                        SUM(vt.valor_total_lancamento) AS total_registro,
                        MAX(
                            CASE
                                WHEN cs.nome = 'Financeiro' AND s.nome = 'Pagar débito' THEN 1
                                ELSE 0
                            END
                        ) AS pagamento_debito
                     FROM lancamentos_diarios l
                     JOIN pets p ON p.id = l.pet_id
                     JOIN servicos s ON s.id = l.servico_id
                     JOIN categorias_servico cs ON cs.id = s.categoria_id
                     JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
                     WHERE l.id IN ($placeholders)"
                );
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($lancamentoIds);
                // Obtém a próxima linha retornada pela consulta como um registro associativo.
                $dadosPagamento = $stmt->fetch();

                // Verifica a condição abaixo antes de executar este bloco.
                if (!$dadosPagamento || !$dadosPagamento['primeiro_lancamento']) {
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('O registro selecionado não foi encontrado.');
                }

                apagarFinanceiroLancamentos(
                    $pdo,
                    $fechamentoId,
                    $lancamentoIds
                );

                // Calcula o valor total da linha ou atendimento considerando serviços e adicionais relacionados.
                $totalRegistro = (float) $dadosPagamento['total_registro'];
                // Soma tudo o que foi efetivamente pago para comparar com o valor cobrado.
                $totalRecebido = round(array_sum($pagamentosRecebidos), 2);
                // Soma pagamentos e créditos utilizados para verificar quanto do atendimento já foi quitado.
                $valorCoberto = round($totalRecebido + $creditoUtilizado, 2);
                // Armazena em "pagamentoDebitoRegistro" o valor necessário para as próximas etapas deste fluxo.
                $pagamentoDebitoRegistro = (int) ($dadosPagamento['pagamento_debito'] ?? 0) === 1;

                // Verifica a condição abaixo antes de executar este bloco.
                if ($pagamentoDebitoRegistro) {
                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($creditoUtilizado > 0.009) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException('Para pagar débito, use Dinheiro, Pix ou Cartão.');
                    }

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($totalRecebido <= 0) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException('Informe quanto o tutor está pagando do débito.');
                    }

                    // Guarda o valor de débito ainda pendente do tutor.
                    $saldoDebito = saldoDebitoTutorFechamento(
                        $pdo,
                        (int) $dadosPagamento['cliente_id']
                    );

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($totalRecebido > $saldoDebito + 0.009) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException(
                            'O tutor possui ' . formatarMoeda($saldoDebito) . ' de débito. O pagamento não pode ultrapassar esse valor.'
                        );
                    }
                // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
                } elseif ($valorCoberto > $totalRegistro + 0.009) {
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('O pagamento mais o crédito utilizado não pode ultrapassar o total do registro.');
                }

                // Verifica a condição abaixo antes de executar este bloco.
                if (!$pagamentoDebitoRegistro && $creditoUtilizado > 0) {
                    // Guarda o crédito disponível do tutor antes de registrar uma nova utilização.
                    $saldoCredito = saldoCreditoTutorFechamento(
                        $pdo,
                        (int) $dadosPagamento['cliente_id']
                    );

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($creditoUtilizado > $saldoCredito + 0.009) {
                        // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                        throw new RuntimeException('O tutor não possui crédito suficiente para esse valor.');
                    }
                }

                inserirFinanceiroRegistroFechamento(
                    $pdo,
                    $fechamentoId,
                    (int) $dadosPagamento['cliente_id'],
                    (int) $dadosPagamento['primeiro_lancamento'],
                    $pagamentosRecebidos,
                    $pagamentoDebitoRegistro ? 0.0 : $creditoUtilizado,
                    $formasPorNome,
                    $usuarioId,
                    $dataSelecionada
                );

                // Verifica a condição abaixo antes de executar este bloco.
                if ($pagamentoDebitoRegistro) {
                    // Prepara a inclusão de um novo registro em "movimentacoes_cliente" usando os valores já validados.
                    $stmt = $pdo->prepare(
                        "INSERT INTO movimentacoes_cliente (
                            fechamento_diario_id,
                            cliente_id,
                            lancamento_diario_id,
                            tipo_saldo,
                            operacao,
                            valor,
                            data_hora_movimentacao,
                            descricao,
                            criado_por
                         ) VALUES (
                            :fechamento,
                            :cliente,
                            :lancamento,
                            'DEBITO',
                            'SAIDA',
                            :valor,
                            :data_hora,
                            'Pagamento de débito',
                            :usuario
                         )"
                    );
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                    $stmt->execute([
                        ':fechamento' => $fechamentoId,
                        ':cliente' => (int) $dadosPagamento['cliente_id'],
                        ':lancamento' => (int) $dadosPagamento['primeiro_lancamento'],
                        ':valor' => $totalRecebido,
                        ':data_hora' => $dataSelecionada . ' ' . date('H:i:s'),
                        ':usuario' => $usuarioId,
                    ]);
                }

                // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
                $pdo->commit();

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: fechamento-diario.php?data=' . urlencode($dataSelecionada) . '&pagamento=1');
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
            } catch (Throwable $erro) {
                // Confere se existe uma transação aberta antes de tentar desfazê-la.
                if ($pdo->inTransaction()) {
                    // Desfaz as alterações da transação atual porque alguma etapa falhou.
                    $pdo->rollBack();
                }

                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = $erro instanceof RuntimeException
                    ? $erro->getMessage()
                    : 'Não foi possível atualizar o pagamento.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            }
        }
    }

    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    elseif ($acao === 'excluir_registro') {
        // Armazena em "registro" o valor necessário para as próximas etapas deste fluxo.
        $registro = trim((string) ($_POST['registro'] ?? ''));
        // Reúne os IDs dos lançamentos que pertencem ao mesmo atendimento ou registro do fechamento.
        $lancamentoIds = idsLancamentosRegistroFechamento(
            $pdo,
            $fechamentoId,
            $registro
        );

        // Verifica a condição abaixo antes de executar este bloco.
        if (!$lancamentoIds) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Registro inválido.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
            try {
                // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
                $pdo->beginTransaction();

                // Monta os marcadores necessários para usar uma quantidade variável de IDs em uma consulta preparada.
                $placeholders = placeholdersIds($lancamentoIds);

                // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT pet_pacote_id
                     FROM lancamentos_diarios
                     WHERE id IN ($placeholders)
                       AND pet_pacote_id IS NOT NULL"
                );
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($lancamentoIds);
                // Reúne os pacotes que tiveram consumo alterado para recalcular seus status ao final da operação.
                $pacotesAfetados = array_map(
                    'intval',
                    // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
                    array_column($stmt->fetchAll(), 'pet_pacote_id')
                );

                apagarFinanceiroLancamentos(
                    $pdo,
                    $fechamentoId,
                    $lancamentoIds
                );

                // Prepara a remoção do registro correspondente em "lancamentos_diarios" conforme a ação solicitada.
                $stmt = $pdo->prepare(
                    "DELETE FROM lancamentos_diarios
                     WHERE id IN ($placeholders)"
                );
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($lancamentoIds);

                // Percorre cada item da coleção para processar os registros individualmente.
                foreach (array_unique($pacotesAfetados) as $petPacoteId) {
                    recalcularStatusPacoteFechamento(
                        $pdo,
                        (int) $petPacoteId,
                        $dataSelecionada
                    );
                }

                // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
                $pdo->commit();

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: fechamento-diario.php?data=' . urlencode($dataSelecionada) . '&excluido=1');
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
            } catch (Throwable) {
                // Confere se existe uma transação aberta antes de tentar desfazê-la.
                if ($pdo->inTransaction()) {
                    // Desfaz as alterações da transação atual porque alguma etapa falhou.
                    $pdo->rollBack();
                }

                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Não foi possível excluir o registro.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            }
        }
    }

    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    elseif ($acao === 'finalizar_fechamento') {
        // Guarda os valores informados pela equipe para a conferência final.
        $dinheiroContado = valorFechamento($_POST['valor_dinheiro_contado'] ?? 0);
        $pixConferido = valorFechamento($_POST['valor_pix_conferido'] ?? 0);
        $cartaoConferido = valorFechamento($_POST['valor_cartao_conferido'] ?? 0);

        $quantidadesConferidas = [
            'banho' => max(0, (int) ($_POST['quantidade_banho'] ?? 0)),
            'banho_tosa' => max(0, (int) ($_POST['quantidade_banho_tosa'] ?? 0)),
            'higienica' => max(0, (int) ($_POST['quantidade_higienica'] ?? 0)),
            'creche' => max(0, (int) ($_POST['quantidade_creche'] ?? 0)),
            'hotel' => max(0, (int) ($_POST['quantidade_hotel'] ?? 0)),
        ];

        // Guarda observações informadas pela equipe para acompanhar o cadastro, agendamento ou lançamento.
        $observacoes = textoOuNull($_POST['observacoes_fechamento'] ?? null);

        $divergencias = [];
        $rotulosQuantidade = [
            'banho' => 'Banho',
            'banho_tosa' => 'Banho e tosa',
            'higienica' => 'Tosa higiênica',
            'creche' => 'Creche',
            'hotel' => 'Hotel',
        ];

        foreach ($rotulosQuantidade as $chave => $rotulo) {
            $esperado = (int) ($conferenciaFechamento[$chave] ?? 0);
            $informado = (int) ($quantidadesConferidas[$chave] ?? 0);

            if ($esperado !== $informado) {
                $divergencias[] = $rotulo
                    . ': sistema ' . $esperado
                    . ', conferido ' . $informado;
            }
        }

        $conferenciasFinanceiras = [
            'Dinheiro' => [$dinheiroContado, (float) ($conferenciaFechamento['dinheiro'] ?? 0)],
            'Pix' => [$pixConferido, (float) ($conferenciaFechamento['pix'] ?? 0)],
            'Cartão' => [$cartaoConferido, (float) ($conferenciaFechamento['cartao'] ?? 0)],
        ];

        foreach ($conferenciasFinanceiras as $rotulo => [$informado, $esperado]) {
            if (abs($informado - $esperado) > 0.009) {
                $divergencias[] = $rotulo
                    . ': sistema ' . formatarMoeda($esperado)
                    . ', conferido ' . formatarMoeda($informado);
            }
        }

        if ($divergencias) {
            $mensagem = 'A conferência não bate. '
                . implode(' | ', $divergencias)
                . '. Corrija os valores antes de finalizar.';
            $tipoMensagem = 'error';
            $abrirModalFinalizacao = true;
        } else {
            // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
            try {
            // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
            $pdo->beginTransaction();

            // Recalcula os débitos automáticos do dia do zero para evitar duplicidade.
            $stmt = $pdo->prepare(
                "DELETE FROM movimentacoes_cliente
                 WHERE fechamento_diario_id = :fechamento
                   AND tipo_saldo = 'DEBITO'
                   AND operacao = 'ENTRADA'
                   AND descricao IN (
                        'Débito automático do fechamento diário',
                        'Débito gerado no fechamento diário'
                   )"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':fechamento' => $fechamentoId]);

            // Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                "SELECT
                    l.id,
                    l.atendimento_id,
                    p.cliente_id,
                    vt.valor_total_lancamento,
                    COALESCE(pg.total_pago, 0) AS total_pago,
                    COALESCE(mc.credito_utilizado, 0) AS credito_utilizado
                 FROM lancamentos_diarios l
                 JOIN pets p ON p.id = l.pet_id
                 JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
                 LEFT JOIN (
                    SELECT lancamento_diario_id, SUM(valor) AS total_pago
                    FROM pagamentos
                    WHERE estornado = FALSE
                    GROUP BY lancamento_diario_id
                 ) pg ON pg.lancamento_diario_id = l.id
                 LEFT JOIN (
                    SELECT
                        lancamento_diario_id,
                        SUM(
                            CASE
                                WHEN tipo_saldo = 'CREDITO'
                                 AND operacao = 'SAIDA'
                                    THEN valor
                                ELSE 0
                            END
                        ) AS credito_utilizado
                    FROM movimentacoes_cliente
                    GROUP BY lancamento_diario_id
                 ) mc ON mc.lancamento_diario_id = l.id
                 WHERE l.fechamento_diario_id = :fechamento
                 ORDER BY l.id"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':fechamento' => $fechamentoId]);

            // Armazena em "gruposFechamento" o valor necessário para as próximas etapas deste fluxo.
            $gruposFechamento = [];

            // Percorre cada item da coleção para processar os registros individualmente.
            foreach ($stmt->fetchAll() as $linha) {
                // Armazena em "chave" o valor necessário para as próximas etapas deste fluxo.
                $chave = $linha['atendimento_id'] !== null
                    ? 'a:' . (int) $linha['atendimento_id']
                    : 'l:' . (int) $linha['id'];

                // Verifica a condição abaixo antes de executar este bloco.
                if (!isset($gruposFechamento[$chave])) {
                    $gruposFechamento[$chave] = [
                        'cliente_id' => (int) $linha['cliente_id'],
                        'primeiro_lancamento' => (int) $linha['id'],
                        'total' => 0.0,
                        'pago' => 0.0,
                        'credito' => 0.0,
                    ];
                }

                $gruposFechamento[$chave]['total'] += (float) $linha['valor_total_lancamento'];
                $gruposFechamento[$chave]['pago'] += (float) $linha['total_pago'];
                $gruposFechamento[$chave]['credito'] += (float) $linha['credito_utilizado'];
            }

            // Armazena em "stmtDebito" o valor necessário para as próximas etapas deste fluxo.
            $stmtDebito = $pdo->prepare(
                "INSERT INTO movimentacoes_cliente (
                    fechamento_diario_id,
                    cliente_id,
                    lancamento_diario_id,
                    tipo_saldo,
                    operacao,
                    valor,
                    data_hora_movimentacao,
                    descricao,
                    criado_por
                 ) VALUES (
                    :fechamento,
                    :cliente,
                    :lancamento,
                    'DEBITO',
                    'ENTRADA',
                    :valor,
                    :data_hora,
                    'Débito automático do fechamento diário',
                    :usuario
                 )"
            );

            // Percorre cada item da coleção para processar os registros individualmente.
            foreach ($gruposFechamento as $grupo) {
                // Armazena em "faltante" o valor necessário para as próximas etapas deste fluxo.
                $faltante = round(
                    $grupo['total'] - $grupo['pago'] - $grupo['credito'],
                    2
                );

                // Verifica a condição abaixo antes de executar este bloco.
                if ($faltante <= 0.009) {
                    continue;
                }

                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmtDebito->execute([
                    ':fechamento' => $fechamentoId,
                    ':cliente' => $grupo['cliente_id'],
                    ':lancamento' => $grupo['primeiro_lancamento'],
                    ':valor' => $faltante,
                    ':data_hora' => $dataSelecionada . ' ' . date('H:i:s'),
                    ':usuario' => $usuarioId,
                ]);
            }

            // Prepara a atualização do registro correspondente em "fechamentos_diarios" com os novos dados.
            $stmt = $pdo->prepare(
                "UPDATE fechamentos_diarios
                 SET status = 'FECHADO',
                     valor_dinheiro_contado = :contado,
                     observacoes = :observacoes,
                     fechado_por = :usuario,
                     fechado_em = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([
                ':contado' => $dinheiroContado,
                ':observacoes' => $observacoes,
                ':usuario' => $usuarioId,
                ':id' => $fechamentoId,
            ]);

            // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
            $pdo->commit();

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header('Location: fechamento-diario.php?data=' . urlencode($dataSelecionada) . '&fechado=1');
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
        } catch (Throwable $erro) {
            // Confere se existe uma transação aberta antes de tentar desfazê-la.
            if ($pdo->inTransaction()) {
                // Desfaz as alterações da transação atual porque alguma etapa falhou.
                $pdo->rollBack();
            }

            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Não foi possível finalizar o fechamento.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        }
    }
    }

    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    elseif ($acao === 'reabrir_fechamento' && $administrador) {
        // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
        try {
            // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
            $pdo->beginTransaction();

            // Prepara a atualização do registro correspondente em "fechamentos_diarios" com os novos dados.
            $stmt = $pdo->prepare(
                "DELETE FROM movimentacoes_cliente
                 WHERE fechamento_diario_id = :fechamento
                   AND tipo_saldo = 'DEBITO'
                   AND operacao = 'ENTRADA'
                   AND descricao IN (
                        'Débito automático do fechamento diário',
                        'Débito gerado no fechamento diário'
                   )"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':fechamento' => $fechamentoId]);

            // Prepara a atualização do registro correspondente em "fechamentos_diarios" com os novos dados.
            $stmt = $pdo->prepare(
                "UPDATE fechamentos_diarios
                 SET status = 'REABERTO',
                     fechado_por = NULL,
                     fechado_em = NULL
                 WHERE id = :id"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':id' => $fechamentoId]);

            // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
            $pdo->commit();

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header('Location: fechamento-diario.php?data=' . urlencode($dataSelecionada) . '&reaberto=1');
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
        } catch (Throwable) {
            // Confere se existe uma transação aberta antes de tentar desfazê-la.
            if ($pdo->inTransaction()) {
                // Desfaz as alterações da transação atual porque alguma etapa falhou.
                $pdo->rollBack();
            }

            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Não foi possível reabrir o fechamento.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        }
    }

}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verifica a condição abaixo antes de executar este bloco.
    if (isset($_GET['lancado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Registro adicionado ao fechamento.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['editado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Registro atualizado com sucesso.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['pagamento'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Pagamento do lançamento atualizado.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['excluido'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Lançamento excluído.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['abertura'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Valor de abertura atualizado.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['fechado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Fechamento diário finalizado.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['reaberto'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Fechamento reaberto para ajustes.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    }
}

// Recarrega o fechamento depois das ações.
$stmt = $pdo->prepare('SELECT * FROM fechamentos_diarios WHERE id = :id');
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $fechamentoId]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$fechamento = $stmt->fetch();
// Verifica se o fechamento já foi encerrado para impedir alterações que exigem o caixa aberto.
$fechamentoFechado = $fechamento['status'] === 'FECHADO';

// Reúne os serviços ativos disponíveis para seleção, classificação ou resumo.
$servicos = $pdo->query(
    'SELECT s.id, s.nome, s.aceita_pacote, cs.nome AS categoria
     FROM servicos s
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     WHERE s.ativo = TRUE
       AND cs.ativo = TRUE
     ORDER BY cs.nome, s.nome'
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
)->fetchAll();

// Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare(
    "SELECT
        l.id,
        l.atendimento_id,
        l.pet_id,
        p.nome AS pet,
        c.id AS cliente_id,
        c.nome AS tutor,
        s.nome AS servico,
        cs.nome AS categoria,
        pa.nome AS pacote,
        l.creditos_pacote_usados,
        vt.valor_servico,
        vt.total_adicionais,
        vt.valor_total_lancamento,
        COALESCE(pg.dinheiro, 0) AS dinheiro,
        COALESCE(pg.pix, 0) AS pix,
        COALESCE(pg.cartao, 0) AS cartao,
        COALESCE(mov.credito_utilizado, 0) AS credito_utilizado,
        COALESCE(mov.debito_gerado, 0) AS debito_gerado,
        COALESCE(mov.debito_pago, 0) AS debito_pago
     FROM lancamentos_diarios l
     JOIN pets p ON p.id = l.pet_id
     JOIN clientes c ON c.id = p.cliente_id
     JOIN servicos s ON s.id = l.servico_id
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
     LEFT JOIN pet_pacotes pp ON pp.id = l.pet_pacote_id
     LEFT JOIN pacotes pa ON pa.id = pp.pacote_id
     LEFT JOIN (
        SELECT
            p.lancamento_diario_id,
            SUM(CASE WHEN fp.nome = 'Dinheiro' THEN p.valor ELSE 0 END) AS dinheiro,
            SUM(CASE WHEN fp.nome = 'Pix' THEN p.valor ELSE 0 END) AS pix,
            SUM(CASE WHEN fp.nome IN ('Cartão', 'Cartão de débito', 'Cartão de crédito') THEN p.valor ELSE 0 END) AS cartao
        FROM pagamentos p
        JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
        WHERE p.estornado = FALSE
        GROUP BY p.lancamento_diario_id
     ) pg ON pg.lancamento_diario_id = l.id
     LEFT JOIN (
        SELECT
            lancamento_diario_id,
            SUM(
                CASE
                    WHEN tipo_saldo = 'CREDITO' AND operacao = 'SAIDA'
                        THEN valor
                    ELSE 0
                END
            ) AS credito_utilizado,
            SUM(
                CASE
                    WHEN tipo_saldo = 'DEBITO' AND operacao = 'ENTRADA'
                        THEN valor
                    ELSE 0
                END
            ) AS debito_gerado,
            SUM(
                CASE
                    WHEN tipo_saldo = 'DEBITO' AND operacao = 'SAIDA'
                        THEN valor
                    ELSE 0
                END
            ) AS debito_pago
        FROM movimentacoes_cliente
        GROUP BY lancamento_diario_id
     ) mov ON mov.lancamento_diario_id = l.id
     WHERE l.fechamento_diario_id = :fechamento
     ORDER BY l.id"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':fechamento' => $fechamentoId]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$lancamentosBrutos = $stmt->fetchAll();

// Reúne os registros retornados pelo banco para montar a resposta ou listagem da interface.
$registros = [];

// Percorre cada item da coleção para processar os registros individualmente.
foreach ($lancamentosBrutos as $item) {
    // Armazena em "chave" o valor necessário para as próximas etapas deste fluxo.
    $chave = $item['atendimento_id'] !== null
        ? 'a:' . (int) $item['atendimento_id']
        : 'l:' . (int) $item['id'];

    // Verifica a condição abaixo antes de executar este bloco.
    if (!isset($registros[$chave])) {
        $registros[$chave] = [
            'registro' => $chave,
            'pet_id' => (int) $item['pet_id'],
            'cliente_id' => (int) $item['cliente_id'],
            'pet' => (string) $item['pet'],
            'tutor' => (string) $item['tutor'],
            'servicos' => [],
            'pacotes' => [],
            'valor_servicos' => 0.0,
            'total_adicionais' => 0.0,
            'total' => 0.0,
            'creche_hotel' => 0.0,
            'vet' => 0.0,
            'taxidog' => 0.0,
            'loja' => 0.0,
            'dinheiro' => 0.0,
            'pix' => 0.0,
            'cartao' => 0.0,
            'credito_utilizado' => 0.0,
            'debito_gerado' => 0.0,
            'debito_pago' => 0.0,
        ];
    }

    // Armazena em "categoriaChave" o valor necessário para as próximas etapas deste fluxo.
    $categoriaChave = categoriaFechamento(
        (string) $item['categoria'],
        (string) $item['servico']
    );
    // Armazena o valor financeiro "valorTotalServico" já normalizado para os cálculos seguintes.
    $valorTotalServico = (float) $item['valor_total_lancamento'];

    $registros[$chave]['servicos'][] = (string) $item['servico'];

    // Verifica a condição abaixo antes de executar este bloco.
    if (!empty($item['pacote'])) {
        // Armazena em "rotuloPacote" o valor necessário para as próximas etapas deste fluxo.
        $rotuloPacote = (string) $item['pacote'];

        // Verifica a condição abaixo antes de executar este bloco.
        if ((int) $item['creditos_pacote_usados'] > 0) {
            $rotuloPacote .= ' · ' . (int) $item['creditos_pacote_usados'] . ' crédito(s)';
        }

        $registros[$chave]['pacotes'][] = $rotuloPacote;
    }

    $registros[$chave]['valor_servicos'] += (float) $item['valor_servico'];
    $registros[$chave]['total_adicionais'] += (float) $item['total_adicionais'];
    $registros[$chave]['total'] += $valorTotalServico;
    $registros[$chave]['dinheiro'] += (float) $item['dinheiro'];
    $registros[$chave]['pix'] += (float) $item['pix'];
    $registros[$chave]['cartao'] += (float) $item['cartao'];
    $registros[$chave]['credito_utilizado'] += (float) $item['credito_utilizado'];
    $registros[$chave]['debito_gerado'] += (float) $item['debito_gerado'];
    $registros[$chave]['debito_pago'] += (float) $item['debito_pago'];

    // Verifica a condição abaixo antes de executar este bloco.
    if (isset($registros[$chave][$categoriaChave])) {
        $registros[$chave][$categoriaChave] += $valorTotalServico;
    }
}

// Reúne os registros retornados pelo banco para montar a resposta ou listagem da interface.
$registros = array_values($registros);

// Prepara uma consulta em "vw_fechamento_diario" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare('SELECT * FROM vw_fechamento_diario WHERE fechamento_diario_id = :id');
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $fechamentoId]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$resumo = $stmt->fetch() ?: [];

// Prepara uma consulta em "lancamentos_diarios" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare(
    "SELECT
        COUNT(l.id) AS quantidade_lancamentos,
        COUNT(DISTINCT l.pet_id) AS quantidade_pets
     FROM lancamentos_diarios l
     JOIN servicos s ON s.id = l.servico_id
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     WHERE l.fechamento_diario_id = :id
       AND LOWER(cs.nome) <> 'financeiro'"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $fechamentoId]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$resumoOperacional = $stmt->fetch() ?: ['quantidade_lancamentos' => 0, 'quantidade_pets' => 0];

// Totais de pagamento exibidos de forma simples: Dinheiro, Pix e Cartão.
// Cartões antigos (crédito/débito) entram juntos no mesmo total.
$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN fp.nome = 'Dinheiro' THEN p.valor ELSE 0 END), 0) AS dinheiro,
        COALESCE(SUM(CASE WHEN fp.nome = 'Pix' THEN p.valor ELSE 0 END), 0) AS pix,
        COALESCE(SUM(CASE WHEN fp.nome IN ('Cartão', 'Cartão de débito', 'Cartão de crédito') THEN p.valor ELSE 0 END), 0) AS cartao
     FROM pagamentos p
     JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
     WHERE p.estornado = FALSE
       AND p.fechamento_diario_id = :id"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $fechamentoId]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$resumoPagamentos = $stmt->fetch() ?: ['dinheiro' => 0, 'pix' => 0, 'cartao' => 0];

$resumo['dinheiro'] = (float) ($resumoPagamentos['dinheiro'] ?? 0);
$resumo['pix'] = (float) ($resumoPagamentos['pix'] ?? 0);
$resumo['cartao'] = (float) ($resumoPagamentos['cartao'] ?? 0);
$resumo['total_recebido'] = $resumo['dinheiro'] + $resumo['pix'] + $resumo['cartao'];

$conferenciaFechamento = resumoConferenciaFinalFechamento(
    $pdo,
    $fechamentoId,
    (float) ($fechamento['valor_abertura_caixa'] ?? 0)
);

// Calcula quanto dinheiro físico deveria existir no caixa conforme os lançamentos registrados.
$dinheiroEsperado = (float) ($conferenciaFechamento['dinheiro'] ?? 0);
// Armazena em "dinheiroContadoAtual" o valor necessário para as próximas etapas deste fluxo.
$dinheiroContadoAtual = $fechamento['valor_dinheiro_contado'] !== null ? (float) $fechamento['valor_dinheiro_contado'] : null;
// Armazena em "diferencaDinheiro" o valor necessário para as próximas etapas deste fluxo.
$diferencaDinheiro = $dinheiroContadoAtual !== null ? round($dinheiroContadoAtual - $dinheiroEsperado, 2) : null;

// Classificação detalhada por serviço: banho, tosa, creche, hotel, consulta etc.
$stmt = $pdo->prepare(
    "SELECT
        s.id AS servico_id,
        s.nome AS servico,
        cs.nome AS categoria,
        COUNT(l.id) AS quantidade_lancamentos,
        COUNT(DISTINCT l.pet_id) AS quantidade_pets,
        COALESCE(SUM(vt.valor_total_lancamento), 0) AS total_servico
     FROM lancamentos_diarios l
     JOIN servicos s ON s.id = l.servico_id
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
     WHERE l.fechamento_diario_id = :id
       AND LOWER(cs.nome) <> 'financeiro'
     GROUP BY s.id, s.nome, cs.nome
     ORDER BY cs.nome, s.nome"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $fechamentoId]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$resumoServicos = $stmt->fetchAll();

// Calcula a data anterior para permitir navegar para o período precedente.
$dataAnterior = (new DateTime($dataSelecionada))->modify('-1 day')->format('Y-m-d');
// Calcula a data seguinte para permitir navegar para o próximo período.
$dataSeguinte = (new DateTime($dataSelecionada))->modify('+1 day')->format('Y-m-d');

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Fechamento diário';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'fechamento-diario';
// Define o(s) CSS desta página em assets/css/ (fechamento.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'fechamento';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page daily-close-page">
    <div class="daily-close-heading">
        <div>
            <div class="daily-close-kicker">Financeiro</div>
            <h1>Fechamento diário</h1>
            <p>Seu caixa do dia no mesmo padrão da planilha, só que organizado pelo sistema.</p>
        </div>

        <div class="daily-close-status-area">
            <span class="daily-close-status status-<?= strtolower(escapar((string) $fechamento['status'])) ?>">
                <i class="fa-solid fa-circle"></i>
                <?= escapar((string) $fechamento['status']) ?>
            </span>

            <?php if ($fechamentoFechado && $administrador): ?>
                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                <form method="post" action="fechamento-diario.php?data=<?= escapar($dataSelecionada) ?>">
                    <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                    <input type="hidden" name="acao" value="reabrir_fechamento">
                    <button class="daily-ghost-button" type="submit">
                        <i class="fa-solid fa-lock-open"></i>
                        Reabrir dia
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?> daily-close-message">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <div class="daily-date-toolbar">
        <div class="daily-date-nav">
            <a href="fechamento-diario.php?data=<?= escapar($dataAnterior) ?>" aria-label="Dia anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a class="daily-today-button" href="fechamento-diario.php?data=<?= escapar(date('Y-m-d')) ?>">Hoje</a>
            <a href="fechamento-diario.php?data=<?= escapar($dataSeguinte) ?>" aria-label="Dia seguinte">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form method="get" action="fechamento-diario.php" class="daily-date-form">
            <!-- Rótulo do campo "Data do fechamento", indicando ao usuário qual informação deve ser preenchida. -->
            <label for="dataFechamento">Data do fechamento</label>
            <!-- Campo "data" utilizado para enviar esta informação no formulário. -->
            <input id="dataFechamento" name="data" type="date" value="<?= escapar($dataSelecionada) ?>" onchange="this.form.submit()">
        </form>

        <strong class="daily-date-title"><?= escapar(formatarData($dataSelecionada)) ?></strong>
    </div>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="daily-sheet-card">
        <div class="daily-sheet-header">
            <div>
                <h2>Movimento do dia</h2>
                <small>80 linhas fixas para o dia, no padrão do seu caixa.</small>
            </div>

            <div class="daily-sheet-actions">
                <button class="daily-primary-button" type="button" data-open-daily-modal="launch" <?= $fechamentoFechado ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-plus"></i>
                    Novo lançamento
                </button>
            </div>
        </div>

        <div class="daily-sheet-wrap">
            <!-- Tabela usada para organizar os registros em colunas de leitura rápida. -->
            <table class="daily-sheet-table">
                <!-- Cabeçalho da tabela com os nomes das informações exibidas em cada coluna. -->
                <thead>
                    <tr class="daily-group-row">
                        <th colspan="6">Atendimento</th>
                        <th colspan="5">Categorias / saldos</th>
                        <th colspan="6">Financeiro</th>
                        <th rowspan="2" aria-label="Ações"></th>
                    </tr>
                    <tr>
                        <th>Cód.</th>
                        <th>Pet / tutor</th>
                        <th>Serviço</th>
                        <th>Pacote</th>
                        <th>Valor</th>
                        <th>Adic.</th>
                        <th>Creche/Hotel</th>
                        <th>Vet</th>
                        <th>Taxidog</th>
                        <th>Débito</th>
                        <th>Crédito</th>
                        <th>Loja</th>
                        <th>Total</th>
                        <th>Dinheiro</th>
                        <th>Pix</th>
                        <th>Cartão</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <!-- Corpo da tabela onde os registros retornados pelo banco são listados. -->
                <tbody>
                    <?php foreach ($registros as $item): ?>
                        <?php
                        // Calcula/armazena o total de "total" que será usado nos resumos e na interface.
                        $total = (float) $item['total'];
                        // Guarda uma condição booleana em "ehLinhaPagamentoDebito" para controlar o fluxo ou a exibição da página.
                        $ehLinhaPagamentoDebito = in_array('Pagar débito', $item['servicos'], true);
                        // Calcula/armazena o total de "totalQuitado" que será usado nos resumos e na interface.
                        $totalQuitado = (float) $item['dinheiro']
                            + (float) $item['pix']
                            + (float) $item['cartao']
                            + (float) $item['credito_utilizado']
                            + (float) $item['debito_gerado'];
                        // Armazena o valor financeiro "saldo" já normalizado para os cálculos seguintes.
                        $saldo = $ehLinhaPagamentoDebito ? 0.0 : round($total - $totalQuitado, 2);
                        // Armazena em "pagamentoPendente" o valor necessário para as próximas etapas deste fluxo.
                        $pagamentoPendente = !$ehLinhaPagamentoDebito
                            && $saldo > 0.009
                            && (float) $item['debito_gerado'] <= 0;
                        ?>
                        <tr class="<?= $pagamentoPendente ? 'has-pending-payment' : '' ?>">
                            <td class="daily-code">#<?= (int) $item['pet_id'] ?></td>
                            <td class="daily-pet-cell">
                                <strong><?= escapar($item['pet']) ?></strong>
                                <small><?= escapar($item['tutor']) ?></small>
                            </td>
                            <td>
                                <div class="daily-services-cell">
                                    <?php foreach ($item['servicos'] as $nomeServico): ?>
                                        <span><?= escapar($nomeServico) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="daily-package-cell">
                                <?php if ($item['pacotes']): ?>
                                    <div class="daily-packages-cell">
                                        <?php foreach (array_unique($item['pacotes']) as $pacote): ?>
                                            <span><?= escapar($pacote) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="daily-dash">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatarMoeda($item['valor_servicos']) ?></td>
                            <td><?= (float) $item['total_adicionais'] > 0 ? formatarMoeda($item['total_adicionais']) : '<span class="daily-dash">—</span>' ?></td>
                            <td><?= (float) $item['creche_hotel'] > 0 ? formatarMoeda($item['creche_hotel']) : '<span class="daily-dash">—</span>' ?></td>
                            <td><?= (float) $item['vet'] > 0 ? formatarMoeda($item['vet']) : '<span class="daily-dash">—</span>' ?></td>
                            <td><?= (float) $item['taxidog'] > 0 ? formatarMoeda($item['taxidog']) : '<span class="daily-dash">—</span>' ?></td>
                            <td class="daily-money <?= (float) $item['debito_pago'] > 0 ? 'green-text' : 'danger-text' ?>">
                                <?php if ((float) $item['debito_pago'] > 0): ?>
                                    Pago <?= formatarMoeda($item['debito_pago']) ?>
                                <?php elseif ((float) $item['debito_gerado'] > 0): ?>
                                    <?= formatarMoeda($item['debito_gerado']) ?>
                                <?php else: ?>
                                    <span class="daily-dash">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="daily-money green-text"><?= (float) $item['credito_utilizado'] > 0 ? formatarMoeda($item['credito_utilizado']) : '<span class="daily-dash">—</span>' ?></td>
                            <td><?= (float) $item['loja'] > 0 ? formatarMoeda($item['loja']) : '<span class="daily-dash">—</span>' ?></td>
                            <td class="daily-total-cell"><?= formatarMoeda($total) ?></td>
                            <td><?= (float) $item['dinheiro'] > 0 ? formatarMoeda($item['dinheiro']) : '<span class="daily-dash">—</span>' ?></td>
                            <td><?= (float) $item['pix'] > 0 ? formatarMoeda($item['pix']) : '<span class="daily-dash">—</span>' ?></td>
                            <td><?= (float) $item['cartao'] > 0 ? formatarMoeda($item['cartao']) : '<span class="daily-dash">—</span>' ?></td>
                            <td class="daily-money <?= $pagamentoPendente ? 'pending-text' : (abs($saldo) > 0.009 ? 'danger-text' : 'muted-text') ?>">
                                <?= abs($saldo) > 0.009 ? formatarMoeda($saldo) : 'R$ 0,00' ?>
                                <?php if ($pagamentoPendente): ?>
                                    <span class="daily-pending-chip">A receber</span>
                                <?php endif; ?>
                            </td>
                            <td class="daily-row-action">
                                <?php if (!$fechamentoFechado): ?>
                                    <div class="daily-row-action-buttons">
                                        <button
                                            type="button"
                                            class="daily-full-edit"
                                            title="Editar linha completa"
                                            data-edit-record
                                            data-record="<?= escapar($item['registro']) ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="daily-payment-edit"
                                            title="Alterar somente o pagamento"
                                            data-edit-payment
                                            data-record="<?= escapar($item['registro']) ?>"
                                            data-pet="<?= escapar($item['pet']) ?>"
                                            data-tutor="<?= escapar($item['tutor']) ?>"
                                            data-servico="<?= escapar(implode(', ', $item['servicos'])) ?>"
                                            data-total="<?= escapar((string) $total) ?>"
                                            data-dinheiro="<?= escapar((string) $item['dinheiro']) ?>"
                                            data-pix="<?= escapar((string) $item['pix']) ?>"
                                            data-cartao="<?= escapar((string) $item['cartao']) ?>"
                                            data-credito-usado="<?= escapar((string) $item['credito_utilizado']) ?>"
                                            data-pagamento-debito="<?= $ehLinhaPagamentoDebito ? '1' : '0' ?>">
                                            <i class="fa-regular fa-credit-card"></i>
                                        </button>
                                        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                                        <form
                                            method="post"
                                            action="fechamento-diario.php?data=<?= escapar($dataSelecionada) ?>"
                                            onsubmit="return confirm('Excluir esta linha inteira, com todos os serviços e pagamentos dela?');">
                                            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                                            <input type="hidden" name="acao" value="excluir_registro">
                                            <!-- Campo "registro" utilizado para enviar esta informação no formulário. -->
                                            <input type="hidden" name="registro" value="<?= escapar($item['registro']) ?>">
                                            <button type="submit" title="Excluir linha completa">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php for ($linha = count($registros); $linha < 80; $linha++): ?>
                        <tr class="daily-empty-row" aria-hidden="true">
                            <?php for ($coluna = 0; $coluna < 18; $coluna++): ?>
                                <td<?= $coluna === 0 ? ' class="daily-code"' : '' ?>></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="daily-after-sheet-title">
        <span>Resumo do dia</span>
        <small>Os totais ficam abaixo da planilha para deixar o fechamento mais limpo.</small>
    </div>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="daily-summary-grid">
        <article class="daily-summary-card emphasis">
            <span>Comercializado</span>
            <strong><?= formatarMoeda($resumo['total_comercializado'] ?? 0) ?></strong>
            <small>Serviços + adicionais</small>
        </article>

        <article class="daily-summary-card">
            <span>Recebido</span>
            <strong><?= formatarMoeda($resumo['total_recebido'] ?? 0) ?></strong>
            <small>Dinheiro, Pix e cartão</small>
        </article>

        <article class="daily-summary-card">
            <span>Pets atendidos</span>
            <strong><?= (int) ($resumoOperacional['quantidade_pets'] ?? 0) ?></strong>
            <small><?= (int) ($resumoOperacional['quantidade_lancamentos'] ?? 0) ?> serviço(s) lançado(s)</small>
        </article>

        <article class="daily-summary-card">
            <span>Adicionais</span>
            <strong><?= formatarMoeda($resumo['total_adicionais'] ?? 0) ?></strong>
            <small>Somados aos serviços</small>
        </article>

        <article class="daily-summary-card danger-soft">
            <span>Débito gerado</span>
            <strong><?= formatarMoeda($resumo['debito_gerado'] ?? 0) ?></strong>
            <small>Valor que ficou pendente</small>
        </article>

        <article class="daily-summary-card green-soft">
            <span>Débito recebido</span>
            <strong><?= formatarMoeda($resumo['debito_pago'] ?? 0) ?></strong>
            <small>Pagamentos de dívidas antigas</small>
        </article>

        <article class="daily-summary-card green-soft">
            <span>Crédito utilizado</span>
            <strong><?= formatarMoeda($resumo['credito_utilizado'] ?? 0) ?></strong>
            <small>Saldo antigo do tutor</small>
        </article>
    </section>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="daily-payment-strip">
        <div class="daily-payment-item">
            <span><i class="fa-solid fa-money-bill-wave"></i> Dinheiro</span>
            <strong><?= formatarMoeda($resumoPagamentos['dinheiro'] ?? 0) ?></strong>
        </div>
        <div class="daily-payment-item">
            <span><i class="fa-brands fa-pix"></i> Pix</span>
            <strong><?= formatarMoeda($resumoPagamentos['pix'] ?? 0) ?></strong>
        </div>
        <div class="daily-payment-item">
            <span><i class="fa-regular fa-credit-card"></i> Cartão</span>
            <strong><?= formatarMoeda($resumoPagamentos['cartao'] ?? 0) ?></strong>
        </div>
        <button class="daily-opening-box" type="button" data-open-daily-modal="opening" <?= $fechamentoFechado ? 'disabled' : '' ?>>
            <span>Abertura do caixa <i class="fa-solid fa-pen"></i></span>
            <strong><?= formatarMoeda($fechamento['valor_abertura_caixa'] ?? 0) ?></strong>
        </button>
    </section>

    <div class="daily-bottom-grid">
        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
        <section class="daily-detail-card daily-service-summary-card">
            <div class="daily-detail-title">
                <div>
                    <span class="daily-detail-icon"><i class="fa-solid fa-list-check"></i></span>
                    <div>
                        <h2>Classificação dos serviços</h2>
                        <small>Quantidade de pets e valor de cada serviço realizado no dia.</small>
                    </div>
                </div>
            </div>

            <!-- Bloco visual "daily-service-summary-list" que organiza esta parte da interface. -->
            <div class="daily-service-summary-list">
                <?php if (!$resumoServicos): ?>
                    <div class="daily-inline-empty">Sem serviços lançados ainda.</div>
                <?php else: ?>
                    <?php foreach ($resumoServicos as $servicoResumo): ?>
                        <!-- Bloco visual "daily-service-summary-item" que organiza esta parte da interface. -->
                        <div class="daily-service-summary-item">
                            <!-- Bloco visual "daily-service-summary-main" que organiza esta parte da interface. -->
                            <div class="daily-service-summary-main">
                                <span class="daily-service-category-chip"><?= escapar($servicoResumo['categoria']) ?></span>
                                <strong><?= escapar($servicoResumo['servico']) ?></strong>
                                <small><?= (int) $servicoResumo['quantidade_pets'] ?> pet(s) • <?= (int) $servicoResumo['quantidade_lancamentos'] ?> lançamento(s)</small>
                            </div>
                            <!-- Bloco visual "daily-service-summary-numbers" que organiza esta parte da interface. -->
                            <div class="daily-service-summary-numbers">
                                <strong><?= (int) $servicoResumo['quantidade_pets'] ?></strong>
                                <small>pets</small>
                                <span><?= formatarMoeda($servicoResumo['total_servico']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>


    </div>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="daily-cash-card">
        <div class="daily-cash-copy">
            <span class="daily-detail-icon cash"><i class="fa-solid fa-cash-register"></i></span>
            <div>
                <h2>Conferência do caixa</h2>
                <small>O dinheiro esperado considera a abertura do caixa + os recebimentos em dinheiro.</small>
            </div>
        </div>

        <div class="daily-cash-numbers">
            <div>
                <span>Esperado</span>
                <strong><?= formatarMoeda($dinheiroEsperado) ?></strong>
            </div>
            <div>
                <span>Contado</span>
                <strong><?= $dinheiroContadoAtual !== null ? formatarMoeda($dinheiroContadoAtual) : '—' ?></strong>
            </div>
            <div class="<?= $diferencaDinheiro !== null && abs($diferencaDinheiro) > 0.009 ? 'has-difference' : '' ?>">
                <span>Diferença</span>
                <strong><?= $diferencaDinheiro !== null ? formatarMoeda($diferencaDinheiro) : '—' ?></strong>
            </div>
        </div>

        <?php if (!$fechamentoFechado): ?>
            <button class="daily-finish-button" type="button" data-open-daily-modal="finish">
                <i class="fa-solid fa-check"></i>
                Finalizar dia
            </button>
        <?php else: ?>
            <div class="daily-closed-label">
                <i class="fa-solid fa-lock"></i>
                Fechado em <?= escapar(formatarDataHoraAgenda($fechamento['fechado_em'])) ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Modal: novo / editar registro -->
<div class="daily-modal" id="dailyLaunchModal" aria-hidden="true">
    <!-- Bloco visual "daily-modal-dialog daily-modal-wide daily-record-dialog" que organiza esta parte da interface. -->
    <div class="daily-modal-dialog daily-modal-wide daily-record-dialog" role="dialog" aria-modal="true" aria-labelledby="dailyLaunchTitle">
        <!-- Bloco visual "daily-modal-header" que organiza esta parte da interface. -->
        <div class="daily-modal-header">
            <div>
                <span id="dailyLaunchKicker">Novo registro</span>
                <h2 id="dailyLaunchTitle">Adicionar linha ao fechamento</h2>
            </div>
            <button type="button" class="daily-modal-close" data-close-daily-modal><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form method="post" action="fechamento-diario.php?data=<?= escapar($dataSelecionada) ?>" id="dailyLaunchForm">
            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="acao" value="salvar_registro">
            <!-- Campo "dailyRecordKey" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="registro" id="dailyRecordKey">

            <!-- Bloco visual "daily-modal-body" que organiza esta parte da interface. -->
            <div class="daily-modal-body">
                <div class="daily-form-section">
                    <div class="daily-form-section-title">
                        <span>1</span>
                        <div>
                            <strong>Pet</strong>
                            <small>A linha inteira pertence a este pet. Você pode trocar o pet ao editar.</small>
                        </div>
                    </div>

                    <div class="daily-form-grid">
                        <div class="field span-12">
                            <!-- Rótulo do campo "Pesquisar pet ou tutor", indicando ao usuário qual informação deve ser preenchida. -->
                            <label for="dailyPetSearch">Pesquisar pet ou tutor</label>
                            <div
                                class="search-picker"
                                id="dailyPetPicker"
                                data-search-picker="pet"
                                data-required="1">
                                <!-- Bloco visual "search-picker-input-wrap" que organiza esta parte da interface. -->
                                <div class="search-picker-input-wrap">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input
                                        id="dailyPetSearch"
                                        data-search-input
                                        type="search"
                                        autocomplete="off"
                                        placeholder="Digite o nome do pet ou tutor"
                                        required>
                                    <input
                                        id="dailyPet"
                                        data-search-value
                                        type="hidden"
                                        name="pet_id">
                                </div>
                                <!-- Bloco visual "search-picker-results" que organiza esta parte da interface. -->
                                <div class="search-picker-results" data-search-results hidden></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="daily-form-section">
                    <div class="daily-form-section-title with-action">
                        <div class="daily-form-title-left">
                            <span>2</span>
                            <div>
                                <strong>Serviços desta linha</strong>
                                <small>Pode colocar banho + creche, dois serviços da mesma categoria ou quantos precisar.</small>
                            </div>
                        </div>
                        <button type="button" class="daily-add-service-button" id="addDailyService">
                            <i class="fa-solid fa-plus"></i>
                            Adicionar serviço
                        </button>
                    </div>

                    <div class="daily-services-editor" id="dailyServicesList"></div>
                </div>

                <div class="daily-form-section">
                    <div class="daily-form-section-title">
                        <span>3</span>
                        <div>
                            <strong>Recebimento</strong>
                            <small>Pode deixar em zero e preencher quando o tutor vier buscar. O débito só é criado automaticamente ao finalizar o dia.</small>
                        </div>
                    </div>

                    <div class="daily-payment-input-grid">
                        <!-- Campo "pagamento_dinheiro" utilizado para enviar esta informação no formulário. -->
                        <label><span>Dinheiro</span><div class="money-input"><span>R$</span><input id="dailyPaymentCash" name="pagamento_dinheiro" data-daily-payment type="number" min="0" step="0.01" value="0.00"></div></label>
                        <!-- Campo "pagamento_pix" utilizado para enviar esta informação no formulário. -->
                        <label><span>Pix</span><div class="money-input"><span>R$</span><input id="dailyPaymentPix" name="pagamento_pix" data-daily-payment type="number" min="0" step="0.01" value="0.00"></div></label>
                        <!-- Campo "pagamento_cartao" utilizado para enviar esta informação no formulário. -->
                        <label><span>Cartão</span><div class="money-input"><span>R$</span><input id="dailyPaymentCard" name="pagamento_cartao" data-daily-payment type="number" min="0" step="0.01" value="0.00"></div></label>
                    </div>

                    <div class="daily-credit-row single" id="dailyCreditRow">
                        <label>
                            <span>Usar crédito do tutor</span>
                            <!-- Campo "credito_utilizado" utilizado para enviar esta informação no formulário. -->
                            <div class="money-input"><span>R$</span><input id="dailyCreditUsed" name="credito_utilizado" type="number" min="0" step="0.01" value="0.00"></div>
                        </label>
                    </div>
                </div>

                <div class="daily-live-total" aria-live="polite">
                    <div><span>Total da linha</span><strong id="dailyTotalService">R$ 0,00</strong></div>
                    <div><span>Recebido + crédito</span><strong id="dailyTotalReceived">R$ 0,00</strong></div>
                    <div id="dailyRemainingBox"><span>Falta receber</span><strong id="dailyRemaining">R$ 0,00</strong></div>
                </div>
            </div>

            <!-- Bloco visual "daily-modal-footer" que organiza esta parte da interface. -->
            <div class="daily-modal-footer">
                <button type="button" class="daily-cancel-button" data-close-daily-modal>Cancelar</button>
                <button type="submit" class="daily-primary-button">
                    <i class="fa-solid fa-check"></i>
                    <span id="dailySaveRecordText">Salvar registro</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: alterar somente o pagamento -->
<div class="daily-modal" id="dailyPaymentModal" aria-hidden="true">
    <!-- Bloco visual "daily-modal-dialog" que organiza esta parte da interface. -->
    <div class="daily-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dailyPaymentTitle">
        <!-- Bloco visual "daily-modal-header" que organiza esta parte da interface. -->
        <div class="daily-modal-header">
            <div>
                <span>Recebimento</span>
                <h2 id="dailyPaymentTitle">Alterar pagamento</h2>
            </div>
            <button type="button" class="daily-modal-close" data-close-daily-modal><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form method="post" action="fechamento-diario.php?data=<?= escapar($dataSelecionada) ?>" id="dailyPaymentForm">
            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="acao" value="atualizar_pagamento">
            <!-- Campo "dailyPaymentRecord" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="registro" id="dailyPaymentRecord">

            <!-- Bloco visual "daily-modal-body" que organiza esta parte da interface. -->
            <div class="daily-modal-body">
                <div class="daily-payment-context">
                    <div>
                        <span>Pet</span>
                        <strong id="dailyPaymentPet">—</strong>
                        <small id="dailyPaymentTutor">—</small>
                    </div>
                    <div>
                        <span>Serviços</span>
                        <strong id="dailyPaymentService">—</strong>
                        <small>Pagamento da linha completa</small>
                    </div>
                    <strong id="dailyPaymentTotal">R$ 0,00</strong>
                </div>

                <div class="daily-payment-input-grid">
                    <!-- Campo "pagamento_dinheiro" utilizado para enviar esta informação no formulário. -->
                    <label><span>Dinheiro</span><div class="money-input"><span>R$</span><input id="editPaymentCash" name="pagamento_dinheiro" data-edit-payment-value type="number" min="0" step="0.01" value="0.00"></div></label>
                    <!-- Campo "pagamento_pix" utilizado para enviar esta informação no formulário. -->
                    <label><span>Pix</span><div class="money-input"><span>R$</span><input id="editPaymentPix" name="pagamento_pix" data-edit-payment-value type="number" min="0" step="0.01" value="0.00"></div></label>
                    <!-- Campo "pagamento_cartao" utilizado para enviar esta informação no formulário. -->
                    <label><span>Cartão</span><div class="money-input"><span>R$</span><input id="editPaymentCard" name="pagamento_cartao" data-edit-payment-value type="number" min="0" step="0.01" value="0.00"></div></label>
                </div>

                <div class="daily-credit-row single" id="editPaymentCreditRow">
                    <label>
                        <span>Usar crédito do tutor</span>
                        <!-- Campo "credito_utilizado" utilizado para enviar esta informação no formulário. -->
                        <div class="money-input"><span>R$</span><input id="editPaymentTutorCredit" name="credito_utilizado" type="number" min="0" step="0.01" value="0.00"></div>
                    </label>
                </div>

                <div class="daily-live-total payment-live-total" aria-live="polite">
                    <div><span>Total da linha</span><strong id="editPaymentTotalValue">R$ 0,00</strong></div>
                    <div><span>Recebido + crédito</span><strong id="editPaymentReceived">R$ 0,00</strong></div>
                    <div id="editPaymentRemainingBox"><span>Falta receber</span><strong id="editPaymentRemaining">R$ 0,00</strong></div>
                </div>

                <div class="daily-payment-note">
                    <i class="fa-regular fa-clock"></i>
                    <span>Se ainda não pagou, deixe tudo em zero. Ao finalizar o dia, qualquer valor restante vira débito automaticamente na ficha do tutor.</span>
                </div>
            </div>

            <!-- Bloco visual "daily-modal-footer" que organiza esta parte da interface. -->
            <div class="daily-modal-footer">
                <button type="button" class="daily-cancel-button" data-close-daily-modal>Cancelar</button>
                <button type="submit" class="daily-primary-button"><i class="fa-solid fa-check"></i> Salvar pagamento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: finalizar -->
<div class="daily-modal" id="dailyFinishModal" aria-hidden="true">
    <!-- Bloco visual "daily-modal-dialog" que organiza esta parte da interface. -->
    <div class="daily-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dailyFinishTitle">
        <!-- Bloco visual "daily-modal-header" que organiza esta parte da interface. -->
        <div class="daily-modal-header">
            <div><span>Conferência final</span><h2 id="dailyFinishTitle">Finalizar o dia</h2></div>
            <button type="button" class="daily-modal-close" data-close-daily-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form method="post" action="fechamento-diario.php?data=<?= escapar($dataSelecionada) ?>">
            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="acao" value="finalizar_fechamento">
            <!-- Bloco visual "daily-modal-body" que organiza esta parte da interface. -->
            <div class="daily-modal-body">
                <div class="daily-finish-preview">
                    <span>Conferência final do dia</span>
                    <strong>Confira os valores e quantidades antes de finalizar.</strong>
                </div>

                <div class="daily-form-grid">
                    <div class="field span-12">
                        <label>Quantidade de pets por serviço</label>
                    </div>

                    <div class="field span-6">
                        <label>Banho <small>Esperado: <?= (int) ($conferenciaFechamento['banho'] ?? 0) ?></small></label>
                        <input name="quantidade_banho" type="number" min="0" step="1" required value="<?= escapar((string) ($_POST['quantidade_banho'] ?? '0')) /* [BP-ALTERAÇÃO #7] Começa em 0 em vez de vazio. */ ?>">
                    </div>

                    <div class="field span-6">
                        <label>Banho e tosa <small>Esperado: <?= (int) ($conferenciaFechamento['banho_tosa'] ?? 0) ?></small></label>
                        <input name="quantidade_banho_tosa" type="number" min="0" step="1" required value="<?= escapar((string) ($_POST['quantidade_banho_tosa'] ?? '0')) /* [BP-ALTERAÇÃO #7] Começa em 0 em vez de vazio. */ ?>">
                    </div>

                    <div class="field span-6">
                        <label>Tosa higiênica <small>Esperado: <?= (int) ($conferenciaFechamento['higienica'] ?? 0) ?></small></label>
                        <input name="quantidade_higienica" type="number" min="0" step="1" required value="<?= escapar((string) ($_POST['quantidade_higienica'] ?? '0')) /* [BP-ALTERAÇÃO #7] Começa em 0 em vez de vazio. */ ?>">
                    </div>

                    <div class="field span-6">
                        <label>Creche <small>Esperado: <?= (int) ($conferenciaFechamento['creche'] ?? 0) ?></small></label>
                        <input name="quantidade_creche" type="number" min="0" step="1" required value="<?= escapar((string) ($_POST['quantidade_creche'] ?? '0')) /* [BP-ALTERAÇÃO #7] Começa em 0 em vez de vazio. */ ?>">
                    </div>

                    <div class="field span-6">
                        <label>Hotel <small>Esperado: <?= (int) ($conferenciaFechamento['hotel'] ?? 0) ?></small></label>
                        <input name="quantidade_hotel" type="number" min="0" step="1" required value="<?= escapar((string) ($_POST['quantidade_hotel'] ?? '0')) /* [BP-ALTERAÇÃO #7] Começa em 0 em vez de vazio. */ ?>">
                    </div>

                    <div class="field span-12">
                        <label>Formas de pagamento conferidas</label>
                    </div>

                    <div class="field span-4">
                        <label>Dinheiro contado <small>Esperado: <?= formatarMoeda($conferenciaFechamento['dinheiro'] ?? 0) ?></small></label>
                        <div class="money-input"><span>R$</span><input name="valor_dinheiro_contado" type="number" min="0" step="0.01" required value="<?= escapar((string) ($_POST['valor_dinheiro_contado'] ?? '0.00')) /* [BP-ALTERAÇÃO #7] Começa em 0,00 em vez de vazio. */ ?>"></div>
                    </div>

                    <div class="field span-4">
                        <label>Pix conferido <small>Esperado: <?= formatarMoeda($conferenciaFechamento['pix'] ?? 0) ?></small></label>
                        <div class="money-input"><span>R$</span><input name="valor_pix_conferido" type="number" min="0" step="0.01" required value="<?= escapar((string) ($_POST['valor_pix_conferido'] ?? '0.00')) /* [BP-ALTERAÇÃO #7] Começa em 0,00 em vez de vazio. */ ?>"></div>
                    </div>

                    <div class="field span-4">
                        <label>Cartão conferido <small>Esperado: <?= formatarMoeda($conferenciaFechamento['cartao'] ?? 0) ?></small></label>
                        <div class="money-input"><span>R$</span><input name="valor_cartao_conferido" type="number" min="0" step="0.01" required value="<?= escapar((string) ($_POST['valor_cartao_conferido'] ?? '0.00')) /* [BP-ALTERAÇÃO #7] Começa em 0,00 em vez de vazio. */ ?>"></div>
                    </div>

                    <div class="field span-12">
                        <label>Observações <small>(opcional)</small></label>
                        <textarea name="observacoes_fechamento" placeholder="Ex.: caixa conferido, diferença explicada..."><?= escapar((string) ($_POST['observacoes_fechamento'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="daily-final-warning"><i class="fa-solid fa-lock"></i><span>Depois de finalizar, as linhas ficam bloqueadas. Qualquer valor ainda não pago será lançado automaticamente como débito na ficha do tutor. Administradores podem reabrir o dia.</span></div>
            </div>
            <!-- Bloco visual "daily-modal-footer" que organiza esta parte da interface. -->
            <div class="daily-modal-footer"><button type="button" class="daily-cancel-button" data-close-daily-modal>Cancelar</button><button type="submit" class="daily-finish-button"><i class="fa-solid fa-check"></i> Finalizar fechamento</button></div>
        </form>
    </div>
</div>

<template id="dailyServiceTemplate">
    <article class="daily-service-editor">
        <div class="daily-service-editor-head">
            <div>
                <span class="daily-service-number">Serviço 1</span>
                <small>Você pode misturar categorias na mesma linha.</small>
            </div>
            <button type="button" class="daily-remove-service" data-remove-service title="Remover serviço">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>

        <div class="daily-form-grid">
            <div class="field span-6">
                <label>Serviço</label>
                <select data-service-select data-service-field="servico_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($servicos as $servico): ?>
                        <option
                            value="<?= (int) $servico['id'] ?>"
                            data-accepts-package="<?= (int) $servico['aceita_pacote'] ?>"
                            data-debt-payment="<?= ((int) $servico['id'] === $servicoPagarDebitoId) ? '1' : '0' ?>">
                            <?= ((int) $servico['id'] === $servicoPagarDebitoId)
                                ? 'Financeiro — Pagar débito'
                                : escapar($servico['categoria']) . ' — ' . escapar($servico['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field span-6">
                <label>Como será lançado?</label>
                <select data-service-mode data-service-field="modo">
                    <option value="avulso">Serviço avulso</option>
                    <option value="pacote">Usar pacote ativo</option>
                    <option value="iniciar">Iniciar pacote</option>
                </select>
            </div>

            <div class="field span-8 daily-existing-package-box" data-existing-package-box hidden>
                <label>Pacote ativo</label>
                <select data-package-select data-service-field="pet_pacote_id">
                    <option value="">Selecione o pacote</option>
                </select>
                <small class="field-help">Depois que um pacote é iniciado, ele aparece aqui nas próximas vezes.</small>
            </div>

            <div class="field span-4 daily-existing-package-box" data-existing-package-box hidden>
                <label>Créditos usados</label>
                <input data-package-credits data-service-field="creditos_pacote_usados" type="number" min="1" step="1" value="1">
            </div>

            <div class="field span-6 daily-new-package-box" data-new-package-box hidden>
                <label>Quantidade do pacote</label>
                <input data-new-package-quantity data-service-field="novo_pacote_quantidade" type="number" min="1" max="100" step="1" value="4">
                <small class="field-help">Ex.: 4 para um pacote de quatro banhos.</small>
            </div>

            <div class="field span-6 daily-new-package-box" data-new-package-box hidden>
                <label>Validade em dias <small>(opcional)</small></label>
                <input data-new-package-validity data-service-field="novo_pacote_validade" type="number" min="1" max="3650" step="1" placeholder="Ex.: 60">
            </div>

            <div class="field span-4 daily-value-field">
                <label>Valor lançado hoje</label>
                <div class="money-input">
                    <span>R$</span>
                    <input data-service-value data-service-field="valor_servico" type="number" min="0" step="0.01" value="0.00" required>
                </div>
            </div>

            <div class="field span-8">
                <label>Observação <small>(opcional)</small></label>
                <input data-service-description data-service-field="descricao" type="text" maxlength="500" placeholder="Ex.: banho medicamentoso, tosa na tesoura...">
            </div>
        </div>

        <div class="daily-debt-payment-note" data-debt-payment-note hidden>
            <i class="fa-solid fa-wallet"></i>
            <div>
                <strong>Pagamento de débito</strong>
                <small data-debt-payment-balance>Selecione o pet para consultar quanto o tutor está devendo.</small>
                <small>O valor do serviço fica R$ 0,00. Informe o valor recebido em Dinheiro, Pix ou Cartão.</small>
            </div>
        </div>

        <div class="daily-service-extras" data-service-extras>
            <div class="daily-service-extras-head">
                <div>
                    <strong>Adicionais deste serviço</strong>
                    <small>Pode ter nenhum, um ou vários.</small>
                </div>
                <button type="button" data-add-service-extra>
                    <i class="fa-solid fa-plus"></i>
                    Adicional
                </button>
            </div>
            <div class="daily-service-extras-list" data-service-extras-list></div>
        </div>

        <div class="daily-package-start-note" data-package-start-note hidden>
            <i class="fa-solid fa-box-open"></i>
            <span>Ao salvar, o pacote começa para este pet e este primeiro atendimento usa 1 crédito. O valor acima é o que será cobrado hoje.</span>
        </div>
    </article>
</template>

<template id="dailyServiceExtraTemplate">
    <div class="daily-extra-row daily-service-extra">
        <select data-extra-field="tipo_id">
            <option value="">Tipo de adicional</option>
            <?php foreach ($tiposAdicional as $tipo): ?>
                <option value="<?= (int) $tipo['id'] ?>"><?= escapar($tipo['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="money-input">
            <span>R$</span>
            <input data-daily-extra-value data-extra-field="valor" type="number" min="0" step="0.01" value="0.00"><?php /* [BP-ALTERAÇÃO #7] Começa em 0,00 (adicional com 0 é ignorado ao salvar). */ ?>
        </div>
        <input data-extra-field="descricao" type="text" maxlength="500" placeholder="Observação (opcional)">
        <button type="button" data-remove-daily-extra aria-label="Remover adicional"><i class="fa-solid fa-xmark"></i></button>
    </div>
</template>

<!-- Script responsável pelas interações desta parte da página no navegador. -->
<script>
(() => {
    // Armazena em "modalMap" um valor usado pela lógica executada no navegador.
    const modalMap = {
        launch: document.querySelector('#dailyLaunchModal'),
        payment: document.querySelector('#dailyPaymentModal'),
        opening: document.querySelector('#dailyOpeningModal'),
        finish: document.querySelector('#dailyFinishModal')
    };

    // Armazena em "openModal" um valor usado pela lógica executada no navegador.
    const openModal = (name) => {
        // Armazena em "modal" um valor usado pela lógica executada no navegador.
        const modal = modalMap[name];
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('daily-modal-open');
        // Armazena em "first" um valor usado pela lógica executada no navegador.
        const first = modal.querySelector('input:not([type="hidden"]), select, textarea');
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (first) setTimeout(() => first.focus(), 30);
    };

    // Armazena em "closeModal" um valor usado pela lógica executada no navegador.
    <?php if ($abrirModalFinalizacao): ?>
    setTimeout(() => openModal('finish'), 0);
    <?php endif; ?>

    const closeModal = (modal) => {
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!document.querySelector('.daily-modal.is-open')) {
            document.body.classList.remove('daily-modal-open');
        }
    };

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    document.querySelectorAll('[data-open-daily-modal]').forEach((button) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        button.addEventListener('click', () => openModal(button.dataset.openDailyModal));
    });

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    document.querySelectorAll('[data-close-daily-modal]').forEach((button) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        button.addEventListener('click', () => closeModal(button.closest('.daily-modal')));
    });

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    document.querySelectorAll('.daily-modal').forEach((modal) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        modal.addEventListener('click', (event) => {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (event.target === modal) closeModal(modal);
        });
    });

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    document.addEventListener('keydown', (event) => {
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (event.key === 'Escape') {
            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
            document.querySelectorAll('.daily-modal.is-open').forEach(closeModal);
        }
    });

    // Armazena em "numberValue" um valor usado pela lógica executada no navegador.
    const numberValue = (input) => Math.max(0, Number(input?.value || 0) || 0);
    // Armazena em "money" um valor usado pela lógica executada no navegador.
    const money = (value) => value.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});

    // -----------------------------------------------------------------
    // Linha completa: pet + vários serviços + pagamento
    // -----------------------------------------------------------------
    const launchForm = document.querySelector('#dailyLaunchForm');
    // Obtém a referência do elemento "recordKey" para ler ou alterar seu conteúdo/comportamento.
    const recordKey = document.querySelector('#dailyRecordKey');
    // Obtém a referência do elemento "launchKicker" para ler ou alterar seu conteúdo/comportamento.
    const launchKicker = document.querySelector('#dailyLaunchKicker');
    // Obtém a referência do elemento "launchTitle" para ler ou alterar seu conteúdo/comportamento.
    const launchTitle = document.querySelector('#dailyLaunchTitle');
    // Obtém a referência do elemento "saveRecordText" para ler ou alterar seu conteúdo/comportamento.
    const saveRecordText = document.querySelector('#dailySaveRecordText');
    // Obtém a referência do elemento "petPicker" para ler ou alterar seu conteúdo/comportamento.
    const petPicker = document.querySelector('#dailyPetPicker');
    // Obtém a referência do elemento "petSearch" para ler ou alterar seu conteúdo/comportamento.
    const petSearch = document.querySelector('#dailyPetSearch');
    // Obtém a referência do elemento "petValue" para ler ou alterar seu conteúdo/comportamento.
    const petValue = document.querySelector('#dailyPet');
    // Obtém a referência do elemento "servicesList" para ler ou alterar seu conteúdo/comportamento.
    const servicesList = document.querySelector('#dailyServicesList');
    // Obtém a referência do elemento "serviceTemplate" para ler ou alterar seu conteúdo/comportamento.
    const serviceTemplate = document.querySelector('#dailyServiceTemplate');
    // Obtém a referência do elemento "extraTemplate" para ler ou alterar seu conteúdo/comportamento.
    const extraTemplate = document.querySelector('#dailyServiceExtraTemplate');
    // Obtém a referência do elemento "addServiceButton" para ler ou alterar seu conteúdo/comportamento.
    const addServiceButton = document.querySelector('#addDailyService');
    // Obtém a referência do elemento "creditUsed" para ler ou alterar seu conteúdo/comportamento.
    const creditUsed = document.querySelector('#dailyCreditUsed');
    // Obtém a referência do elemento "creditRow" para ler ou alterar seu conteúdo/comportamento.
    const creditRow = document.querySelector('#dailyCreditRow');
    // Obtém a referência do elemento "paymentInputs" para ler ou alterar seu conteúdo/comportamento.
    const paymentInputs = document.querySelectorAll('[data-daily-payment]');
    // Obtém a referência do elemento "totalService" para ler ou alterar seu conteúdo/comportamento.
    const totalService = document.querySelector('#dailyTotalService');
    // Obtém a referência do elemento "totalReceived" para ler ou alterar seu conteúdo/comportamento.
    const totalReceived = document.querySelector('#dailyTotalReceived');
    // Obtém a referência do elemento "remaining" para ler ou alterar seu conteúdo/comportamento.
    const remaining = document.querySelector('#dailyRemaining');
    // Obtém a referência do elemento "remainingBox" para ler ou alterar seu conteúdo/comportamento.
    const remainingBox = document.querySelector('#dailyRemainingBox');

    // Armazena em "renumberServiceRows" um valor usado pela lógica executada no navegador.
    const renumberServiceRows = () => {
        // Armazena em "rows" um valor usado pela lógica executada no navegador.
        const rows = [...servicesList.querySelectorAll('.daily-service-editor')];

        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        rows.forEach((row, serviceIndex) => {
            row.dataset.serviceIndex = String(serviceIndex);
            // Armazena em "number" um valor usado pela lógica executada no navegador.
            const number = row.querySelector('.daily-service-number');
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (number) number.textContent = `Serviço ${serviceIndex + 1}`;

            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
            row.querySelectorAll('[data-service-field]').forEach((field) => {
                field.name = `servicos[${serviceIndex}][${field.dataset.serviceField}]`;
            });

            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
            row.querySelectorAll('.daily-service-extra').forEach((extraRow, extraIndex) => {
                // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
                extraRow.querySelectorAll('[data-extra-field]').forEach((field) => {
                    field.name = `servicos[${serviceIndex}][adicionais][${extraIndex}][${field.dataset.extraField}]`;
                });
            });

            // Armazena em "removeButton" um valor usado pela lógica executada no navegador.
            const removeButton = row.querySelector('[data-remove-service]');
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (removeButton) removeButton.disabled = rows.length === 1;
        });
    };

    // Armazena em "hasDebtPaymentService" um valor usado pela lógica executada no navegador.
    const hasDebtPaymentService = () => [...servicesList.querySelectorAll('[data-service-select]')]
        .some((select) => select.selectedOptions?.[0]?.dataset?.debtPayment === '1');

    // Armazena em "updateDebtGlobalState" um valor usado pela lógica executada no navegador.
    const updateDebtGlobalState = () => {
        // Armazena em "debtMode" um valor usado pela lógica executada no navegador.
        const debtMode = hasDebtPaymentService();
        addServiceButton.disabled = debtMode;
        creditRow.hidden = debtMode;
        creditUsed.disabled = debtMode;

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (debtMode) {
            creditUsed.value = '0.00';
        }
    };

    // Armazena em "recalculate" um valor usado pela lógica executada no navegador.
    const recalculate = () => {
        // Armazena em "total" um valor usado pela lógica executada no navegador.
        let total = 0;

        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        servicesList.querySelectorAll('[data-service-value]').forEach((input) => {
            total += numberValue(input);
        });

        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        servicesList.querySelectorAll('[data-daily-extra-value]').forEach((input) => {
            total += numberValue(input);
        });

        // Armazena em "paid" um valor usado pela lógica executada no navegador.
        let paid = numberValue(creditUsed);
        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        paymentInputs.forEach((input) => paid += numberValue(input));

        // Armazena em "debtMode" um valor usado pela lógica executada no navegador.
        const debtMode = hasDebtPaymentService();
        // Armazena em "balance" um valor usado pela lógica executada no navegador.
        const balance = debtMode ? 0 : total - paid;
        totalService.textContent = money(total);
        totalReceived.textContent = money(paid);

        // Armazena em "balanceLabel" um valor usado pela lógica executada no navegador.
        const balanceLabel = remainingBox.querySelector('span');
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (debtMode) {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (balanceLabel) balanceLabel.textContent = 'Débito pago';
            remaining.textContent = money(paid);
        } else {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (balanceLabel) balanceLabel.textContent = 'Falta receber';
            remaining.textContent = money(Math.max(0, balance));
        }

        remainingBox.classList.toggle('is-negative', !debtMode && balance < -0.009);
        remainingBox.classList.toggle('is-positive', !debtMode && balance > 0.009);
        updateDebtGlobalState();
    };

    // Armazena em "refreshDebtBalance" um valor usado pela lógica executada no navegador.
    const refreshDebtBalance = async (row) => {
        // Armazena em "serviceSelect" um valor usado pela lógica executada no navegador.
        const serviceSelect = row.querySelector('[data-service-select]');
        // Armazena em "selected" um valor usado pela lógica executada no navegador.
        const selected = serviceSelect?.selectedOptions?.[0];
        // Armazena em "note" um valor usado pela lógica executada no navegador.
        const note = row.querySelector('[data-debt-payment-note]');
        // Armazena em "balanceText" um valor usado pela lógica executada no navegador.
        const balanceText = row.querySelector('[data-debt-payment-balance]');
        // Armazena em "debtMode" um valor usado pela lógica executada no navegador.
        const debtMode = selected?.dataset?.debtPayment === '1';

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!note || !balanceText) return;
        note.hidden = !debtMode;
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!debtMode) return;

        // Armazena em "petId" um valor usado pela lógica executada no navegador.
        const petId = petValue.value || '';
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!petId) {
            balanceText.textContent = 'Selecione o pet para consultar quanto o tutor está devendo.';
            return;
        }

        balanceText.textContent = 'Consultando débito do tutor...';
        try {
            // Armazena em "response" um valor usado pela lógica executada no navegador.
            const response = await fetch(`busca-registros.php?tipo=saldo-debito&pet_id=${encodeURIComponent(petId)}`);
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (!response.ok) throw new Error('Falha');
            // Armazena em "data" um valor usado pela lógica executada no navegador.
            const data = await response.json();
            balanceText.textContent = `Débito atual do tutor: ${money(Number(data.saldo || 0))}`;
        } catch (error) {
            balanceText.textContent = 'Não foi possível consultar o débito agora.';
        }
    };

    // Armazena em "addExtraToService" um valor usado pela lógica executada no navegador.
    const addExtraToService = (serviceRow, data = null) => {
        // Armazena em "extraRow" um valor usado pela lógica executada no navegador.
        const extraRow = extraTemplate.content.firstElementChild.cloneNode(true);
        serviceRow.querySelector('[data-service-extras-list]').appendChild(extraRow);

        // Armazena em "type" um valor usado pela lógica executada no navegador.
        const type = extraRow.querySelector('[data-extra-field="tipo_id"]');
        // Armazena em "value" um valor usado pela lógica executada no navegador.
        const value = extraRow.querySelector('[data-extra-field="valor"]');
        // Armazena em "description" um valor usado pela lógica executada no navegador.
        const description = extraRow.querySelector('[data-extra-field="descricao"]');

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (data) {
            type.value = data.tipo_id || '';
            value.value = data.valor || '0';
            description.value = data.descricao || '';
        }

        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        extraRow.querySelector('[data-remove-daily-extra]')?.addEventListener('click', () => {
            extraRow.remove();
            renumberServiceRows();
            recalculate();
        });

        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        value?.addEventListener('input', recalculate);
        renumberServiceRows();
        recalculate();

        return extraRow;
    };

    // Armazena em "setPackageMode" um valor usado pela lógica executada no navegador.
    const setPackageMode = async (row, preferredPackage = '') => {
        // Armazena em "mode" um valor usado pela lógica executada no navegador.
        const mode = row.querySelector('[data-service-mode]');
        // Armazena em "serviceSelect" um valor usado pela lógica executada no navegador.
        const serviceSelect = row.querySelector('[data-service-select]');
        // Armazena em "packageBoxes" um valor usado pela lógica executada no navegador.
        const packageBoxes = row.querySelectorAll('[data-existing-package-box]');
        // Armazena em "newPackageBoxes" um valor usado pela lógica executada no navegador.
        const newPackageBoxes = row.querySelectorAll('[data-new-package-box]');
        // Armazena em "packageNote" um valor usado pela lógica executada no navegador.
        const packageNote = row.querySelector('[data-package-start-note]');
        // Armazena em "packageSelect" um valor usado pela lógica executada no navegador.
        const packageSelect = row.querySelector('[data-package-select]');
        // Armazena em "packageCredits" um valor usado pela lógica executada no navegador.
        const packageCredits = row.querySelector('[data-package-credits]');
        // Armazena em "selectedService" um valor usado pela lógica executada no navegador.
        const selectedService = serviceSelect.selectedOptions?.[0];
        // Armazena em "debtPayment" um valor usado pela lógica executada no navegador.
        const debtPayment = selectedService?.dataset?.debtPayment === '1';
        // Armazena em "acceptsPackage" um valor usado pela lógica executada no navegador.
        const acceptsPackage = !debtPayment && selectedService?.dataset?.acceptsPackage === '1';
        // Armazena em "valueField" um valor usado pela lógica executada no navegador.
        const valueField = row.querySelector('[data-service-value]');
        // Armazena em "extrasBox" um valor usado pela lógica executada no navegador.
        const extrasBox = row.querySelector('[data-service-extras]');
        // Armazena em "debtNote" um valor usado pela lógica executada no navegador.
        const debtNote = row.querySelector('[data-debt-payment-note]');

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (debtPayment && servicesList.querySelectorAll('.daily-service-editor').length > 1) {
            alert('Pagar débito precisa ficar sozinho na linha. Remova os outros serviços primeiro.');
            serviceSelect.value = '';
            return setPackageMode(row);
        }

        mode.disabled = debtPayment;
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (debtPayment) {
            mode.value = 'avulso';
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (valueField) {
                valueField.value = '0.00';
                valueField.readOnly = true;
            }
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (extrasBox) extrasBox.hidden = true;
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (debtNote) debtNote.hidden = false;
        } else {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (valueField) valueField.readOnly = false;
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (extrasBox) extrasBox.hidden = false;
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (debtNote) debtNote.hidden = true;
        }

        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        [...mode.options].forEach((option) => {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (option.value === 'pacote' || option.value === 'iniciar') {
                option.disabled = !acceptsPackage;
            }
        });

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!acceptsPackage && mode.value !== 'avulso') {
            mode.value = 'avulso';
        }

        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        packageBoxes.forEach((box) => box.hidden = mode.value !== 'pacote');
        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        newPackageBoxes.forEach((box) => box.hidden = mode.value !== 'iniciar');
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (packageNote) packageNote.hidden = mode.value !== 'iniciar';

        packageCredits.required = mode.value === 'pacote';
        packageSelect.required = mode.value === 'pacote';

        await refreshDebtBalance(row);
        recalculate();

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (mode.value !== 'pacote') {
            packageSelect.required = false;
            return;
        }

        // Armazena em "petId" um valor usado pela lógica executada no navegador.
        const petId = petValue.value || '';
        // Armazena em "serviceId" um valor usado pela lógica executada no navegador.
        const serviceId = serviceSelect.value || '';

        packageSelect.innerHTML = '<option value="">Buscando pacotes...</option>';
        packageSelect.disabled = true;

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!petId || !serviceId) {
            packageSelect.innerHTML = '<option value="">Selecione o pet e o serviço primeiro</option>';
            return;
        }

        try {
            // Armazena em "ignore" um valor usado pela lógica executada no navegador.
            const ignore = recordKey.value
                ? `&ignorar_registro=${encodeURIComponent(recordKey.value)}`
                : '';

            // Armazena em "response" um valor usado pela lógica executada no navegador.
            const response = await fetch(
                `busca-registros.php?tipo=pacote&pet_id=${encodeURIComponent(petId)}&servico_id=${encodeURIComponent(serviceId)}&data=<?= escapar($dataSelecionada) ?>&fechamento_id=<?= (int) $fechamentoId ?>${ignore}`
            );

            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (!response.ok) throw new Error('Falha ao buscar pacotes');

            // Armazena em "packages" um valor usado pela lógica executada no navegador.
            const packages = await response.json();
            packageSelect.innerHTML = '<option value="">Selecione o pacote ativo</option>';

            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
            packages.forEach((item) => {
                // Armazena em "option" um valor usado pela lógica executada no navegador.
                const option = document.createElement('option');
                option.value = item.id;
                option.dataset.remaining = item.remaining;
                option.textContent = `${item.label} — ${item.remaining} crédito${Number(item.remaining) === 1 ? '' : 's'} restante${Number(item.remaining) === 1 ? '' : 's'}`;
                packageSelect.appendChild(option);
            });

            packageSelect.disabled = false;

            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (preferredPackage) {
                packageSelect.value = String(preferredPackage);
            }

            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (!packages.length) {
                packageSelect.innerHTML = '<option value="">Nenhum pacote ativo. Use “Iniciar pacote”.</option>';
            }

            // Armazena em "selected" um valor usado pela lógica executada no navegador.
            const selected = packageSelect.selectedOptions?.[0];
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (selected?.dataset?.remaining) {
                packageCredits.max = selected.dataset.remaining;
            }
        } catch (error) {
            packageSelect.innerHTML = '<option value="">Não foi possível carregar os pacotes</option>';
        }
    };

    // Armazena em "bindServiceRow" um valor usado pela lógica executada no navegador.
    const bindServiceRow = (row) => {
        // Armazena em "serviceSelect" um valor usado pela lógica executada no navegador.
        const serviceSelect = row.querySelector('[data-service-select]');
        // Armazena em "mode" um valor usado pela lógica executada no navegador.
        const mode = row.querySelector('[data-service-mode]');
        // Armazena em "packageSelect" um valor usado pela lógica executada no navegador.
        const packageSelect = row.querySelector('[data-package-select]');
        // Armazena em "packageCredits" um valor usado pela lógica executada no navegador.
        const packageCredits = row.querySelector('[data-package-credits]');
        // Armazena em "value" um valor usado pela lógica executada no navegador.
        const value = row.querySelector('[data-service-value]');

        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        row.querySelector('[data-remove-service]')?.addEventListener('click', () => {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (servicesList.querySelectorAll('.daily-service-editor').length <= 1) return;
            row.remove();
            renumberServiceRows();
            recalculate();
        });

        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        row.querySelector('[data-add-service-extra]')?.addEventListener('click', () => {
            addExtraToService(row);
        });

        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        serviceSelect?.addEventListener('change', () => setPackageMode(row));
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        mode?.addEventListener('change', () => setPackageMode(row));
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        packageSelect?.addEventListener('change', () => {
            // Armazena em "selected" um valor usado pela lógica executada no navegador.
            const selected = packageSelect.selectedOptions?.[0];
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (selected?.dataset?.remaining) {
                packageCredits.max = selected.dataset.remaining;
                // Verifica esta condição antes de alterar a interface ou continuar o processamento.
                if (numberValue(packageCredits) > Number(packageCredits.max)) {
                    packageCredits.value = packageCredits.max;
                }
            }
        });
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        value?.addEventListener('input', recalculate);
    };

    // Armazena em "addServiceRow" um valor usado pela lógica executada no navegador.
    const addServiceRow = async (data = null) => {
        // Armazena em "row" um valor usado pela lógica executada no navegador.
        const row = serviceTemplate.content.firstElementChild.cloneNode(true);
        servicesList.appendChild(row);
        bindServiceRow(row);

        // Armazena em "serviceSelect" um valor usado pela lógica executada no navegador.
        const serviceSelect = row.querySelector('[data-service-select]');
        // Armazena em "mode" um valor usado pela lógica executada no navegador.
        const mode = row.querySelector('[data-service-mode]');
        // Armazena em "value" um valor usado pela lógica executada no navegador.
        const value = row.querySelector('[data-service-value]');
        // Armazena em "description" um valor usado pela lógica executada no navegador.
        const description = row.querySelector('[data-service-description]');
        // Armazena em "packageCredits" um valor usado pela lógica executada no navegador.
        const packageCredits = row.querySelector('[data-package-credits]');
        // Armazena em "quantity" um valor usado pela lógica executada no navegador.
        const quantity = row.querySelector('[data-new-package-quantity]');
        // Armazena em "validity" um valor usado pela lógica executada no navegador.
        const validity = row.querySelector('[data-new-package-validity]');

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (data) {
            serviceSelect.value = String(data.servico_id || '');
            mode.value = data.pet_pacote_id ? 'pacote' : 'avulso';
            value.value = data.valor_servico ?? '0';
            description.value = data.descricao || '';
            packageCredits.value = data.creditos_pacote_usados || '1';
            quantity.value = data.novo_pacote_quantidade || '4';
            validity.value = data.novo_pacote_validade || '';

            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
            (data.adicionais || []).forEach((extra) => addExtraToService(row, extra));

            await setPackageMode(row, data.pet_pacote_id || '');
        } else {
            await setPackageMode(row);
        }

        renumberServiceRows();
        recalculate();
        return row;
    };

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    addServiceButton?.addEventListener('click', () => addServiceRow());

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    paymentInputs.forEach((input) => input.addEventListener('input', recalculate));
    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    creditUsed?.addEventListener('input', recalculate);

    // Armazena em "refreshAllPackages" um valor usado pela lógica executada no navegador.
    const refreshAllPackages = () => {
        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        servicesList.querySelectorAll('.daily-service-editor').forEach((row) => {
            setPackageMode(row);
        });
    };

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    petPicker?.addEventListener('search-picker:selected', refreshAllPackages);
    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    petSearch?.addEventListener('input', () => setTimeout(refreshAllPackages, 0));

    // Armazena em "setPet" um valor usado pela lógica executada no navegador.
    const setPet = (id = '', label = '') => {
        petValue.value = id ? String(id) : '';
        petSearch.value = label || '';
        petSearch.dataset.selectedLabel = label || '';
        petSearch.setCustomValidity('');
    };

    // Armazena em "resetRecordForm" um valor usado pela lógica executada no navegador.
    const resetRecordForm = async () => {
        launchForm.reset();
        recordKey.value = '';
        launchKicker.textContent = 'Novo registro';
        launchTitle.textContent = 'Adicionar linha ao fechamento';
        saveRecordText.textContent = 'Salvar registro';
        setPet('', '');
        servicesList.innerHTML = '';
        document.querySelector('#dailyPaymentCash').value = '0.00';
        document.querySelector('#dailyPaymentPix').value = '0.00';
        document.querySelector('#dailyPaymentCard').value = '0.00';
        creditUsed.value = '0.00';
        await addServiceRow();
        recalculate();
    };

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    document.querySelectorAll('[data-open-daily-modal="launch"]').forEach((button) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        button.addEventListener('click', resetRecordForm);
    });

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    document.querySelectorAll('[data-edit-record]').forEach((button) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        button.addEventListener('click', async () => {
            // Armazena em "key" um valor usado pela lógica executada no navegador.
            const key = button.dataset.record || '';
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (!key) return;

            button.disabled = true;

            try {
                // Armazena em "response" um valor usado pela lógica executada no navegador.
                const response = await fetch(
                    `busca-registros.php?tipo=registro-fechamento&registro=${encodeURIComponent(key)}&fechamento_id=<?= (int) $fechamentoId ?>`
                );

                // Verifica esta condição antes de alterar a interface ou continuar o processamento.
                if (!response.ok) throw new Error('Falha ao carregar registro');

                // Armazena em "data" um valor usado pela lógica executada no navegador.
                const data = await response.json();
                launchForm.reset();
                recordKey.value = key;
                launchKicker.textContent = 'Editar linha completa';
                launchTitle.textContent = `Editar ${data.pet_label || 'registro'}`;
                saveRecordText.textContent = 'Salvar alterações';
                setPet(data.pet_id, data.pet_label);
                servicesList.innerHTML = '';

                for (const service of (data.servicos || [])) {
                    await addServiceRow(service);
                }

                // Verifica esta condição antes de alterar a interface ou continuar o processamento.
                if (!data.servicos?.length) {
                    await addServiceRow();
                }

                document.querySelector('#dailyPaymentCash').value = data.pagamentos?.dinheiro || '0';
                document.querySelector('#dailyPaymentPix').value = data.pagamentos?.pix || '0';
                document.querySelector('#dailyPaymentCard').value = data.pagamentos?.cartao || '0';
                creditUsed.value = data.credito_utilizado || '0';

                recalculate();
                openModal('launch');
            } catch (error) {
                alert('Não foi possível abrir esta linha para edição.');
            } finally {
                button.disabled = false;
            }
        });
    });

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.
    if (!servicesList.children.length) {
        addServiceRow();
    }

    // -----------------------------------------------------------------
    // Alterar somente o pagamento
    // -----------------------------------------------------------------
    const paymentRecord = document.querySelector('#dailyPaymentRecord');
    // Obtém a referência do elemento "paymentPet" para ler ou alterar seu conteúdo/comportamento.
    const paymentPet = document.querySelector('#dailyPaymentPet');
    // Obtém a referência do elemento "paymentTutor" para ler ou alterar seu conteúdo/comportamento.
    const paymentTutor = document.querySelector('#dailyPaymentTutor');
    // Obtém a referência do elemento "paymentService" para ler ou alterar seu conteúdo/comportamento.
    const paymentService = document.querySelector('#dailyPaymentService');
    // Obtém a referência do elemento "paymentTotal" para ler ou alterar seu conteúdo/comportamento.
    const paymentTotal = document.querySelector('#dailyPaymentTotal');
    // Obtém a referência do elemento "editPaymentTotalValue" para ler ou alterar seu conteúdo/comportamento.
    const editPaymentTotalValue = document.querySelector('#editPaymentTotalValue');
    // Obtém a referência do elemento "editPaymentInputs" para ler ou alterar seu conteúdo/comportamento.
    const editPaymentInputs = document.querySelectorAll('[data-edit-payment-value]');
    // Obtém a referência do elemento "editTutorCredit" para ler ou alterar seu conteúdo/comportamento.
    const editTutorCredit = document.querySelector('#editPaymentTutorCredit');
    // Obtém a referência do elemento "editPaymentCreditRow" para ler ou alterar seu conteúdo/comportamento.
    const editPaymentCreditRow = document.querySelector('#editPaymentCreditRow');
    // Obtém a referência do elemento "editReceived" para ler ou alterar seu conteúdo/comportamento.
    const editReceived = document.querySelector('#editPaymentReceived');
    // Obtém a referência do elemento "editRemaining" para ler ou alterar seu conteúdo/comportamento.
    const editRemaining = document.querySelector('#editPaymentRemaining');
    // Obtém a referência do elemento "editRemainingBox" para ler ou alterar seu conteúdo/comportamento.
    const editRemainingBox = document.querySelector('#editPaymentRemainingBox');
    // Armazena em "editTotal" um valor usado pela lógica executada no navegador.
    let editTotal = 0;
    // Armazena em "editDebtPayment" um valor usado pela lógica executada no navegador.
    let editDebtPayment = false;

    // Armazena em "recalculateEditedPayment" um valor usado pela lógica executada no navegador.
    const recalculateEditedPayment = () => {
        // Armazena em "received" um valor usado pela lógica executada no navegador.
        let received = numberValue(editTutorCredit);
        // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
        editPaymentInputs.forEach((input) => received += numberValue(input));
        // Armazena em "balance" um valor usado pela lógica executada no navegador.
        const balance = editDebtPayment ? 0 : editTotal - received;

        editPaymentTotalValue.textContent = money(editTotal);
        editReceived.textContent = money(received);
        // Armazena em "balanceLabel" um valor usado pela lógica executada no navegador.
        const balanceLabel = editRemainingBox.querySelector('span');

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (editDebtPayment) {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (balanceLabel) balanceLabel.textContent = 'Débito pago';
            editRemaining.textContent = money(received);
        } else {
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (balanceLabel) balanceLabel.textContent = 'Falta receber';
            editRemaining.textContent = money(Math.max(0, balance));
        }

        editRemainingBox.classList.toggle('is-positive', !editDebtPayment && balance > 0.009);
        editRemainingBox.classList.toggle('is-negative', !editDebtPayment && balance < -0.009);
    };

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    [...editPaymentInputs, editTutorCredit].forEach((input) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        input?.addEventListener('input', recalculateEditedPayment);
    });

    // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.
    document.querySelectorAll('[data-edit-payment]').forEach((button) => {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        button.addEventListener('click', () => {
            editDebtPayment = button.dataset.pagamentoDebito === '1';
            editTotal = Number(button.dataset.total || 0) || 0;
            editPaymentCreditRow.hidden = editDebtPayment;
            editTutorCredit.disabled = editDebtPayment;
            // Verifica esta condição antes de alterar a interface ou continuar o processamento.
            if (editDebtPayment) editTutorCredit.value = '0';
            paymentRecord.value = button.dataset.record || '';
            paymentPet.textContent = button.dataset.pet || '—';
            paymentTutor.textContent = button.dataset.tutor || '—';
            paymentService.textContent = button.dataset.servico || '—';
            paymentTotal.textContent = money(editTotal);

            document.querySelector('#editPaymentCash').value = button.dataset.dinheiro || '0';
            document.querySelector('#editPaymentPix').value = button.dataset.pix || '0';
            document.querySelector('#editPaymentCard').value = button.dataset.cartao || '0';
            editTutorCredit.value = button.dataset.creditoUsado || '0';

            recalculateEditedPayment();
            openModal('payment');
        });
    });
})();

// Adicionar dentro da IIFE existente na tag <script> do fechamento-diario.php

const setupPetSearchPicker = () => {
    const picker = document.querySelector('#dailyPetPicker');
    if (!picker) return;

    const input = picker.querySelector('[data-search-input]');
    const hiddenValue = picker.querySelector('[data-search-value]');
    const resultsBox = picker.querySelector('[data-search-results]');
    let debounceTimer = null;

    input.addEventListener('input', () => {
        const query = input.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            resultsBox.hidden = true;
            resultsBox.innerHTML = '';
            hiddenValue.value = '';
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`busca-registros.php?tipo=pet&q=${encodeURIComponent(query)}`);
                if (!response.ok) throw new Error('Erro na requisição');

                const data = await response.json();
                resultsBox.innerHTML = '';

                if (data.length === 0) {
                    resultsBox.innerHTML = '<div class="search-picker-no-results" style="padding: 10px; font-size: 0.875rem; color: #666;">Nenhum pet encontrado</div>';
                } else {
                    data.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'search-picker-item';
                        itemDiv.style.cursor = 'pointer';
                        itemDiv.style.padding = '8px 12px';
                        itemDiv.textContent = item.label;

                        itemDiv.addEventListener('click', () => {
                            input.value = item.label;
                            hiddenValue.value = item.id;
                            resultsBox.hidden = true;

                            // Dispara evento para atualização automática de pacotes e saldo de débitos
                            picker.dispatchEvent(new CustomEvent('search-picker:selected', { detail: item }));
                        });

                        resultsBox.appendChild(itemDiv);
                    });
                }

                resultsBox.hidden = false;
            } catch (error) {
                console.error('Erro ao consultar pets:', error);
            }
        }, 300);
    });

    // Oculta caixa de resultados ao clicar fora do componente
    document.addEventListener('click', (event) => {
        if (!picker.contains(event.target)) {
            resultsBox.hidden = true;
        }
    });
};

// Executa a inicialização do seletor
setupPetSearchPicker();
</script>

<?php require __DIR__ . '/../includes/rodape.php'; ?>

