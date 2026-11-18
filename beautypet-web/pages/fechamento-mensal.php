<?php
/**
 * ARQUIVO: fechamento-mensal.php
 * RESPONSABILIDADE: Fechamento mensal.
 * Consolida os fechamentos diários por competência, totaliza serviços e formas de pagamento e permite finalizar ou reabrir o mês.
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
// Guarda o mês selecionado no formato AAAA-MM para montar o fechamento mensal.
$competencia = (string) ($_GET['mes'] ?? date('Y-m'));

/**
 * Executa a responsabilidade específica de "competenciaMensalValida" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function competenciaMensalValida(string $competencia): bool
{
    // Verifica a condição abaixo antes de executar este bloco.
    if (!preg_match('/^(\d{4})-(\d{2})$/', $competencia, $partes)) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return false;
    }

    // Representa a competência mensal utilizada nas consultas e na navegação entre meses.
    $mes = (int) $partes[2];

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $mes >= 1 && $mes <= 12;
}

/**
 * Executa a responsabilidade específica de "nomeMesFechamento" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomeMesFechamento(int $mes): string
{
    // Armazena a coleção "nomes" carregada para ser percorrida e exibida/processada a seguir.
    $nomes = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return $nomes[$mes] ?? '';
}

/**
 * Executa a responsabilidade específica de "classeStatusMensal" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function classeStatusMensal(?string $status): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return match ($status) {
        'FECHADO' => 'closed',
        'REABERTO' => 'reopened',
        default => 'open',
    };
}

/**
 * Executa a responsabilidade específica de "nomeStatusMensal" e devolve/produz o resultado necessário para o restante do módulo.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function nomeStatusMensal(?string $status): string
{
    // Devolve o resultado calculado para o ponto que chamou esta função.
    return match ($status) {
        'FECHADO' => 'Fechado',
        'REABERTO' => 'Reaberto',
        default => 'Aberto',
    };
}

// Verifica a condição abaixo antes de executar este bloco.
if (!competenciaMensalValida($competencia)) {
    // Guarda o mês selecionado no formato AAAA-MM para montar o fechamento mensal.
    $competencia = date('Y-m');
}

[$anoTexto, $mesTexto] = explode('-', $competencia);
// Armazena em "ano" o valor necessário para as próximas etapas deste fluxo.
$ano = (int) $anoTexto;
// Representa a competência mensal utilizada nas consultas e na navegação entre meses.
$mes = (int) $mesTexto;

// Armazena a coleção "inicioMes" carregada para ser percorrida e exibida/processada a seguir.
$inicioMes = sprintf('%04d-%02d-01', $ano, $mes);
// Armazena a coleção "fimMes" carregada para ser percorrida e exibida/processada a seguir.
$fimMes = (new DateTime($inicioMes))->modify('last day of this month')->format('Y-m-d');
// Armazena a coleção "diasNoMes" carregada para ser percorrida e exibida/processada a seguir.
$diasNoMes = (int) (new DateTime($fimMes))->format('d');

// Armazena em "competenciaAnterior" o valor necessário para as próximas etapas deste fluxo.
$competenciaAnterior = (new DateTime($inicioMes))->modify('-1 month')->format('Y-m');
// Armazena em "competenciaSeguinte" o valor necessário para as próximas etapas deste fluxo.
$competenciaSeguinte = (new DateTime($inicioMes))->modify('+1 month')->format('Y-m');

// Prepara uma consulta em "fechamentos_mensais" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare(
    'SELECT *
     FROM fechamentos_mensais
     WHERE ano = ?
       AND mes = ?
     LIMIT 1'
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([$ano, $mes]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$fechamentoMensal = $stmt->fetch();

// Verifica a condição abaixo antes de executar este bloco.
if (!$fechamentoMensal) {
    // Prepara uma consulta em "fechamentos_mensais" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "INSERT INTO fechamentos_mensais (ano, mes, status)
         VALUES (?, ?, 'ABERTO')"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([$ano, $mes]);

    // Prepara uma consulta em "fechamentos_mensais" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT *
         FROM fechamentos_mensais
         WHERE ano = ?
           AND mes = ?
         LIMIT 1'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([$ano, $mes]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $fechamentoMensal = $stmt->fetch();
}

// Armazena o identificador "fechamentoMensalId" utilizado para relacionar ou localizar o registro no banco.
$fechamentoMensalId = (int) $fechamentoMensal['id'];
// Armazena em "mensalFechado" o valor necessário para as próximas etapas deste fluxo.
$mensalFechado = ($fechamentoMensal['status'] ?? '') === 'FECHADO';

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação o formulário solicitou para direcionar o processamento correto.
    $acao = (string) ($_POST['acao'] ?? '');

    // HEURÍSTICA 9: antes não havia try/catch; uma falha do banco virava página de erro fatal.
    try {
        // Verifica a condição abaixo antes de executar este bloco.
        if ($acao === 'finalizar_mes' && !$mensalFechado) {
            // Guarda observações informadas pela equipe para acompanhar o cadastro, agendamento ou lançamento.
            $observacoes = textoOuNull($_POST['observacoes'] ?? null);

            // Prepara uma consulta em "fechamentos_diarios" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM fechamentos_diarios f
                 WHERE f.data_fechamento BETWEEN ? AND ?
                   AND f.status <> 'FECHADO'
                   AND EXISTS (
                        SELECT 1
                        FROM lancamentos_diarios l
                        WHERE l.fechamento_diario_id = f.id
                   )"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([$inicioMes, $fimMes]);
            // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
            $diasPendentes = (int) $stmt->fetchColumn();

            // Verifica a condição abaixo antes de executar este bloco.
            if ($diasPendentes > 0) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                // HEURÍSTICA 9: informa quantos dias faltam e onde resolver.
                $mensagem = 'Ainda ' . ($diasPendentes === 1 ? 'existe 1 dia' : 'existem ' . $diasPendentes . ' dias')
                    . ' com movimento sem fechamento. Abra o Fechamento diário desses dias, finalize-os e depois feche o mês.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Prepara a atualização do registro correspondente em "fechamentos_mensais" com os novos dados.
                $stmt = $pdo->prepare(
                    "UPDATE fechamentos_mensais
                     SET status = 'FECHADO',
                         observacoes = ?,
                         fechado_por = ?,
                         fechado_em = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute([$observacoes, $usuarioId, $fechamentoMensalId]);

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: fechamento-mensal.php?mes=' . urlencode($competencia) . '&fechado=1');
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            }
        }

        // Verifica a condição abaixo antes de executar este bloco.
        if ($acao === 'reabrir_mes' && $administrador && $mensalFechado) {
            // Prepara uma consulta em "vw_fechamento_diario" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                "UPDATE fechamentos_mensais
                 SET status = 'REABERTO',
                     fechado_por = NULL,
                     fechado_em = NULL
                 WHERE id = ?"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([$fechamentoMensalId]);

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header('Location: fechamento-mensal.php?mes=' . urlencode($competencia) . '&reaberto=1');
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }

        // HEURÍSTICA 9: antes o clique de quem não é administrador era ignorado sem explicação.
        if ($acao === 'reabrir_mes' && !$administrador) {
            $mensagem = 'Somente administradores podem reabrir o mês. Peça a um administrador ou troque de usuário no menu do perfil.';
            $tipoMensagem = 'error';
        }
    } catch (Throwable $erro) {
        error_log('[BeautyPet] fechamento-mensal: ' . $erro->getMessage());
        $mensagem = $acao === 'reabrir_mes'
            ? 'Não foi possível reabrir o mês agora. Nada foi alterado: tente novamente em instantes.'
            : 'Não foi possível fechar o mês agora. Nada foi alterado: confira a internet e tente novamente.';
        $tipoMensagem = 'error';
    }
}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verifica a condição abaixo antes de executar este bloco.
    if (isset($_GET['fechado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Fechamento mensal finalizado com sucesso.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['reaberto'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Fechamento mensal reaberto.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    }
}

// Dados de cada dia. O resumo financeiro usa o mesmo fechamento diário já existente,
// mas a quantidade de pets e serviços ignora o serviço financeiro "Pagar débito".
$stmt = $pdo->prepare(
    "SELECT
        v.*,
        COALESCE(op.quantidade_servicos, 0) AS quantidade_servicos_operacionais,
        COALESCE(op.quantidade_pets, 0) AS quantidade_pets_operacionais,
        COALESCE(pg_real.dinheiro, 0) AS pagamento_dinheiro_real,
        COALESCE(pg_real.pix, 0) AS pagamento_pix_real,
        COALESCE(pg_real.cartao, 0) AS pagamento_cartao_real,
        COALESCE(pg_real.total_recebido, 0) AS pagamento_total_real
     FROM vw_fechamento_diario v
     LEFT JOIN (
        SELECT
            l.fechamento_diario_id,
            COUNT(l.id) AS quantidade_servicos,
            COUNT(DISTINCT l.pet_id) AS quantidade_pets
        FROM lancamentos_diarios l
        JOIN servicos s ON s.id = l.servico_id
        JOIN categorias_servico cs ON cs.id = s.categoria_id
        WHERE LOWER(cs.nome) <> 'financeiro'
        GROUP BY l.fechamento_diario_id
     ) op ON op.fechamento_diario_id = v.fechamento_diario_id
     LEFT JOIN (
        SELECT
            p.fechamento_diario_id,
            SUM(CASE
                WHEN LOWER(fp.nome) = 'dinheiro' THEN p.valor
                ELSE 0
            END) AS dinheiro,
            SUM(CASE
                WHEN LOWER(fp.nome) = 'pix' THEN p.valor
                ELSE 0
            END) AS pix,
            SUM(CASE
                WHEN LOWER(fp.nome) LIKE 'cart%' THEN p.valor
                ELSE 0
            END) AS cartao,
            SUM(p.valor) AS total_recebido
        FROM pagamentos p
        JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
        WHERE p.estornado = FALSE
        GROUP BY p.fechamento_diario_id
     ) pg_real ON pg_real.fechamento_diario_id = v.fechamento_diario_id
     WHERE v.data_fechamento BETWEEN ? AND ?
     ORDER BY v.data_fechamento"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([$inicioMes, $fimMes]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$dadosDiasBanco = $stmt->fetchAll();

// Armazena em "dadosPorData" o valor necessário para as próximas etapas deste fluxo.
$dadosPorData = [];

// Percorre cada item da coleção para processar os registros individualmente.
foreach ($dadosDiasBanco as $diaBanco) {
    // Recalcula as formas de pagamento diretamente da tabela de pagamentos não estornados
    $diaBanco['dinheiro'] = (float) ($diaBanco['pagamento_dinheiro_real'] ?? 0);
    $diaBanco['pix'] = (float) ($diaBanco['pagamento_pix_real'] ?? 0);
    $diaBanco['cartao'] = (float) ($diaBanco['pagamento_cartao_real'] ?? 0);
    $diaBanco['total_recebido'] = (float) ($diaBanco['pagamento_total_real'] ?? 0);

    $dadosPorData[(string) $diaBanco['data_fechamento']] = $diaBanco;
}

// Armazena a coleção "linhasMes" carregada para ser percorrida e exibida/processada a seguir.
$linhasMes = [];
// Agrupa os totais calculados para exibição no resumo financeiro da página.
$totais = [
    'servicos' => 0,
    'comercializado' => 0.0,
    'adicionais' => 0.0,
    'recebido' => 0.0,
    'dinheiro' => 0.0,
    'pix' => 0.0,
    'cartao' => 0.0,
    'credito_utilizado' => 0.0,
    'debito_gerado' => 0.0,
    'debito_pago' => 0.0,
    'diferenca_caixa' => 0.0,
];

// Armazena em "diasComMovimento" o valor necessário para as próximas etapas deste fluxo.
$diasComMovimento = 0;
// Armazena em "diasFechadosComMovimento" o valor necessário para as próximas etapas deste fluxo.
$diasFechadosComMovimento = 0;

// Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
for ($dia = 1; $dia <= $diasNoMes; $dia++) {
    // Armazena a data "data" usada nas validações e consultas desta página.
    $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
    // Armazena em "item" o valor necessário para as próximas etapas deste fluxo.
    $item = $dadosPorData[$data] ?? null;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($item !== null) {
        // Armazena a coleção "quantidadeServicos" carregada para ser percorrida e exibida/processada a seguir.
        $quantidadeServicos = (int) ($item['quantidade_servicos_operacionais'] ?? 0);
        // Guarda uma condição booleana em "temMovimento" para controlar o fluxo ou a exibição da página.
        $temMovimento = $quantidadeServicos > 0 || (float) ($item['total_recebido'] ?? 0) > 0;
        // Calcula quanto dinheiro físico deveria existir no caixa conforme os lançamentos registrados.
        $dinheiroEsperado = round(
            (float) ($item['valor_abertura_caixa'] ?? 0)
            + (float) ($item['dinheiro'] ?? 0),
            2
        );
        // Guarda o valor de dinheiro que a equipe informou ter contado fisicamente no caixa.
        $dinheiroContado = $item['dinheiro_contado'] !== null
            ? (float) $item['dinheiro_contado']
            : null;
        // Armazena em "diferencaCaixa" o valor necessário para as próximas etapas deste fluxo.
        $diferencaCaixa = $dinheiroContado !== null
            ? round($dinheiroContado - $dinheiroEsperado, 2)
            : null;

        // Verifica a condição abaixo antes de executar este bloco.
        if ($temMovimento) {
            $diasComMovimento++;

            // Verifica a condição abaixo antes de executar este bloco.
            if (($item['status'] ?? '') === 'FECHADO') {
                $diasFechadosComMovimento++;
            }
        }

        $totais['servicos'] += $quantidadeServicos;
        $totais['comercializado'] += (float) ($item['total_comercializado'] ?? 0);
        $totais['adicionais'] += (float) ($item['total_adicionais'] ?? 0);
        $totais['recebido'] += (float) ($item['total_recebido'] ?? 0);
        $totais['dinheiro'] += (float) ($item['dinheiro'] ?? 0);
        $totais['pix'] += (float) ($item['pix'] ?? 0);
        $totais['cartao'] += (float) ($item['cartao'] ?? 0);
        $totais['credito_utilizado'] += (float) ($item['credito_utilizado'] ?? 0);
        $totais['debito_gerado'] += (float) ($item['debito_gerado'] ?? 0);
        $totais['debito_pago'] += (float) ($item['debito_pago'] ?? 0);

        // Verifica a condição abaixo antes de executar este bloco.
        if ($diferencaCaixa !== null) {
            $totais['diferenca_caixa'] += $diferencaCaixa;
        }

        $item['_dinheiro_esperado_sem_despesas'] = $dinheiroEsperado;
        $item['_diferenca_caixa_sem_despesas'] = $diferencaCaixa;
        $item['_tem_movimento'] = $temMovimento;
    }

    $linhasMes[] = [
        'data' => $data,
        'dados' => $item,
    ];
}

// Quantidade de pets únicos no mês, sem contar a operação financeira de pagar débito.
$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT l.pet_id)
     FROM lancamentos_diarios l
     JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
     JOIN servicos s ON s.id = l.servico_id
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     WHERE f.data_fechamento BETWEEN ? AND ?
       AND LOWER(cs.nome) <> 'financeiro'"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([$inicioMes, $fimMes]);
// Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
$petsUnicosMes = (int) $stmt->fetchColumn();

// Classificação por serviço individual do mês: Banho, Tosa, Tosa higiênica, Creche etc.
$stmt = $pdo->prepare(
    "SELECT
        s.id AS servico_id,
        s.nome AS servico,
        cs.nome AS categoria,
        COUNT(l.id) AS quantidade_lancamentos,
        COUNT(DISTINCT l.pet_id) AS quantidade_pets,
        COALESCE(SUM(vt.valor_total_lancamento), 0) AS total_servico
     FROM lancamentos_diarios l
     JOIN fechamentos_diarios f ON f.id = l.fechamento_diario_id
     JOIN servicos s ON s.id = l.servico_id
     JOIN categorias_servico cs ON cs.id = s.categoria_id
     JOIN vw_total_lancamento vt ON vt.lancamento_diario_id = l.id
     WHERE f.data_fechamento BETWEEN ? AND ?
       AND LOWER(cs.nome) <> 'financeiro'
     GROUP BY s.id, s.nome, cs.nome
     ORDER BY cs.nome, s.nome"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([$inicioMes, $fimMes]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$resumoServicosMes = $stmt->fetchAll();

$fechamentoMensal['status'] = $mensalFechado ? 'FECHADO' : ($fechamentoMensal['status'] ?? 'ABERTO');

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Fechamento mensal';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'fechamento-mensal';
// Define o(s) CSS desta página em assets/css/ (fechamento.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'fechamento';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page monthly-close-page">
   <div class="monthly-close-heading">
        <div>
            <span class="monthly-eyebrow">Financeiro</span>
            <h1>Fechamento mensal</h1>
            <p>Consolidação automática dos fechamentos diários.</p>
        </div>

        <div class="monthly-heading-actions">
            <span class="monthly-status general-status">
                <i class="fa-solid fa-circle"></i>
                <?= escapar(nomeStatusMensal($fechamentoMensal['status'] ?? 'ABERTO')) ?>
            </span>

       <?php if (!$mensalFechado): ?>
                <button class="monthly-primary-action" type="button" data-open-monthly-finish>
                    Fechar mês
                </button>

            <?php elseif ($administrador): ?>
                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                <form method="post" action="fechamento-mensal.php?mes=<?= escapar($competencia) ?>" onsubmit="return confirm('Reabrir este fechamento mensal?');">
                    <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                    <input type="hidden" name="acao" value="reabrir_mes">
                    <button class="monthly-secondary-action" type="submit">
                        Reabrir mês
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?> monthly-message" role="<?= $tipoMensagem === 'error' ? 'alert' : 'status' ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

<?php
// ===== Dados da barra de período (título do mês e tag contextual) =====
$mensCompetenciaAtual = date('Y-m');
$mensTituloMes = nomeMesFechamento($mes) . ' de ' . $ano;
$mensDiferencaMeses = (($ano - (int) date('Y')) * 12) + ($mes - (int) date('n'));
$mensTagMes = [-1 => 'Mês passado', 0 => 'Mês atual', 1 => 'Próximo mês'][$mensDiferencaMeses] ?? '';
$mensEhMesAtual = $competencia === $mensCompetenciaAtual;
?>

<div class="monthly-datebar" id="monthlyDatebar" data-atual="<?= escapar($mensCompetenciaAtual) ?>">
    <!-- Lado esquerdo: mês ativo em destaque + tag contextual -->
    <div class="monthly-datebar-info">
        <h2 class="monthly-datebar-title" id="monthlyTitulo"><?= escapar($mensTituloMes) ?></h2>
        <span class="monthly-datebar-tag<?= $mensTagMes === 'Mês atual' ? ' is-atual' : '' ?>"
              id="monthlyTag"
              <?= $mensTagMes === '' ? 'hidden' : '' ?>><?= escapar($mensTagMes) ?></span>
    </div>

    <!-- Lado direito: controles agrupados -->
    <div class="monthly-datebar-controls">
        <!-- "Mês atual" só aparece quando o mês selecionado não é o mês corrente -->
        <a class="monthly-datebar-current" id="monthlyBotaoAtual"
           href="fechamento-mensal.php?mes=<?= escapar($mensCompetenciaAtual) ?>"
           <?= $mensEhMesAtual ? 'hidden' : '' ?>>Mês atual</a>

        <div class="monthly-datebar-group">
            <a class="monthly-datebar-arrow" href="fechamento-mensal.php?mes=<?= escapar($competenciaAnterior) ?>" aria-label="Mês anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </a>

            <!-- Seletor de mês (o JS envia o formulário ao mudar) -->
            <form class="monthly-datebar-form" id="monthlyPeriodForm" method="get" action="fechamento-mensal.php">
                <label class="monthly-datebar-label" for="monthlyPeriod">Escolher mês</label>
                <input id="monthlyPeriod" name="mes" type="month" value="<?= escapar($competencia) ?>">
                <span class="monthly-datebar-caret" aria-hidden="true">▾</span>
            </form>

            <a class="monthly-datebar-arrow" href="fechamento-mensal.php?mes=<?= escapar($competenciaSeguinte) ?>" aria-label="Mês seguinte">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>
</div>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="monthly-sheet-card">
        <div class="monthly-sheet-head">
            <div>
                <span>Movimento diário</span>
                <h2><?= escapar(nomeMesFechamento($mes)) ?> / <?= $ano ?></h2>
            </div>
            <small><?= $diasFechadosComMovimento ?> de <?= $diasComMovimento ?> dia(s) com movimento fechado(s)</small>
        </div>

        <div class="monthly-sheet-scroll">
            <!-- Tabela usada para organizar os registros em colunas de leitura rápida. -->
            <table class="monthly-sheet-table">
                <!-- Cabeçalho da tabela com os nomes das informações exibidas em cada coluna. -->
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Status</th>
                        <th>Pets</th>
                        <th>Serviços</th>
                        <th>Adicionais</th>
                        <th>Comercializado</th>
                        <th>Recebido</th>
                        <th>Dinheiro</th>
                        <th>Pix</th>
                        <th>Cartão</th>
                        <th>Crédito usado</th>
                        <th>Débito gerado</th>
                        <th>Débito pago</th>
                        <th>Dif. caixa</th>
                        <th></th>
                    </tr>
                </thead>
                <!-- Corpo da tabela onde os registros retornados pelo banco são listados. -->
                <tbody>
                    <?php foreach ($linhasMes as $linha): ?>
                        <?php
                        // Armazena em "item" o valor necessário para as próximas etapas deste fluxo.
                        $item = $linha['dados'];
                        // Armazena a data "dataLinha" usada nas validações e consultas desta página.
                        $dataLinha = $linha['data'];
                        // Armazena em "numeroDia" o valor necessário para as próximas etapas deste fluxo.
                        $numeroDia = (int) substr($dataLinha, 8, 2);
                        // Armazena em "diaSemana" o valor necessário para as próximas etapas deste fluxo.
                        $diaSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', strtotime($dataLinha))];
                        ?>
                        <tr class="<?= $item === null ? 'is-empty' : '' ?>">
                            <td class="monthly-day-cell">
                                <strong><?= str_pad((string) $numeroDia, 2, '0', STR_PAD_LEFT) ?></strong>
                                <small><?= escapar($diaSemana) ?></small>
                            </td>

                            <td>
                                <?php if ($item === null): ?>
                                    <span class="monthly-day-status empty">Sem movimento</span>
                                <?php else: ?>
                                    <span class="monthly-day-status <?= escapar(classeStatusMensal($item['status'] ?? 'ABERTO')) ?>">
                                        <?= escapar(nomeStatusMensal($item['status'] ?? 'ABERTO')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td><?= $item !== null ? (int) ($item['quantidade_pets_operacionais'] ?? 0) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null ? (int) ($item['quantidade_servicos_operacionais'] ?? 0) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['total_adicionais'] ?? 0) > 0 ? formatarMoeda($item['total_adicionais']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['total_comercializado'] ?? 0) > 0 ? formatarMoeda($item['total_comercializado']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['total_recebido'] ?? 0) > 0 ? formatarMoeda($item['total_recebido']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['dinheiro'] ?? 0) > 0 ? formatarMoeda($item['dinheiro']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['pix'] ?? 0) > 0 ? formatarMoeda($item['pix']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['cartao'] ?? 0) > 0 ? formatarMoeda($item['cartao']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['credito_utilizado'] ?? 0) > 0 ? formatarMoeda($item['credito_utilizado']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['debito_gerado'] ?? 0) > 0 ? formatarMoeda($item['debito_gerado']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td><?= $item !== null && (float) ($item['debito_pago'] ?? 0) > 0 ? formatarMoeda($item['debito_pago']) : '<span class="monthly-dash">—</span>' ?></td>
                            <td>
                                <?php if ($item !== null && $item['_diferenca_caixa_sem_despesas'] !== null): ?>
                                    <?php $diferenca = (float) $item['_diferenca_caixa_sem_despesas']; ?>
                                    <span class="monthly-money-difference <?= abs($diferenca) < 0.01 ? 'ok' : ($diferenca > 0 ? 'positive' : 'negative') ?>">
                                        <?= formatarMoeda($diferenca) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="monthly-dash">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="monthly-open-day" href="fechamento-diario.php?data=<?= escapar($dataLinha) ?>" title="Abrir fechamento do dia">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

   <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="monthly-summary-section">
        <div class="monthly-section-title">
            <div>
                <span>Resumo do mês</span>
                <h2>Totais consolidados</h2>
            </div>
            <small>Calculado automaticamente a partir dos lançamentos diários.</small>
        </div>

        <!-- Bloco visual adaptado com o mesmo padrão visual de cartões (daily-summary-grid) -->
        <div class="monthly-summary-grid">
            <article class="daily-summary-card emphasis">
                <span>Total comercializado</span>
                <strong><?= formatarMoeda($totais['comercializado']) ?></strong>
                <small>Serviços + adicionais</small>
            </article>

            <article class="daily-summary-card">
                <span>Total recebido</span>
                <strong><?= formatarMoeda($totais['recebido']) ?></strong>
                <small>Inclui pagamentos de débitos antigos</small>
            </article>

            <article class="daily-summary-card">
                <span>Pets únicos</span>
                <strong><?= $petsUnicosMes ?></strong>
                <small><?= $totais['servicos'] ?> serviço(s) realizado(s)</small>
            </article>

            <article class="daily-summary-card">
                <span>Adicionais</span>
                <strong><?= formatarMoeda($totais['adicionais']) ?></strong>
                <small>Somados aos serviços</small>
            </article>

            <article class="daily-summary-card danger-soft">
                <span>Débito gerado</span>
                <strong><?= formatarMoeda($totais['debito_gerado']) ?></strong>
                <small>Valores que ficaram para pagar</small>
            </article>

            <article class="daily-summary-card green-soft">
                <span>Débito pago</span>
                <strong><?= formatarMoeda($totais['debito_pago']) ?></strong>
                <small>Pagamentos de dívidas antigas</small>
            </article>

            <article class="daily-summary-card green-soft">
                <span>Crédito utilizado</span>
                <strong><?= formatarMoeda($totais['credito_utilizado']) ?></strong>
                <small>Saldo de crédito usado no mês</small>
            </article>
        </div>
    </section>

    <div class="monthly-lower-grid">
        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
        <section class="monthly-detail-card monthly-services-card">
            <div class="monthly-section-title compact">
                <div>
                    <span>Classificação</span>
                    <h2>Serviços do mês</h2>
                </div>
            </div>

            <?php if (!$resumoServicosMes): ?>
                <div class="monthly-empty-detail">
                    <i class="fa-solid fa-scissors"></i>
                    <span>Nenhum serviço lançado neste mês.</span>
                </div>
            <?php else: ?>
                <div class="monthly-service-list">
                    <?php foreach ($resumoServicosMes as $servico): ?>
                        <article>
                            <div>
                                <span class="monthly-service-category"><?= escapar($servico['categoria']) ?></span>
                                <strong><?= escapar($servico['servico']) ?></strong>
                                <small><?= (int) $servico['quantidade_pets'] ?> pet(s) • <?= (int) $servico['quantidade_lancamentos'] ?> lançamento(s)</small>
                            </div>
                            <div class="monthly-service-values">
                                <strong><?= (int) $servico['quantidade_pets'] ?></strong>
                                <span><?= formatarMoeda($servico['total_servico']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
        <section class="monthly-detail-card monthly-payments-card">
            <div class="monthly-section-title compact">
                <div>
                    <span>Recebimentos</span>
                    <h2>Formas de pagamento</h2>
                </div>
            </div>

        <div class="monthly-payment-list">
                <article>
                    <span class="monthly-payment-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                    <div><small>Dinheiro</small><strong><?= formatarMoeda($totais['dinheiro']) ?></strong></div>
                </article>
                <article>
                    <span class="monthly-payment-icon"><i class="fa-brands fa-pix"></i></span>
                    <div><small>Pix</small><strong><?= formatarMoeda($totais['pix']) ?></strong></div>
                </article>
                <article>
                    <span class="monthly-payment-icon"><i class="fa-regular fa-credit-card"></i></span>
                    <div><small>Cartão</small><strong><?= formatarMoeda($totais['cartao']) ?></strong></div>
                </article>
            </div>
                <div class="monthly-payment-total">
                    <span>Total recebido:</span>
                    <strong><?= formatarMoeda($totais['recebido']) ?></strong>
                </div>
        </section>
    </div>
</div>

<!-- Bloco visual "monthly-finish-modal" que organiza esta parte da interface. -->
<div class="monthly-finish-modal" id="monthlyFinishModal" aria-hidden="true">
    <div class="monthly-finish-dialog" role="dialog" aria-modal="true" aria-labelledby="monthlyFinishTitle">
        <div class="monthly-finish-head">
            <div>
                <span>Finalização</span>
                <h2 id="monthlyFinishTitle">Fechar <?= escapar(nomeMesFechamento($mes)) ?> de <?= $ano ?></h2>
            </div>
            <button type="button" data-close-monthly-finish aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form method="post" action="fechamento-mensal.php?mes=<?= escapar($competencia) ?>">
            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="acao" value="finalizar_mes">

            <div class="monthly-finish-preview">
                <div><span>Comercializado</span><strong><?= formatarMoeda($totais['comercializado']) ?></strong></div>
                <div><span>Recebido</span><strong><?= formatarMoeda($totais['recebido']) ?></strong></div>
                <div><span>Dias com movimento</span><strong><?= $diasComMovimento ?></strong></div>
            </div>

            <div class="field span-12">
                <!-- Rótulo do campo "Observações", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="monthlyObservations">Observações</label>
                <!-- Área de texto "observacoes" para registrar observações livres sobre o registro. -->
                <textarea id="monthlyObservations" name="observacoes" placeholder="Observações sobre o fechamento do mês"><?= escapar((string) ($fechamentoMensal['observacoes'] ?? '')) ?></textarea>
            </div>

            <div class="monthly-finish-actions">
                <button class="monthly-secondary-action" type="button" data-close-monthly-finish>Cancelar</button>
                <button class="monthly-primary-action" type="submit">
                    Confirmar fechamento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Script da barra de período: texto do mês, tag e botão "Mês atual" -->
<script>
(() => {
    const periodoInput = document.querySelector('#monthlyPeriod');
    const periodoForm = document.querySelector('#monthlyPeriodForm');
    const barra = document.querySelector('#monthlyDatebar');
    const tituloMes = document.querySelector('#monthlyTitulo');
    const tagMes = document.querySelector('#monthlyTag');
    const botaoAtual = document.querySelector('#monthlyBotaoAtual');

    if (!periodoInput || !periodoForm || !barra) return;

    const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    const atualISO = barra.dataset.atual || '';

    // Lê "AAAA-MM" e valida o mês
    const lerMes = (valor) => {
        const partes = /^(\d{4})-(\d{2})$/.exec(valor || '');
        if (!partes) return null;
        const ano = Number(partes[1]);
        const mes = Number(partes[2]);
        return mes >= 1 && mes <= 12 ? { ano, mes } : null;
    };

    const tagDoMes = (valor) => {
        const m = lerMes(valor);
        const atual = lerMes(atualISO);
        if (!m || !atual) return '';
        const diferenca = ((m.ano - atual.ano) * 12) + (m.mes - atual.mes);
        return { '-1': 'Mês passado', '0': 'Mês atual', '1': 'Próximo mês' }[String(diferenca)] || '';
    };

    const atualizarBarraPeriodo = () => {
        const valor = periodoInput.value;
        const m = lerMes(valor);
        if (!m) return;

        tituloMes.textContent = `${MESES[m.mes - 1]} de ${m.ano}`;

        const tag = tagDoMes(valor);
        tagMes.textContent = tag;
        tagMes.hidden = !tag;
        tagMes.classList.toggle('is-atual', tag === 'Mês atual');

        // "Mês atual" só aparece quando o mês selecionado é diferente do mês corrente
        if (botaoAtual) botaoAtual.hidden = valor === atualISO;
    };

    // Clicar em qualquer área do seletor abre o calendário de meses na hora
    periodoForm.addEventListener('click', () => {
        try {
            periodoInput.showPicker();
        } catch (erro) {
            // Navegador sem showPicker: o input nativo cuida sozinho
        }
    });

    // Ao mudar o mês: atualiza o texto na hora e carrega o fechamento do mês
    periodoInput.addEventListener('change', () => {
        if (!lerMes(periodoInput.value)) return; // ignora mês incompleto
        atualizarBarraPeriodo();
        periodoForm.submit();
    });
})();
</script>

<!-- Script responsável pelas interações desta parte da página no navegador. -->
<script>
(() => {
    // Obtém a referência do elemento "modal" para ler ou alterar seu conteúdo/comportamento.
    const modal = document.querySelector('#monthlyFinishModal');
    // Obtém a referência do elemento "abrir" para ler ou alterar seu conteúdo/comportamento.
    const abrir = document.querySelector('[data-open-monthly-finish]');
    // Obtém a referência do elemento "fechar" para ler ou alterar seu conteúdo/comportamento.
    const fechar = document.querySelectorAll('[data-close-monthly-finish]');

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.
    if (!modal || !abrir) return;

    // Armazena em "abrirModal" um valor usado pela lógica executada no navegador.
    const abrirModal = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('monthly-modal-open');
    };

    // Armazena em "fecharModal" um valor usado pela lógica executada no navegador.
    const fecharModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('monthly-modal-open');
    };

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    abrir.addEventListener('click', abrirModal);
    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    fechar.forEach((botao) => botao.addEventListener('click', fecharModal));

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    modal.addEventListener('click', (event) => {
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (event.target === modal) fecharModal();
    });

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    document.addEventListener('keydown', (event) => {
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            fecharModal();
        }
    });
})();
</script>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
