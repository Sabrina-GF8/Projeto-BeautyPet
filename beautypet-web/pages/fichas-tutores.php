<?php

// Carrega as configurações do sistema e a conexão com o banco de dados.
require __DIR__ . '/../config/config.php';

// Recebe o termo de busca informado pelo usuário.
$busca = trim($_GET['busca'] ?? '');

// Define quantos tutores aparecem por página
$itensPorPagina = 12;

// Descobre a página atual pela URL (ex: ?pagina=2), padrão é 1
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Consulta a quantidade total geral de tutores ativos no sistema (para o cabeçalho)
$totalTutoresGeral = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    ->fetchColumn();

// Configura os parâmetros e condições de busca dinâmica
$parametros = [];
$condicoes = ['c.ativo = TRUE'];

if ($busca !== '') {
    $cpfBusca = preg_replace('/\D+/', '', $busca);
    
    $condicoesBusca = ['c.nome ILIKE :nome', 'c.email ILIKE :email'];
    
    $parametros[':nome'] = '%' . $busca . '%';
    $parametros[':email'] = '%' . $busca . '%';

    if ($cpfBusca !== '') {
        $condicoesBusca[] = 'c.cpf LIKE :cpf';
        $parametros[':cpf'] = '%' . $cpfBusca . '%';
    }

    $condicoes[] = '(' . implode(' OR ', $condicoesBusca) . ')';
}

$whereSql = 'WHERE ' . implode(' AND ', $condicoes);

// Consulta para contar o TOTAL de registros encontrados com base na busca
$sqlCount = 'SELECT COUNT(DISTINCT c.id) FROM clientes c ' . $whereSql;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($parametros);
$totalRegistros = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, ceil($totalRegistros / $itensPorPagina));

if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
    $offset = ($paginaAtual - 1) * $itensPorPagina;
}

// Consulta principal com LIMIT e OFFSET para trazer apenas os tutores da página atual
$sql = '
    SELECT
        c.id,
        c.nome,
        c.cpf,
        c.email,
        c.telefone_principal,
        COALESCE(p.total_pets, 0)        AS total_pets,
        COALESCE(g.total_gasto, 0.00)   AS total_gasto,
        COALESCE(s.saldo_credito, 0.00) AS saldo_credito,
        COALESCE(s.saldo_debito, 0.00)  AS saldo_debito
    FROM clientes c
    LEFT JOIN (
        SELECT
            cliente_id,
            COUNT(*) AS total_pets
        FROM pets
        WHERE ativo = TRUE
        GROUP BY cliente_id
    ) p
        ON p.cliente_id = c.id
    LEFT JOIN (
        SELECT
            cliente_id,
            SUM(total_gasto) AS total_gasto
        FROM vw_gastos_cliente_mes
        GROUP BY cliente_id
    ) g
        ON g.cliente_id = c.id
    LEFT JOIN vw_saldo_cliente s
        ON s.cliente_id = c.id
    ' . $whereSql . '
    ORDER BY c.nome
    LIMIT ' . $itensPorPagina . ' OFFSET ' . $offset;

// Executa a consulta paginada dos tutores
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$tutores = $stmt->fetchAll();

$tituloPagina = 'Fichas dos tutores';
$paginaAtiva = 'fichas-tutores';
$cssPagina = 'fichas';
require __DIR__ . '/../includes/topo.php';

?>

