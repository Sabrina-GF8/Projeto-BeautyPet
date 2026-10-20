<?php

// Carrega o arquivo de configuração do sistema.
require __DIR__ . '/../config/config.php';

// Obtém o ID do tutor informado pela URL.
// O valor é validado para garantir que seja um número inteiro.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Caso o ID não seja válido, retorna para a página de fichas dos tutores.
if (!$id) {
    header('Location: fichas-tutores.php');
    exit;
}

// Consulta os dados do tutor, a quantidade de pets,
// o total gasto e os saldos financeiro de crédito e débito.
$sqlTutor = '
    SELECT
        c.*,
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

    WHERE c.id = :id
      AND c.ativo = TRUE
';

// Prepara a consulta do tutor.
$stmt = $pdo->prepare($sqlTutor);

// Executa a consulta utilizando o ID do tutor.
$stmt->execute([':id' => $id]);

// Recupera os dados do tutor encontrado.
$tutor = $stmt->fetch();

// Caso o tutor não seja encontrado, retorna um erro 404.
if (!$tutor) {
    http_response_code(404);
    exit('Tutor não encontrado.');
}

// Consulta os pets vinculados ao tutor,
// incluindo informações de espécie, raça e pacotes.
$sqlPets = <<<'SQL'
    SELECT
        p.id,
        p.nome,
        p.foto_url,
        p.porte,
        p.peso,
        e.nome AS especie,
        r.nome AS raca,
        COALESCE(pkg.pacotes_ativos, 0)      AS pacotes_ativos,
        COALESCE(pkg.creditos_restantes, 0) AS creditos_restantes,
        pkg.resumo_pacotes
    FROM pets p
    JOIN especies e
        ON e.id = p.especie_id
    LEFT JOIN racas r
        ON r.id = p.raca_id
    LEFT JOIN (
        SELECT
            pet_id,
            COUNT(*) AS pacotes_ativos,
            SUM(creditos_restantes) AS creditos_restantes,
            STRING_AGG(
                CONCAT(pacote, ': ', creditos_restantes, ' restante(s)'),
                ', ' ORDER BY pacote
            ) AS resumo_pacotes
        FROM vw_saldo_pacotes
        WHERE status = 'ATIVO'
          AND creditos_restantes > 0
        GROUP BY pet_id
    ) pkg
        ON pkg.pet_id = p.id
    WHERE p.cliente_id = :cliente_id
      AND p.ativo = TRUE
    ORDER BY p.nome
SQL;

// Prepara a consulta dos pets.
$stmt = $pdo->prepare($sqlPets);

// Executa a consulta utilizando o ID do tutor.
$stmt->execute([':cliente_id' => $id]);

// Recupera todos os pets vinculados ao tutor.
$pets = $stmt->fetchAll();

// Consulta o histórico financeiro do tutor,
// incluindo pagamentos e movimentações de crédito e débito.
$sqlHistorico = <<<'SQL'
    SELECT
        h.*,
        COALESCE(
            NULLIF(CONCAT_WS(' + ', pg.formas, mov.movimentos), ''),
            'Não informado'
        ) AS forma_pagamento
    FROM vw_historico_cliente h

    LEFT JOIN (
        SELECT
            p.lancamento_diario_id,
            STRING_AGG(DISTINCT fp.nome, ' + ' ORDER BY fp.nome) AS formas
        FROM pagamentos p
        JOIN formas_pagamento fp
            ON fp.id = p.forma_pagamento_id
        WHERE p.estornado = FALSE
          AND p.lancamento_diario_id IS NOT NULL
        GROUP BY p.lancamento_diario_id
    ) pg
        ON pg.lancamento_diario_id = h.lancamento_diario_id

    LEFT JOIN (
        SELECT
            lancamento_diario_id,
            STRING_AGG(
                DISTINCT CASE
                    WHEN tipo_saldo = 'CREDITO' AND operacao = 'SAIDA'
                        THEN 'Crédito utilizado'
                    WHEN tipo_saldo = 'CREDITO' AND operacao = 'ENTRADA'
                        THEN 'Crédito gerado'
                    WHEN tipo_saldo = 'DEBITO' AND operacao = 'ENTRADA'
                        THEN 'Débito pendente'
                    WHEN tipo_saldo = 'DEBITO' AND operacao = 'SAIDA'
                        THEN 'Débito pago'
                END,
                ' + '
            ) AS movimentos
        FROM movimentacoes_cliente
        WHERE lancamento_diario_id IS NOT NULL
        GROUP BY lancamento_diario_id
    ) mov
        ON mov.lancamento_diario_id = h.lancamento_diario_id

    WHERE h.cliente_id = :cliente_id
    ORDER BY h.data_fechamento DESC, h.lancamento_diario_id DESC
    LIMIT 100
