<?php

// Carrega as configurações do sistema e a conexão com o banco de dados.
require __DIR__ . '/../config/config.php';

// Recebe o termo de busca informado pelo usuário.
// Caso não exista, utiliza uma string vazia.
$busca = trim($_GET['busca'] ?? '');

// Consulta os tutores ativos, seus pets e informações financeiras.
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

// Cria o array que armazenará os parâmetros da consulta.
$parametros = [];

// Verifica se foi informado algum termo de pesquisa.
if ($busca !== '') {

    // Remove caracteres que não sejam números para realizar
    // a pesquisa por CPF.
    $cpfBusca = preg_replace('/\D+/', '', $busca);

    // Adiciona as condições de pesquisa por nome ou e-mail.
    $sql .= '
    AND (
        c.nome ILIKE :nome
        OR c.email ILIKE :email
';

    // Define os parâmetros utilizados na pesquisa.
    $parametros = [
        ':nome'  => '%' . $busca . '%',
        ':email' => '%' . $busca . '%',
    ];

    // Se o termo possuir números, também realiza a busca por CPF.
    if ($cpfBusca !== '') {

        $sql .= ' OR c.cpf LIKE :cpf';

        $parametros[':cpf'] = '%' . $cpfBusca . '%';
    }

    // Finaliza a condição de pesquisa.
    $sql .= ')';
}

// Organiza os tutores em ordem alfabética.
$sql .= ' ORDER BY c.nome';

// Prepara a consulta SQL.
$stmt = $pdo->prepare($sql);

// Executa a consulta utilizando os parâmetros informados.
$stmt->execute($parametros);

// Armazena todos os tutores encontrados.
$tutores = $stmt->fetchAll();

// Consulta a quantidade total de tutores ativos.
$totalTutores = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    ->fetchColumn();

// Define o título da página.
$tituloPagina = 'Fichas dos tutores';

// Define a página ativa no menu.
$paginaAtiva = 'fichas-tutores';

// Define o(s) CSS desta página em assets/css/ (fichas.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'fichas';
// Carrega o topo da página.
require __DIR__ . '/../includes/topo.php';

?>

<div class="page records-page">

    <!-- Cabeçalho da página de fichas dos tutores. -->
    <div class="page-heading">

        <div>

            <h1>Fichas dos tutores</h1>

            <small>Consulte os dados, pets vinculados e histórico financeiro.</small>

        </div>

        <!-- Exibe a quantidade de tutores cadastrados. -->
        <span class="counter">

            <strong><?= $totalTutores ?></strong>

            <?= $totalTutores === 1 ? 'tutor cadastrado' : 'tutores cadastrados' ?>

        </span>

    </div>

    <!-- Formulário utilizado para pesquisar tutores. -->
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

            <!-- Botão utilizado para limpar a pesquisa. -->
            <a href="fichas-tutores.php" class="clear-search" title="Limpar busca">

                <i class="fa-solid fa-xmark"></i>

            </a>

        <?php endif; ?>

        <!-- Sugestões exibidas enquanto o nome é digitado. -->
        <div
            class="records-live-results"
            data-records-live-results
            aria-live="polite"
            hidden></div>

    </form>

    <?php if (!$tutores): ?>

        <!-- Mensagem exibida quando nenhum tutor é encontrado. -->
        <div class="empty-state">

            <i class="fa-regular fa-address-card"></i>

            <h2>Nenhum tutor encontrado</h2>

            <p>Cadastre um tutor ou tente outro termo de busca.</p>

            <a class="primary-link" href="cadastro-tutor.php">Cadastrar tutor</a>

        </div>

    <?php else: ?>

        <!-- Lista de tutores encontrados. -->
        <div class="tutor-list">

            <?php foreach ($tutores as $tutor): ?>

                <!-- Cada tutor direciona para sua ficha individual. -->
                <a class="tutor-row" href="ficha-tutor.php?id=<?= (int) $tutor['id'] ?>">

                    <!-- Exibe as iniciais do nome do tutor. -->
                    <div class="record-avatar">
                        <?= escapar(iniciaisNome($tutor['nome'])) ?>
                    </div>

                    <!-- Informações principais do tutor. -->
                    <div class="record-main">

                        <strong><?= escapar($tutor['nome']) ?></strong>

                        <span><?= escapar(formatarCpf($tutor['cpf'])) ?></span>

                        <small><?= escapar($tutor['email'] ?: formatarTelefone($tutor['telefone_principal'])) ?></small>

                    </div>

                    <!-- Exibe os indicadores financeiros e a quantidade de pets. -->
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

                        <!-- Verifica se o tutor possui débito. -->
                        <?php if ((float) $tutor['saldo_debito'] > 0): ?>

                            <div class="metric-box red">

                                <small>Débito</small>

                                <strong><?= escapar(formatarMoeda($tutor['saldo_debito'])) ?></strong>

                            </div>

                        <!-- Caso não tenha débito, verifica se possui crédito. -->
                        <?php elseif ((float) $tutor['saldo_credito'] > 0): ?>

                            <div class="metric-box green">

                                <small>Crédito</small>

                                <strong><?= escapar(formatarMoeda($tutor['saldo_credito'])) ?></strong>

                            </div>

                        <!-- Caso não tenha débito nem crédito, informa que não há pendências. -->
                        <?php else: ?>

                            <div class="metric-box">

                                <small>Saldo</small>

                                <strong>Sem pendências</strong>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Ícone que indica acesso à ficha do tutor. -->
                    <i class="fa-solid fa-chevron-right record-arrow"></i>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php

// Carrega o rodapé da página.
require __DIR__ . '/../includes/rodape.php';

?>