<div class="page records-page">

    <?php if (isset($_GET['nao_encontrado'])): ?>
        <div class="message error" role="alert">O tutor procurado não foi encontrado ou foi removido. Pesquise novamente na lista abaixo.</div>
    <?php endif; ?>

    <!-- Cabeçalho da página de fichas dos tutores -->
    <div class="page-heading">
        <div>
            <h1>Fichas dos tutores</h1>
            <small>Consulte os dados, pets vinculados e histórico financeiro.</small>
        </div>
        <span class="counter">
            <strong><?= $totalTutoresGeral ?></strong>
            <?= $totalTutoresGeral === 1 ? 'tutor cadastrado' : 'tutores cadastrados' ?>
        </span>
    </div>

    <!-- Formulário de pesquisa de tutores -->
    <form
        class="records-search records-live-search"
        method="get"
        data-records-live-search="tutor"
        autocomplete="off">

        <i class="fa-solid fa-magnifying-glass"></i>

        <input
            type="search"
            name="busca"
            value="<?= escapar($busca) ?>"
            placeholder="Buscar por nome, CPF ou e-mail"
            autocomplete="off"
            spellcheck="false"
            data-records-live-input>

        <?php if ($busca !== ''): ?>
            <a href="fichas-tutores.php" class="clear-search" title="Limpar busca">
                <i class="fa-solid fa-xmark"></i>
            </a>
        <?php endif; ?>

        <div
            class="records-live-results"
            data-records-live-results
            aria-live="polite"
            hidden></div>

    </form>

    <?php if (!$tutores): ?>
        <div class="empty-state">
            <i class="fa-regular fa-address-card"></i>
            <h2>Nenhum tutor encontrado</h2>
            <p>Cadastre um tutor ou tente outro termo de busca.</p>
            <a class="primary-link" href="cadastro-tutor.php">Cadastrar tutor</a>
        </div>
    <?php else: ?>
        
        <!-- Lista de tutores da página atual -->
        <div class="tutor-list">
            <?php foreach ($tutores as $tutor): ?>
                <a class="tutor-row" href="ficha-tutor.php?id=<?= (int) $tutor['id'] ?>">

                    <div class="record-avatar">
                        <?= escapar(iniciaisNome($tutor['nome'])) ?>
                    </div>

                    <div class="record-main">
                        <strong><?= escapar($tutor['nome']) ?></strong>
                        <span><?= escapar(formatarCpf($tutor['cpf'])) ?></span>
                        <small><?= escapar($tutor['email'] ?: formatarTelefone($tutor['telefone_principal'])) ?></small>
                    </div>

                    <div class="record-metrics">
                        <div class="metric-box">
                            <small>Total comercializado</small>
                            <strong><?= escapar(formatarMoeda($tutor['total_gasto'])) ?></strong>
                        </div>

                        <div class="metric-box yellow">
                            <small>Pets</small>
                            <strong>
                                <?= (int) $tutor['total_pets'] ?>
                                <?= (int) $tutor['total_pets'] === 1 ? 'pet' : 'pets' ?>
                            </strong>
                        </div>

                        <?php if ((float) $tutor['saldo_debito'] > 0): ?>
                            <div class="metric-box red">
                                <small>Débito</small>
                                <strong><?= escapar(formatarMoeda($tutor['saldo_debito'])) ?></strong>
                            </div>
                        <?php elseif ((float) $tutor['saldo_credito'] > 0): ?>
                            <div class="metric-box green">
                                <small>Crédito</small>
                                <strong><?= escapar(formatarMoeda($tutor['saldo_credito'])) ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="metric-box">
                                <small>Saldo</small>
                                <strong>Sem pendências</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Renderiza a barra de paginação numérica -->
        <?php if ($totalPaginas > 1): ?>
            <div class="pagination-bar">
                <?php 
                    $queryBusca = $busca !== '' ? '&busca=' . urlencode($busca) : '';
                ?>

                <?php if ($paginaAtual > 1): ?>
                    <a href="fichas-tutores.php?pagina=<?= $paginaAtual - 1 ?><?= $queryBusca ?>" class="pagination-button">
                        <i class="fa-solid fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="pagination-button disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <div class="pagination-numbers">
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPaginas || ($i >= $paginaAtual - 2 && $i <= $paginaAtual + 2)): ?>
                            <a href="fichas-tutores.php?pagina=<?= $i ?><?= $queryBusca ?>" class="pagination-num <?= $i === $paginaAtual ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i == $paginaAtual - 3 || $i == $paginaAtual + 3): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="fichas-tutores.php?pagina=<?= $paginaAtual + 1 ?><?= $queryBusca ?>" class="pagination-button">
                        Seguinte <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagination-button disabled">Seguinte <i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php
require __DIR__ . '/../includes/rodape.php';
?>