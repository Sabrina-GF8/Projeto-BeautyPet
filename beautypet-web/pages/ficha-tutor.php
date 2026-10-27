<?php

// Carrega as configurações do sistema e a conexão com o banco de dados.
require __DIR__ . '/../config/config.php';

// Valida o ID do tutor recebido por parâmetro na URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: fichas-tutores.php?nao_encontrado=1');
    exit;
}

// Consulta principal dos dados do perfil e métricas financeiras do tutor
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

$stmt = $pdo->prepare($sqlTutor);
$stmt->execute([':id' => $id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    header('Location: fichas-tutores.php?nao_encontrado=1');
    exit;
}

// Consulta os pets ativos vinculados ao tutor selecionado
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

$stmt = $pdo->prepare($sqlPets);
$stmt->execute([':cliente_id' => $id]);
$pets = $stmt->fetchAll();

// Configurações de paginação para o histórico financeiro (25 itens por página)
$itensHistoricoPorPagina = 25;
$paginaHistoricoAtual = max(1, (int) ($_GET['pagina_historico'] ?? 1));
$offsetHistorico = ($paginaHistoricoAtual - 1) * $itensHistoricoPorPagina;

// Contagem total de lançamentos do histórico do tutor
$stmtCountHist = $pdo->prepare('SELECT COUNT(*) FROM vw_historico_cliente WHERE cliente_id = :cliente_id');
$stmtCountHist->execute([':cliente_id' => $id]);
$totalRegistrosHistorico = (int) $stmtCountHist->fetchColumn();
$totalPaginasHistorico = max(1, ceil($totalRegistrosHistorico / $itensHistoricoPorPagina));

if ($paginaHistoricoAtual > $totalPaginasHistorico) {
    $paginaHistoricoAtual = $totalPaginasHistorico;
    $offsetHistorico = ($paginaHistoricoAtual - 1) * $itensHistoricoPorPagina;
}

// Consulta o histórico financeiro do tutor paginado
$sqlHistorico = '
    SELECT
        h.*,
        COALESCE(
            NULLIF(CONCAT_WS(\' + \', pg.formas, mov.movimentos), \'\'),
            \'Não informado\'
        ) AS forma_pagamento
    FROM vw_historico_cliente h

    LEFT JOIN (
        SELECT
            p.lancamento_diario_id,
            STRING_AGG(DISTINCT fp.nome, \' + \' ORDER BY fp.nome) AS formas
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
                    WHEN tipo_saldo = \'CREDITO\' AND operacao = \'SAIDA\'
                        THEN \'Crédito utilizado\'
                    WHEN tipo_saldo = \'CREDITO\' AND operacao = \'ENTRADA\'
                        THEN \'Crédito gerado\'
                    WHEN tipo_saldo = \'DEBITO\' AND operacao = \'ENTRADA\'
                        THEN \'Débito pendente\'
                    WHEN tipo_saldo = \'DEBITO\' AND operacao = \'SAIDA\'
                        THEN \'Débito pago\'
                END,
                \' + \'
            ) AS movimentos
        FROM movimentacoes_cliente
        WHERE lancamento_diario_id IS NOT NULL
        GROUP BY lancamento_diario_id
    ) mov
        ON mov.lancamento_diario_id = h.lancamento_diario_id

    WHERE h.cliente_id = :cliente_id
    ORDER BY h.data_fechamento DESC, h.lancamento_diario_id DESC
    LIMIT ' . $itensHistoricoPorPagina . ' OFFSET ' . $offsetHistorico;

$stmt = $pdo->prepare($sqlHistorico);
$stmt->execute([':cliente_id' => $id]);
$historico = $stmt->fetchAll();

$tituloPagina = 'Ficha de ' . $tutor['nome'];
$paginaAtiva = 'fichas-tutores';
$cssPagina = 'ficha';
require __DIR__ . '/../includes/topo.php';

?>

<div class="page records-page">

    <!-- Link para retornar à lista de tutores -->
    <a class="back-link" href="fichas-tutores.php">
        <i class="fa-solid fa-arrow-left"></i>
        Voltar para fichas dos tutores
    </a>

    <?php if (isset($_GET['editado'])): ?>
        <div class="message success detail-edit-message">Dados atualizados com sucesso!</div>
    <?php endif; ?>

    <!-- Layout principal contendo perfil do tutor e resumo lateral -->
    <div class="detail-layout">

        <!-- Card com os dados pessoais do tutor e botão de edição integrado -->
        <section class="detail-card tutor-profile-card">

            <div>
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
            </div>

            <!-- Botão de edição posicionado no canto inferior direito do card -->
            <div class="tutor-action-wrapper">
                <a class="detail-edit-button" href="cadastro-tutor.php?editar=<?= (int) $id ?>">
                    Editar tutor
                </a>
            </div>

        </section>

       <!-- Coluna lateral com resumo financeiro e quantidade de pets -->
        <aside class="summary-column">

            <article class="daily-summary-card emphasis">
                <span>Total comercializado</span>
                <strong><?= escapar(formatarMoeda($tutor['total_gasto'])) ?></strong>
                <small>Serviços + adicionais</small>
            </article>

            <article class="daily-summary-card green-soft">
                <span>Crédito disponível</span>
                <strong><?= escapar(formatarMoeda($tutor['saldo_credito'])) ?></strong>
                <small>Saldo a favor</small>
            </article>

            <article class="daily-summary-card danger-soft">
                <span>Débito pendente</span>
                <strong>
                    <?= (float) $tutor['saldo_debito'] > 0
                        ? escapar(formatarMoeda($tutor['saldo_debito']))
                        : 'Nenhum' ?>
                </strong>
                <small>Valor em aberto</small>
            </article>

            <article class="daily-summary-card">
                <span>Pets cadastrados</span>
                <strong><?= (int) $tutor['total_pets'] ?></strong>
                <small>Vinculados ao tutor</small>
            </article>

        </aside>
    </div>

    <!-- Seção de listagem de pets vinculados -->
    <section class="detail-card section-card">

        <div class="section-title">
            <h2>Pets vinculados</h2>
            <a class="detail-edit-button dashed-add-button" href="cadastro-pet.php?tutor=<?= (int) $id ?>">
                Cadastrar novo pet
            </a>
        </div>

        <?php if (!$pets): ?>
            <div class="inline-empty">
                Nenhum pet está vinculado a este tutor.
            </div>
        <?php else: ?>
            <div class="linked-pets-grid">
                <?php foreach ($pets as $pet): ?>
                    <a class="linked-pet-card" href="ficha-pet.php?id=<?= (int) $pet['id'] ?>">

                        <?php $fotoPet = urlFotoPet($pet['foto_url'] ?? null); ?>
                        <?php if ($fotoPet): ?>
                            <img src="<?= escapar($fotoPet) ?>" alt="Foto de <?= escapar($pet['nome']) ?>">
                        <?php else: ?>
                            <div class="pet-photo-fallback">
                                <i class="fa-solid fa-paw"></i>
                            </div>
                        <?php endif; ?>

                        <strong><?= escapar($pet['nome']) ?></strong>
                        <span><?= escapar($pet['raca'] ?: $pet['especie']) ?></span>

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

    <!-- Seção de histórico financeiro com paginação -->
    <section class="detail-card history-card">

        <div class="section-title">
            <h2>Histórico financeiro</h2>
            <small>Valores vindos dos lançamentos do fechamento diário.</small>
        </div>

        <?php if (!$historico): ?>
            <div class="inline-empty">
                Ainda não existem lançamentos para este tutor.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pet</th>
                            <th>Serviço</th>
                            <th>Valor</th>
                            <th>Forma de pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $item): ?>
                            <tr>
                                <td>
                                    <?= escapar(formatarData($item['data_fechamento'])) ?>
                                </td>
                                <td>
                                    <?= escapar($item['pet']) ?>
                                </td>
                                <td>
                                    <strong><?= escapar($item['servico']) ?></strong>
                                    <?php if (!empty($item['descricao'])): ?>
                                        <small><?= escapar($item['descricao']) ?></small>
                                    <?php endif; ?>
                                    <?php if ((float) $item['total_adicionais'] > 0): ?>
                                        <small>
                                            Inclui <?= escapar(formatarMoeda($item['total_adicionais'])) ?>
                                            em adicional(is)
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="money">
                                    <?= escapar(formatarMoeda($item['valor_total'])) ?>
                                </td>
                                <td>
                                    <?= escapar($item['forma_pagamento']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Renderiza a barra de paginação do histórico -->
            <?php if ($totalPaginasHistorico > 1): ?>
                <div class="pagination-bar">
                    <?php if ($paginaHistoricoAtual > 1): ?>
                        <a href="ficha-tutor.php?id=<?= $id ?>&pagina_historico=<?= $paginaHistoricoAtual - 1 ?>" class="pagination-button">
                            <i class="fa-solid fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="pagination-button disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                    <?php endif; ?>

                    <div class="pagination-numbers">
                        <?php for ($i = 1; $i <= $totalPaginasHistorico; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPaginasHistorico || ($i >= $paginaHistoricoAtual - 2 && $i <= $paginaHistoricoAtual + 2)): ?>
                                <a href="ficha-tutor.php?id=<?= $id ?>&pagina_historico=<?= $i ?>" class="pagination-num <?= $i === $paginaHistoricoAtual ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php elseif ($i == $paginaHistoricoAtual - 3 || $i == $paginaHistoricoAtual + 3): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <?php if ($paginaHistoricoAtual < $totalPaginasHistorico): ?>
                        <a href="ficha-tutor.php?id=<?= $id ?>&pagina_historico=<?= $paginaHistoricoAtual + 1 ?>" class="pagination-button">
                            Seguinte <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-button disabled">Seguinte <i class="fa-solid fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </section>

</div>

<?php
require __DIR__ . '/../includes/rodape.php';
?>