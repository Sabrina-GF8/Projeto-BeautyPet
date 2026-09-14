<?php
/**
 * ARQUIVO: fichas-tutores.php
 * RESPONSABILIDADE: Listagem e pesquisa de fichas de tutores.
 * Lista tutores cadastrados, aplica filtros de pesquisa e fornece acesso rápido à ficha individual de cada cliente.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Guarda o termo de pesquisa tratado para montar os filtros SQL usados na busca.
$busca = trim($_GET['busca'] ?? '');

// Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
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

// Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
$parametros = [];

// Verifica a condição abaixo antes de executar este bloco.
if ($busca !== '') {
    // Armazena em "cpfBusca" o valor necessário para as próximas etapas deste fluxo.
    $cpfBusca = preg_replace('/\D+/', '', $busca);

    $sql .= '
        AND (
            c.nome LIKE :nome
            OR c.email LIKE :email
    ';

    // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
    $parametros = [
        ':nome'  => '%' . $busca . '%',
        ':email' => '%' . $busca . '%',
    ];

    // Verifica a condição abaixo antes de executar este bloco.
    if ($cpfBusca !== '') {
        $sql .= ' OR c.cpf LIKE :cpf';
        $parametros[':cpf'] = '%' . $cpfBusca . '%';
    }

    $sql .= ')';
}

$sql .= ' ORDER BY c.nome LIMIT 60';

// Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare($sql);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute($parametros);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$tutores = $stmt->fetchAll();

// Conta quantos tutores ativos existem para exibir o total na interface.
$totalTutores = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn();

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Fichas dos tutores';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'fichas-tutores';

// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page records-page">
    <!-- Bloco visual "page-heading" que organiza esta parte da interface. -->
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

    <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
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

    <small class="records-search-note">Mostrando no máximo 60 resultados. Para cadastros maiores, pesquise por nome, CPF ou e-mail.</small>

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

<?php require __DIR__ . '/../includes/rodape.php'; ?>
