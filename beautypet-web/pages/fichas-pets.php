<?php

// Carrega o arquivo de configuração do sistema,
// incluindo a conexão com o banco de dados.
require __DIR__ . '/../config/config.php';

// Recebe o termo informado no campo de busca.
// Caso nenhum termo seja informado, utiliza uma string vazia.
$busca = trim($_GET['busca'] ?? '');

// Consulta os dados dos pets, tutores, espécies, raças,
// informações financeiras e pacotes ativos.
$sql = '
    SELECT
        p.id,
        p.nome,
        p.foto_url,
        p.peso,
        p.porte,
        p.sexo,
        c.nome AS tutor,
        e.nome AS especie,
        r.nome AS raca,
        COALESCE(g.total_gasto, 0.00)       AS total_gasto,
        COALESCE(pkg.pacotes_ativos, 0)    AS pacotes_ativos,
        COALESCE(pkg.creditos_restantes, 0) AS creditos_restantes,
        pkg.resumo_pacotes
    FROM pets p
    JOIN clientes c
        ON c.id = p.cliente_id
    JOIN especies e
        ON e.id = p.especie_id
    LEFT JOIN racas r
        ON r.id = p.raca_id

    LEFT JOIN (
        SELECT
            pet_id,
            SUM(valor_total_lancamento) AS total_gasto
        FROM vw_total_lancamento
        GROUP BY pet_id
    ) g
        ON g.pet_id = p.id

    LEFT JOIN (
        SELECT
            pet_id,
            COUNT(*) AS pacotes_ativos,
            SUM(creditos_restantes) AS creditos_restantes,
            STRING_AGG(
                CONCAT(pacote, \': \', creditos_restantes, \' restante(s)\'),
                \', \'
                ORDER BY pacote
            ) AS resumo_pacotes
        FROM vw_saldo_pacotes
        WHERE status = \'ATIVO\'
          AND creditos_restantes > 0
        GROUP BY pet_id
    ) pkg
        ON pkg.pet_id = p.id

    WHERE p.ativo = TRUE
      AND c.ativo = TRUE
';

// Cria o array que armazenará os parâmetros
// utilizados na consulta SQL.
$parametros = [];

// Verifica se existe algum termo de busca.
if ($busca !== '') {

    // Adiciona à consulta a possibilidade de pesquisar
    // pelo nome do pet, tutor, espécie ou raça.
    $sql .= '
        AND (
            p.nome ILIKE :pet
            OR c.nome ILIKE :tutor
            OR e.nome ILIKE :especie
            OR r.nome ILIKE :raca
        )
    ';

    // Define os parâmetros utilizados na pesquisa.
    $parametros = [
        ':pet'     => '%' . $busca . '%',
        ':tutor'   => '%' . $busca . '%',
        ':especie' => '%' . $busca . '%',
        ':raca'    => '%' . $busca . '%',
    ];
}

// Organiza os pets em ordem alfabética pelo nome.
$sql .= ' ORDER BY p.nome LIMIT 60';

// Prepara a consulta SQL.
$stmt = $pdo->prepare($sql);

// Executa a consulta utilizando os parâmetros informados.
$stmt->execute($parametros);

// Recupera todos os pets encontrados.
$pets = $stmt->fetchAll();

// Consulta a quantidade total de pets ativos.
$totalPets = (int) $pdo
    ->query('SELECT COUNT(*) FROM pets WHERE ativo = TRUE')
    ->fetchColumn();

// Define o título da página.
$tituloPagina = 'Fichas dos pets';

// Define qual opção do menu está ativa.
$paginaAtiva = 'fichas-pets';

// Define o(s) CSS desta página em assets/css/ (fichas.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'fichas';
// Carrega o topo da página.
require __DIR__ . '/../includes/topo.php';
?>

<!-- Área principal da página de fichas dos pets. -->
<div class="page records-page">

    <!-- Cabeçalho da página. -->
    <div class="page-heading">

        <div>

            <!-- Título da página. -->
            <h1>Fichas dos pets</h1>

            <!-- Descrição da página. -->
            <small>Consulte os dados, pacotes e histórico de atendimentos.</small>

        </div>

        <!-- Exibe a quantidade total de pets cadastrados. -->
        <span class="counter">

            <strong><?= $totalPets ?></strong>

            <!-- Ajusta o texto para singular ou plural. -->
            <?= $totalPets === 1 ? 'pet cadastrado' : 'pets cadastrados' ?>

        </span>

    </div>

    <!-- Formulário de pesquisa dos pets. -->
    <form
        class="records-search records-live-search"
        method="get"
        data-records-live-search="pet"
        autocomplete="off">

        <!-- Ícone de pesquisa. -->
        <i class="fa-solid fa-magnifying-glass"></i>

        <!-- Campo para pesquisar por pet, tutor, espécie ou raça. -->
        <input
            type="search"
            name="busca"
            value="<?= escapar($busca) ?>"
            placeholder="Buscar por nome do pet, tutor, espécie ou raça"
            autocomplete="off"
            spellcheck="false"
            data-records-live-input>

        <!-- Exibe o botão para limpar a pesquisa. -->
        <?php if ($busca !== ''): ?>

            <a href="fichas-pets.php" class="clear-search" title="Limpar busca">

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

    <small class="records-search-note">Mostrando no máximo 60 resultados. Para cadastros maiores, digite o nome do pet ou tutor para localizar rapidamente.</small>

    <!-- Verifica se nenhum pet foi encontrado. -->
    <?php if (!$pets): ?>

        <!-- Mensagem exibida quando não existem resultados. -->
        <div class="empty-state">

            <i class="fa-solid fa-paw"></i>

            <h2>Nenhum pet encontrado</h2>

            <p>Cadastre um pet ou tente outro termo de busca.</p>

            <!-- Link para cadastrar um novo pet. -->
            <a class="primary-link" href="cadastro-pet.php">Cadastrar pet</a>

        </div>

    <?php else: ?>

        <!-- Grade onde os cards dos pets serão apresentados. -->
        <div class="pet-cards-grid">

            <!-- Percorre todos os pets encontrados. -->
            <?php foreach ($pets as $pet): ?>

                <!-- Card do pet, direcionando para sua ficha individual. -->
                <a class="pet-record-card" href="ficha-pet.php?id=<?= (int) $pet['id'] ?>">

                    <!-- Cabeçalho do card do pet. -->
                    <div class="pet-card-head">

                        <?php $fotoPet = urlFotoPet($pet['foto_url'] ?? null); ?>
<?php if ($fotoPet): ?>

    <!-- Exibe a foto do pet. -->
    <img
        src="<?= escapar($fotoPet) ?>"
        alt="Foto de <?= escapar($pet['nome']) ?>">

<?php else: ?>

                            <!-- Exibe um ícone quando não existe foto. -->
                            <div class="pet-photo-fallback">

                                <i class="fa-solid fa-paw"></i>

                            </div>

                        <?php endif; ?>

                        <!-- Informações básicas do pet. -->
                        <div>

                            <!-- Nome do pet. -->
                            <h2><?= escapar($pet['nome']) ?></h2>

                            <!-- Exibe a raça ou, caso não exista,
                                 exibe a espécie. -->
                            <span>
                                <?= escapar($pet['raca'] ?: $pet['especie']) ?>
                            </span>

                            <!-- Exibe o nome do tutor. -->
                            <small>
                                <i class="fa-regular fa-user"></i>
                                <?= escapar($pet['tutor']) ?>
                            </small>

                        </div>

                    </div>

                    <!-- Informações físicas do pet. -->
                    <div class="pet-card-data">

                        <!-- Informação sobre o porte. -->
                        <div>

                            <small>Porte</small>

                            <strong>
                                <?= escapar(nomePorte($pet['porte'])) ?>
                            </strong>

                        </div>

                        <!-- Informação sobre o peso. -->
                        <div>

                            <small>Peso</small>

                            <strong>
                                <?= $pet['peso'] !== null
                                    ? escapar(number_format((float) $pet['peso'], 2, ',', '.') . ' kg')
                                    : 'Não informado' ?>
                            </strong>

                        </div>

                    </div>

                    <!-- Informações sobre os pacotes do pet. -->
                    <div class="pet-card-status package">

                        <small>Pacote</small>

                        <!-- Verifica se existe algum pacote ativo. -->
                        <?php if ((int) $pet['pacotes_ativos'] > 0): ?>

                            <strong>
                                <?= (int) $pet['creditos_restantes'] ?> crédito(s) restante(s)
                            </strong>

                        <?php else: ?>

                            <!-- Mensagem exibida quando não existe pacote ativo. -->
                            <strong>Sem pacote ativo</strong>

                        <?php endif; ?>

                    </div>

                    <!-- Exibe o total comercializado para o pet. -->
                    <div class="pet-card-status total">

                        <small>Total comercializado</small>

                        <strong>
                            <?= escapar(formatarMoeda($pet['total_gasto'])) ?>
                        </strong>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php

// Carrega o rodapé da página.
require __DIR__ . '/../includes/rodape.php';

?>