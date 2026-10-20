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

        <form class="finance-filter" method="get" action="financeiro.php">
            <label for="mes">Mês</label>
            <input id="mes" name="mes" type="month" value="<?= escapar($mesSelecionado) ?>" max="<?= date('Y-m') ?>">
            <button class="save-button" type="submit">Ver</button>
        </form>
    </div>

    <?php if ($errosConsulta): ?>
        <div class="message error" role="alert">
            Não foi possível carregar parte do resumo (<?= escapar(implode(', ', $errosConsulta)) ?>). Os outros números estão corretos.
        </div>
    <?php endif; ?>

    <!-- Números principais do mês -->
    <div class="finance-summary">
        <div class="finance-card income">
            <small>Entradas do mês</small>
            <strong><?= escapar(formatarMoeda($totalEntradas)) ?></strong>
        </div>
        <div class="finance-card expense">
            <small>Despesas do caixa</small>
            <strong><?= escapar(formatarMoeda($totalDespesas)) ?></strong>
        </div>
        <div class="finance-card <?= $resultadoMes >= 0 ? 'income' : 'expense' ?>">
            <small>Resultado (entradas − despesas)</small>
            <strong><?= escapar(formatarMoeda($resultadoMes)) ?></strong>
        </div>
        <div class="finance-card">
            <small>A receber (débitos de tutores)</small>
            <strong><?= escapar(formatarMoeda($totalDebitos)) ?></strong>
        </div>
        <div class="finance-card">
            <small>Créditos de tutores</small>
            <strong><?= escapar(formatarMoeda($totalCreditos)) ?></strong>
        </div>
    </div>

    <div class="finance-grid">
        <!-- Entradas por forma de pagamento -->
        <section class="form-card finance-section">
            <h2>Entradas por forma de pagamento</h2>
            <?php if (!$entradasPorForma): ?>
                <p class="finance-empty">Nenhum pagamento registrado neste mês.</p>
            <?php else: ?>
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
            <?php endif; ?>
        </section>

        <!-- Tutores com débito -->
        <section class="form-card finance-section">
            <h2>Tutores com débito pendente</h2>
            <?php if (!$tutoresComDebito): ?>
                <p class="finance-empty">Nenhum tutor com débito. 🎉</p>
            <?php else: ?>
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
            <?php endif; ?>
        </section>
    </div>

    <!-- Despesas do mês -->
    <section class="form-card finance-section">
        <h2>Despesas do caixa em <?= escapar($rotuloMes) ?></h2>
        <?php if (!$despesas): ?>
            <p class="finance-empty">Nenhuma despesa registrada neste mês.</p>
        <?php else: ?>
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
        <?php endif; ?>
    </section>

    <p class="finance-note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        Os valores são lançados no <a href="fechamento-diario.php">Fechamento diário</a>.
        Para ver dia a dia, use o <a href="fechamento-mensal.php">Fechamento mensal</a>.
    </p>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