SQL;

// Prepara a consulta do histórico financeiro.
$stmt = $pdo->prepare($sqlHistorico);

// Executa a consulta utilizando o ID do tutor.
$stmt->execute([':cliente_id' => $id]);

// Recupera os registros do histórico financeiro.
$historico = $stmt->fetchAll();

// Define o título da página utilizando o nome do tutor.
$tituloPagina = 'Ficha de ' . $tutor['nome'];

// Define a página ativa no menu.
$paginaAtiva = 'fichas-tutores';

// Define o(s) CSS desta página em assets/css/ (ficha.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'ficha';
// Carrega o topo da página.
require __DIR__ . '/../includes/topo.php';
?>

<!-- Área principal da ficha do tutor. -->
<div class="page records-page">

    <!-- Link para retornar à lista de tutores. -->
    <a class="back-link" href="fichas-tutores.php">
        <i class="fa-solid fa-arrow-left"></i>
        Voltar para fichas dos tutores
    </a>

    <?php if (isset($_GET['editado'])): ?>
        <div class="message success detail-edit-message">Dados atualizados com sucesso!</div>
    <?php endif; ?>

    <div class="detail-page-actions">
        <a class="detail-edit-button" href="cadastro-tutor.php?editar=<?= (int) $id ?>">
            <i class="fa-solid fa-pen"></i>
            Editar tutor
        </a>
    </div>

    <!-- Organiza as informações principais do tutor. -->
    <div class="detail-layout">

        <!-- Card com os dados pessoais do tutor. -->
        <section class="detail-card tutor-profile-card">

            <div class="detail-profile-head">

                <!-- Exibe as iniciais do nome do tutor. -->
                <div class="record-avatar large">
                    <?= escapar(iniciaisNome($tutor['nome'])) ?>
                </div>

                <div>

                    <!-- Nome do tutor. -->
                    <h1><?= escapar($tutor['nome']) ?></h1>

                    <!-- Data em que o tutor foi cadastrado. -->
                    <p>
                        Tutor cadastrado em
                        <?= escapar(formatarData($tutor['criado_em'])) ?>
                    </p>

                </div>

            </div>

            <!-- Exibe as informações de contato e endereço. -->
            <div class="contact-grid">

                <!-- E-mail do tutor. -->
                <div>
                    <small>E-mail</small>
                    <strong><?= escapar($tutor['email'] ?: 'Não informado') ?></strong>
                </div>

                <!-- CPF do tutor. -->
                <div>
                    <small>CPF</small>
                    <strong><?= escapar(formatarCpf($tutor['cpf'])) ?></strong>
                </div>

                <!-- Telefone principal do tutor. -->
                <div>
                    <small>Telefone</small>
                    <strong><?= escapar(formatarTelefone($tutor['telefone_principal'])) ?></strong>
                </div>

                <!-- Telefone reserva do tutor. -->
                <div>
                    <small>Telefone reserva</small>
                    <strong><?= escapar(formatarTelefone($tutor['telefone_reserva'])) ?></strong>
                </div>

                <!-- Endereço completo do tutor. -->
                <div class="wide">
                    <small>Endereço</small>
                    <strong><?= escapar(enderecoCompleto($tutor)) ?></strong>
                </div>

                <!-- Exibe as observações somente quando existem. -->
                <?php if (!empty($tutor['observacoes'])): ?>

                    <div class="wide">
                        <small>Observações</small>
                        <strong><?= nl2br(escapar($tutor['observacoes'])) ?></strong>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- Coluna com o resumo financeiro e quantidade de pets. -->
        <aside class="summary-column">

            <!-- Total comercializado pelo tutor. -->
            <div class="summary-card">
                <small>Total comercializado</small>
                <strong><?= escapar(formatarMoeda($tutor['total_gasto'])) ?></strong>
            </div>

            <!-- Crédito disponível do tutor. -->
            <div class="summary-card credit">
                <small>Crédito disponível</small>
                <strong><?= escapar(formatarMoeda($tutor['saldo_credito'])) ?></strong>
            </div>

            <!-- Débito pendente do tutor. -->
            <div class="summary-card debt">
                <small>Débito pendente</small>

                <strong>
                    <?= (float) $tutor['saldo_debito'] > 0
                        ? escapar(formatarMoeda($tutor['saldo_debito']))
                        : 'Nenhum' ?>
                </strong>

            </div>

            <!-- Quantidade de pets cadastrados para o tutor. -->
            <div class="summary-card">
                <small>Pets cadastrados</small>
                <strong><?= (int) $tutor['total_pets'] ?></strong>
            </div>

        </aside>

    </div>

    <!-- Seção com os pets vinculados ao tutor. -->
    <section class="detail-card section-card">

        <div class="section-title">

            <h2>Pets vinculados</h2>

            <!-- [BP-ALTERAÇÃO #10] Antes era um texto-link solto e o tutor precisava ser buscado de novo.
                 Agora usa o mesmo botão do "Editar tutor" (.detail-edit-button) e já envia o tutor (?tutor=ID). -->
            <a class="detail-edit-button" href="cadastro-pet.php?tutor=<?= (int) $id ?>">
                <i class="fa-solid fa-paw"></i>
                Cadastrar novo pet
            </a>

        </div>

        <!-- Verifica se o tutor possui pets cadastrados. -->
        <?php if (!$pets): ?>

            <div class="inline-empty">
                Nenhum pet está vinculado a este tutor.
            </div>

        <?php else: ?>

            <!-- Grade com os pets vinculados. -->
            <div class="linked-pets-grid">

                <!-- Percorre todos os pets do tutor. -->
                <?php foreach ($pets as $pet): ?>

                    <!-- Link para acessar a ficha individual do pet. -->
                    <a class="linked-pet-card"
                       href="ficha-pet.php?id=<?= (int) $pet['id'] ?>">

                        <!-- Verifica se existe uma foto cadastrada. -->
                        <?php $fotoPet = urlFotoPet($pet['foto_url'] ?? null); ?>
                        <?php if ($fotoPet): ?>

                            <img 
                                src="<?= escapar($fotoPet) ?>" 
                                alt="Foto de <?= escapar($pet['nome']) ?>">

                        <?php else: ?>

                            <!-- Exibe um ícone caso o pet não possua foto. -->
                            <div class="pet-photo-fallback">
                                <i class="fa-solid fa-paw"></i>
                            </div>

                        <?php endif; ?>

                        <!-- Nome do pet. -->
                        <strong><?= escapar($pet['nome']) ?></strong>

                        <!-- Exibe a raça ou a espécie do pet. -->
                        <span>
                            <?= escapar($pet['raca'] ?: $pet['especie']) ?>
                        </span>

                        <!-- Verifica se o pet possui pacote ativo. -->
                        <?php if ((int) $pet['pacotes_ativos'] > 0): ?>

                            <small class="package-label">
                                <?= (int) $pet['creditos_restantes'] ?> crédito(s) de pacote
                            </small>

                        <?php else: ?>

                            <small>Sem pacote ativo</small>

                        <?php endif; ?>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- Seção com o histórico financeiro do tutor. -->
    <section class="detail-card history-card">

        <div class="section-title">

            <h2>Histórico financeiro</h2>

            <small>Valores vindos dos lançamentos do fechamento diário.</small>

        </div>

        <!-- Verifica se existem registros financeiros. -->
        <?php if (!$historico): ?>

            <div class="inline-empty">
                Ainda não existem lançamentos para este tutor.
            </div>

        <?php else: ?>

            <!-- Área responsável por permitir a visualização da tabela. -->
            <div class="table-wrap">

                <table class="records-table">

                    <!-- Cabeçalho da tabela. -->
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pet</th>
                            <th>Serviço</th>
                            <th>Valor</th>
                            <th>Forma de pagamento</th>
                        </tr>
                    </thead>

                    <!-- Corpo da tabela. -->
                    <tbody>

                        <!-- Percorre cada registro do histórico. -->
                        <?php foreach ($historico as $item): ?>

                            <tr>

                                <!-- Data do fechamento. -->
                                <td>
                                    <?= escapar(formatarData($item['data_fechamento'])) ?>
                                </td>

                                <!-- Nome do pet. -->
                                <td>
                                    <?= escapar($item['pet']) ?>
                                </td>

                                <!-- Informações do serviço. -->
                                <td>

                                    <strong>
                                        <?= escapar($item['servico']) ?>
                                    </strong>

                                    <!-- Exibe a descrição quando estiver disponível. -->
                                    <?php if (!empty($item['descricao'])): ?>

                                        <small>
                                            <?= escapar($item['descricao']) ?>
                                        </small>

                                    <?php endif; ?>

                                    <!-- Exibe o valor dos adicionais quando houver. -->
                                    <?php if ((float) $item['total_adicionais'] > 0): ?>

                                        <small>
                                            Inclui <?= escapar(formatarMoeda($item['total_adicionais'])) ?>
                                            em adicional(is)
                                        </small>

                                    <?php endif; ?>

                                </td>

                                <!-- Valor total do serviço. -->
                                <td class="money">
                                    <?= escapar(formatarMoeda($item['valor_total'])) ?>
                                </td>

                                <!-- Forma de pagamento utilizada. -->
                                <td>
                                    <?= escapar($item['forma_pagamento']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php

// Carrega o rodapé da página.
require __DIR__ . '/../includes/rodape.php';

?>