<?php
/**
 * ARQUIVO: pages/financeiro.php
 * [BP-ALTERAÇÃO #5] Página nova (o menu já tinha o link, mas o arquivo não existia e dava erro 404).
 *
 * RESPONSABILIDADE: resumo financeiro do mês, SOMENTE LEITURA (esta página não grava nada no banco).
 *   - Entradas do mês por forma de pagamento (tabela pagamentos, sem os estornados).
 *   - Despesas do caixa no mês (tabela despesas_caixa).
 *   - Resultado = entradas - despesas.
 *   - Tutores com débito pendente e total de créditos (visão vw_saldo_cliente, a mesma da ficha do tutor).
 *
 * Os lançamentos continuam sendo feitos no Fechamento diário; o detalhamento por dia fica no Fechamento mensal.
 *
 * [BP-PADRONIZAÇÃO] Visual alinhado com a Agenda e o Dashboard:
 *   - Barra de navegação de mês igual à da agenda (Hoje, seta anterior, seletor, seta seguinte, mês por extenso).
 *   - Cards de resumo no mesmo padrão dos KPIs do Dashboard (ícone, valor e rótulo).
 * Cada consulta tem seu próprio try/catch: se uma falhar, o restante da página continua aparecendo.
 */
require __DIR__ . '/../config/config.php';

// Mês escolhido no filtro (formato AAAA-MM). Padrão: mês atual.
$mesSelecionado = (string) ($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesSelecionado)) {
    $mesSelecionado = date('Y-m');
}
$inicioMes = $mesSelecionado . '-01';
$inicioProximoMes = date('Y-m-d', strtotime($inicioMes . ' +1 month'));

$nomesMeses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$rotuloMes = $nomesMeses[(int) substr($mesSelecionado, 5, 2)] . ' de ' . substr($mesSelecionado, 0, 4);

// Navegação entre meses (setas da barra, igual à agenda faz com os dias).
$mesAtual = date('Y-m');
$mesAnterior = date('Y-m', strtotime($inicioMes . ' -1 month'));
$mesSeguinte = date('Y-m', strtotime($inicioMes . ' +1 month'));
$podeAvancar = $mesSeguinte <= $mesAtual; // o filtro nunca vai além do mês atual
$rotuloMesTitulo = ucfirst($rotuloMes); // todos os meses começam com letra ASCII, então ucfirst basta (sem depender de mbstring)

$errosConsulta = [];
$periodo = [':inicio' => $inicioMes, ':fim' => $inicioProximoMes];

// 1. Entradas por forma de pagamento
$entradasPorForma = [];
try {
    $stmt = $pdo->prepare(
        'SELECT fp.nome AS forma, SUM(p.valor) AS total, COUNT(*) AS quantidade
         FROM pagamentos p
         JOIN formas_pagamento fp ON fp.id = p.forma_pagamento_id
         WHERE p.estornado = FALSE
           AND p.data_hora_pagamento >= :inicio
           AND p.data_hora_pagamento <  :fim
         GROUP BY fp.nome
         ORDER BY total DESC'
    );
    $stmt->execute($periodo);
    $entradasPorForma = $stmt->fetchAll();
} catch (Throwable $erro) {
    $errosConsulta[] = 'entradas';
}
$totalEntradas = array_sum(array_map(fn ($linha) => (float) $linha['total'], $entradasPorForma));

// 2. Despesas do caixa
$despesas = [];
try {
    $stmt = $pdo->prepare(
        'SELECT data_hora_despesa, descricao, categoria, valor
         FROM despesas_caixa
         WHERE data_hora_despesa >= :inicio
           AND data_hora_despesa <  :fim
         ORDER BY data_hora_despesa DESC'
    );
    $stmt->execute($periodo);
    $despesas = $stmt->fetchAll();
} catch (Throwable $erro) {
    $errosConsulta[] = 'despesas';
}
$totalDespesas = array_sum(array_map(fn ($linha) => (float) $linha['valor'], $despesas));
$resultadoMes = $totalEntradas - $totalDespesas;

