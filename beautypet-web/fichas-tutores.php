<?php
require __DIR__ . '/config.php';

$busca = trim($_GET['busca'] ?? '');

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

    WHERE c.ativo = TRUE
';

$parametros = [];

if ($busca !== '') {
    $cpfBusca = preg_replace('/\D+/', '', $busca);

    $sql .= '
        AND (
            c.nome LIKE :nome
            OR c.email LIKE :email
    ';

    $parametros = [
        ':nome'  => '%' . $busca . '%',
        ':email' => '%' . $busca . '%',
    ];

    if ($cpfBusca !== '') {
        $sql .= ' OR c.cpf LIKE :cpf';
        $parametros[':cpf'] = '%' . $cpfBusca . '%';
    }

    $sql .= ')';
}

$sql .= ' ORDER BY c.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$tutores = $stmt->fetchAll();

$totalTutores = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    ->fetchColumn();

$tituloPagina = 'Fichas dos tutores';
$paginaAtiva = 'fichas-tutores';

require __DIR__ . '/includes/topo.php';
?>

<div class="page records-page">
    <div class="page-heading">
        <div>
            <h1>Fichas dos tutores</h1>
            <small>Consulte os dados, pets vinculados e histórico financeiro.</small>
        </div>

        <span class="counter">
            <strong><?= $totalTutores ?></strong>
            <?= $totalTutores === 1 ? 'tutor cadastrado' : 'tutores cadastrados' ?>
        </span>
    </div>

    <form class="records-search" method="get">
        <i class="fa-solid fa-magnifying-glass"></i>

        <input
            type="search"
            name="busca"
            value="<?= escapar($busca) ?>"
            placeholder="Buscar por nome, CPF ou e-mail">

        <?php if ($busca !== ''): ?>
            <a href="fichas-tutores.php" class="clear-search" title="Limpar busca">
                <i class="fa-solid fa-xmark"></i>
            </a>
        <?php endif; ?>
    </form>

    <?php if (!$tutores): ?>
        <div class="empty-state">
            <i class="fa-regular fa-address-card"></i>
            <h2>Nenhum tutor encontrado</h2>
            <p>Cadastre um tutor ou tente outro termo de busca.</p>
            <a class="primary-link" href="cadastro-tutor.php">Cadastrar tutor</a>
        </div>
    <?php else: ?>
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

                    <i class="fa-solid fa-chevron-right record-arrow"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
