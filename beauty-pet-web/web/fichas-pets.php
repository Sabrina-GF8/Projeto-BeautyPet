<?php
/**
 * ARQUIVO: fichas-pets.php
 * RESPONSABILIDADE: Listagem e pesquisa de fichas de pets.
 * Lista pets cadastrados, aplica filtros de pesquisa e fornece acesso rápido à ficha individual de cada animal.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Guarda o termo de pesquisa tratado para montar os filtros SQL usados na busca.
$busca = trim($_GET['busca'] ?? '');

// Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
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
            GROUP_CONCAT(
                CONCAT(pacote, \': \', creditos_restantes, \' restante(s)\')
                ORDER BY pacote
                SEPARATOR \', \'
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

// Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
$parametros = [];

// Verifica a condição abaixo antes de executar este bloco.
if ($busca !== '') {
    $sql .= '
        AND (
            p.nome LIKE :pet
            OR c.nome LIKE :tutor
            OR e.nome LIKE :especie
            OR r.nome LIKE :raca
        )
    ';

    // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
    $parametros = [
        ':pet'     => '%' . $busca . '%',
        ':tutor'   => '%' . $busca . '%',
        ':especie' => '%' . $busca . '%',
        ':raca'    => '%' . $busca . '%',
    ];
}

$sql .= ' ORDER BY p.nome LIMIT 60';

// Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare($sql);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute($parametros);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$pets = $stmt->fetchAll();

// Conta quantos pets ativos existem para exibir o total na interface.
$totalPets = (int) $pdo
    ->query('SELECT COUNT(*) FROM pets WHERE ativo = TRUE')
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn();

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Fichas dos pets';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'fichas-pets';

// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page records-page">
    <!-- Bloco visual "page-heading" que organiza esta parte da interface. -->
    <div class="page-heading">
        <div>
            <h1>Fichas dos pets</h1>
            <small>Consulte os dados, pacotes e histórico de atendimentos.</small>
        </div>

        <span class="counter">
            <strong><?= $totalPets ?></strong>
            <?= $totalPets === 1 ? 'pet cadastrado' : 'pets cadastrados' ?>
        </span>
    </div>

    <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
    <form class="records-search" method="get">
        <i class="fa-solid fa-magnifying-glass"></i>

        <input
            type="search"
            name="busca"
            value="<?= escapar($busca) ?>"
            placeholder="Buscar por nome do pet, tutor, espécie ou raça">

        <?php if ($busca !== ''): ?>
            <a href="fichas-pets.php" class="clear-search" title="Limpar busca">
                <i class="fa-solid fa-xmark"></i>
            </a>
        <?php endif; ?>
    </form>

    <small class="records-search-note">Mostrando no máximo 60 resultados. Para cadastros maiores, digite o nome do pet ou tutor para localizar rapidamente.</small>

    <?php if (!$pets): ?>
        <div class="empty-state">
            <i class="fa-solid fa-paw"></i>
            <h2>Nenhum pet encontrado</h2>
            <p>Cadastre um pet ou tente outro termo de busca.</p>
            <a class="primary-link" href="cadastro-pet.php">Cadastrar pet</a>
        </div>
    <?php else: ?>
        <div class="pet-cards-grid">
            <?php foreach ($pets as $pet): ?>
                <a class="pet-record-card" href="ficha-pet.php?id=<?= (int) $pet['id'] ?>">
                    <div class="pet-card-head">
                        <?php $fotoPet = urlFotoPet($pet['foto_url'] ?? null); ?>
                        <?php if ($fotoPet): ?>
                            <img src="<?= escapar($fotoPet) ?>" alt="Foto de <?= escapar($pet['nome']) ?>">
                        <?php else: ?>
                            <div class="pet-photo-fallback">
                                <i class="fa-solid fa-paw"></i>
                            </div>
                        <?php endif; ?>

                        <div>
                            <h2><?= escapar($pet['nome']) ?></h2>
                            <span>
                                <?= escapar($pet['raca'] ?: $pet['especie']) ?>
                            </span>
                            <small>
                                <i class="fa-regular fa-user"></i>
                                <?= escapar($pet['tutor']) ?>
                            </small>
                        </div>
                    </div>

                    <div class="pet-card-data">
                        <div>
                            <small>Porte</small>
                            <strong><?= escapar(nomePorte($pet['porte'])) ?></strong>
                        </div>

                        <div>
                            <small>Peso</small>
                            <strong>
                                <?= $pet['peso'] !== null
                                    ? escapar(number_format((float) $pet['peso'], 2, ',', '.') . ' kg')
                                    : 'Não informado' ?>
                            </strong>
                        </div>
                    </div>

                    <div class="pet-card-status package">
                        <small>Pacote</small>

                        <?php if ((int) $pet['pacotes_ativos'] > 0): ?>
                            <strong>
                                <?= (int) $pet['creditos_restantes'] ?> crédito(s) restante(s)
                            </strong>
                        <?php else: ?>
                            <strong>Sem pacote ativo</strong>
                        <?php endif; ?>
                    </div>

                    <div class="pet-card-status total">
                        <small>Total comercializado</small>
                        <strong><?= escapar(formatarMoeda($pet['total_gasto'])) ?></strong>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