// 3. Débitos e créditos dos tutores (saldo atual, não depende do mês)
$tutoresComDebito = [];
$totalDebitos = 0.0;
$totalCreditos = 0.0;
try {
    $totais = $pdo->query(
        'SELECT COALESCE(SUM(saldo_debito), 0) AS debitos, COALESCE(SUM(saldo_credito), 0) AS creditos
         FROM vw_saldo_cliente'
    )->fetch() ?: [];
    $totalDebitos = (float) ($totais['debitos'] ?? 0);
    $totalCreditos = (float) ($totais['creditos'] ?? 0);

    $tutoresComDebito = $pdo->query(
        'SELECT c.id, c.nome, c.telefone_principal, s.saldo_debito
         FROM vw_saldo_cliente s
         JOIN clientes c ON c.id = s.cliente_id
         WHERE s.saldo_debito > 0
           AND c.ativo = TRUE
         ORDER BY s.saldo_debito DESC
         LIMIT 15'
    )->fetchAll();
} catch (Throwable $erro) {
    $errosConsulta[] = 'saldos';
}

$tituloPagina = 'Financeiro';
$paginaAtiva = 'financeiro';
$cssPagina = ['cadastro', 'financeiro'];
require __DIR__ . '/../includes/topo.php';
?>

<div class="page finance-page">
    <div class="page-heading">
        <div>
            <h1>Financeiro</h1>
            <small>Resumo de <?= escapar($rotuloMes) ?> · somente consulta</small>
        </div>
    </div>

    <?php if ($errosConsulta): ?>
        <div class="message error" role="alert">
            Não foi possível carregar parte do resumo (<?= escapar(implode(', ', $errosConsulta)) ?>). Os outros números estão corretos.
        </div>
    <?php endif; ?>

    <!-- Barra de navegação de mês: mesmo padrão da barra de data da Agenda. -->
    <div class="google-calendar-toolbar">
        <!-- 1. Atalho para o mês atual -->
        <a class="calendar-today-button" href="financeiro.php?mes=<?= escapar($mesAtual) ?>" aria-label="Voltar para o mês atual">Hoje</a>

        <!-- 2. Seta para o mês anterior -->
        <div class="calendar-nav-buttons">
            <a href="financeiro.php?mes=<?= escapar($mesAnterior) ?>" aria-label="Mês anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
        </div>

        <!-- 3. Seletor de mês (envia sozinho ao alterar) -->
        <form class="calendar-date-form" method="get" action="financeiro.php">
            <label for="mes">Mês</label>
            <input id="mes" name="mes" type="month" value="<?= escapar($mesSelecionado) ?>" max="<?= escapar($mesAtual) ?>" onchange="this.form.submit()">
        </form>

        <!-- 4. Seta para o mês seguinte (desativada no mês atual, pois não há dados futuros) -->
        <div class="calendar-nav-buttons">
            <?php if ($podeAvancar): ?>
                <a href="financeiro.php?mes=<?= escapar($mesSeguinte) ?>" aria-label="Mês seguinte">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <a class="is-disabled" aria-disabled="true" aria-label="Mês seguinte (indisponível)">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>

        <!-- 5. Mês por extenso no canto direito -->
        <strong class="calendar-date-title"><?= escapar($rotuloMesTitulo) ?></strong>
    </div>

    <!-- Números principais do mês (mesmo padrão dos KPIs do Dashboard) -->
    <div class="kpi-grid">
        <article class="kpi-card kpi-card-verde">
            <span class="kpi-icone kpi-icone-verde"><i class="fa-solid fa-arrow-trend-up"></i></span>
            <strong class="kpi-valor"><?= escapar(formatarMoeda($totalEntradas)) ?></strong>
            <small class="kpi-rotulo">Entradas do mês</small>
        </article>
        <article class="kpi-card kpi-card-alerta">
            <span class="kpi-icone kpi-icone-vermelho"><i class="fa-solid fa-receipt"></i></span>
            <strong class="kpi-valor"><?= escapar(formatarMoeda($totalDespesas)) ?></strong>
            <small class="kpi-rotulo">Despesas do caixa</small>
        </article>
        <article class="kpi-card <?= $resultadoMes >= 0 ? 'kpi-card-verde' : 'kpi-card-alerta' ?>">
            <span class="kpi-icone <?= $resultadoMes >= 0 ? 'kpi-icone-verde' : 'kpi-icone-vermelho' ?>"><i class="fa-solid fa-scale-balanced"></i></span>
            <strong class="kpi-valor"><?= escapar(formatarMoeda($resultadoMes)) ?></strong>
            <small class="kpi-rotulo">Resultado (entradas − despesas)</small>
        </article>
        <article class="kpi-card">
            <span class="kpi-icone kpi-icone-amarelo"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            <strong class="kpi-valor"><?= escapar(formatarMoeda($totalDebitos)) ?></strong>
            <small class="kpi-rotulo">A receber (débitos de tutores)</small>
        </article>
        <article class="kpi-card">
            <span class="kpi-icone kpi-icone-azul"><i class="fa-solid fa-wallet"></i></span>
            <strong class="kpi-valor"><?= escapar(formatarMoeda($totalCreditos)) ?></strong>
            <small class="kpi-rotulo">Créditos de tutores</small>
        </article>
    </div>

    <div class="finance-grid">
        <!-- Entradas por forma de pagamento -->
        <section class="form-card finance-section">
            <h2>Entradas por forma de pagamento</h2>
            <?php if (!$entradasPorForma): ?>
                <p class="finance-empty">Nenhum pagamento registrado neste mês.</p>
            <?php else: ?>
                <div class="finance-table-wrap">
                    <table class="finance-table">
                        <thead><tr><th>Forma</th><th>Pagamentos</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($entradasPorForma as $linha): ?>
                                <tr>
                                    <td><?= escapar($linha['forma']) ?></td>
                                    <td><?= (int) $linha['quantidade'] ?></td>
                                    <td><?= escapar(formatarMoeda($linha['total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Tutores com débito -->
        <section class="form-card finance-section">
            <h2>Tutores com débito pendente</h2>
            <?php if (!$tutoresComDebito): ?>
                <p class="finance-empty">Nenhum tutor com débito. 🎉</p>
            <?php else: ?>
                <div class="finance-table-wrap">
                    <table class="finance-table">
                        <thead><tr><th>Tutor</th><th>Telefone</th><th>Débito</th></tr></thead>
                        <tbody>
                            <?php foreach ($tutoresComDebito as $tutor): ?>
                                <tr>
                                    <td><a href="ficha-tutor.php?id=<?= (int) $tutor['id'] ?>"><?= escapar($tutor['nome']) ?></a></td>
                                    <td><?= escapar(formatarTelefone($tutor['telefone_principal'] ?? null)) ?></td>
                                    <td><?= escapar(formatarMoeda($tutor['saldo_debito'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Despesas do mês -->
    <section class="form-card finance-section">
        <h2>Despesas do caixa em <?= escapar($rotuloMes) ?></h2>
        <?php if (!$despesas): ?>
            <p class="finance-empty">Nenhuma despesa registrada neste mês.</p>
        <?php else: ?>
            <div class="finance-table-wrap">
                <table class="finance-table">
                    <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($despesas as $despesa): ?>
                            <tr>
                                <td><?= escapar(date('d/m/Y', strtotime((string) $despesa['data_hora_despesa']))) ?></td>
                                <td><?= escapar($despesa['descricao']) ?></td>
                                <td><?= escapar($despesa['categoria'] ?: '—') ?></td>
                                <td><?= escapar(formatarMoeda($despesa['valor'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p class="finance-note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        Os valores são lançados no <a href="fechamento-diario.php">Fechamento diário</a>.
        Para ver dia a dia, use o <a href="fechamento-mensal.php">Fechamento mensal</a>.
    </p>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>