<?php
/**
 * ARQUIVO: ficha-pet.php
 * RESPONSABILIDADE: Ficha detalhada do pet.
 * Carrega dados cadastrais, tutor, pacotes e histórico de serviços/financeiro com paginação.
 * Inclui também a lógica de exclusão restrita a administradores (Alteração #4).
 */
require __DIR__ . '/../config/config.php';

// Converte o identificador recebido para uso seguro nas consultas e alterações.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: fichas-pets.php?nao_encontrado=1');
    exit;
}

// Obtém o usuário atual para checar se é ADMINISTRADOR (Controle de Acesso da Alteração #4)
$usuarioAtual = obterUsuarioAtual($pdo);
$ehAdministrador = (($usuarioAtual['perfil'] ?? null) === 'ADMINISTRADOR');

// ---------------------------------------------------------------------------
// Processamento da Exclusão do Pet (Backend - Alteração #4)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_pet') {
    if (!$ehAdministrador) {
        header('Location: ficha-pet.php?id=' . $id . '&erro=permissao');
        exit;
    }

    try {
        // Exclusão segura utilizando Prepared Statement
        $stmtDel = $pdo->prepare('UPDATE pets SET ativo = FALSE WHERE id = :id');
        $stmtDel->execute([':id' => $id]);

        header('Location: fichas-pets.php?deletado=1');
        exit;
    } catch (Throwable $e) {
        error_log('[BeautyPet] Erro ao excluir pet: ' . $e->getMessage());
        header('Location: ficha-pet.php?id=' . $id . '&erro=conexao');
        exit;
    }
}

// Prepara uma consulta em "pets" para carregar os dados cadastrais e financeiros do pet.
$sqlPet = '
    SELECT
        p.*,
        c.nome AS tutor,
        c.id AS tutor_id,
        e.nome AS especie,
        r.nome AS raca,
        COALESCE(s.saldo_credito, 0.00) AS saldo_credito_tutor,
        COALESCE(s.saldo_debito, 0.00)  AS saldo_debito_tutor,
        COALESCE(g.total_gasto, 0.00)   AS total_gasto
    FROM pets p
    JOIN clientes c
        ON c.id = p.cliente_id
    JOIN especies e
        ON e.id = p.especie_id
    LEFT JOIN racas r
        ON r.id = p.raca_id
    LEFT JOIN vw_saldo_cliente s
        ON s.cliente_id = c.id
    LEFT JOIN (
        SELECT
            pet_id,
            SUM(valor_total_lancamento) AS total_gasto
        FROM vw_total_lancamento
        GROUP BY pet_id
    ) g
        ON g.pet_id = p.id
    WHERE p.id = :id
      AND p.ativo = TRUE
';

$stmt = $pdo->prepare($sqlPet);
$stmt->execute([':id' => $id]);
$pet = $stmt->fetch();

if (!$pet) {
    header('Location: fichas-pets.php?nao_encontrado=1');
    exit;
}

// Carrega os pacotes ativos e com créditos restantes do pet.
$sqlPacotes = '
    SELECT
        pet_pacote_id,
        pacote,
        data_compra,
        data_validade,
        creditos_totais,
        creditos_utilizados,
        creditos_restantes,
        status
    FROM vw_saldo_pacotes
    WHERE pet_id = :pet_id
      AND status = \'ATIVO\'
      AND creditos_restantes > 0
    ORDER BY data_compra DESC
';

$stmt = $pdo->prepare($sqlPacotes);
$stmt->execute([':pet_id' => $id]);
$pacotes = $stmt->fetchAll();

// Soma os créditos ainda disponíveis nos pacotes ativos do pet.
$totalCreditosRestantes = array_sum(
    array_map(
        static fn(array $pacote): int => (int) $pacote['creditos_restantes'],
        $pacotes
    )
);

// --- CONFIGURAÇÃO DA PAGINAÇÃO DO HISTÓRICO (25 itens por página) ---
$itensHistoricoPorPagina = 25;
$paginaHistoricoAtual = max(1, (int) ($_GET['pagina_historico'] ?? 1));
$offsetHistorico = ($paginaHistoricoAtual - 1) * $itensHistoricoPorPagina;

// Conta o total de registros no histórico deste pet para calcular as páginas.
$stmtCountHist = $pdo->prepare('SELECT COUNT(*) FROM vw_historico_pet WHERE pet_id = :pet_id');
$stmtCountHist->execute([':pet_id' => $id]);
$totalRegistrosHistorico = (int) $stmtCountHist->fetchColumn();
$totalPaginasHistorico = max(1, ceil($totalRegistrosHistorico / $itensHistoricoPorPagina));

if ($paginaHistoricoAtual > $totalPaginasHistorico) {
    $paginaHistoricoAtual = $totalPaginasHistorico;
    $offsetHistorico = ($paginaHistoricoAtual - 1) * $itensHistoricoPorPagina;
}

// Consulta o histórico de atendimentos do pet com limite e offset aplicados.
$sqlHistorico = '
    SELECT
        h.*,
        COALESCE(
            NULLIF(CONCAT_WS(\' + \', pg.formas, mov.movimentos), \'\'),
            \'Não informado\'
        ) AS forma_pagamento
    FROM vw_historico_pet h

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

    WHERE h.pet_id = :pet_id
    ORDER BY h.data_fechamento DESC, h.lancamento_diario_id DESC
    LIMIT ' . $itensHistoricoPorPagina . ' OFFSET ' . $offsetHistorico;

$stmt = $pdo->prepare($sqlHistorico);
$stmt->execute([':pet_id' => $id]);
$historico = $stmt->fetchAll();

// Define o título e os metadados da página.
$tituloPagina = 'Ficha de ' . $pet['nome'];
$paginaAtiva = 'fichas-pets';
$cssPagina = 'ficha';
require __DIR__ . '/../includes/topo.php';
?>

<div class="page records-page">
    <a class="back-link" href="fichas-pets.php">
        <i class="fa-solid fa-arrow-left"></i>
        Voltar para fichas dos pets
    </a>

    <?php if (isset($_GET['editado'])): ?>
        <div class="message success detail-edit-message">Dados atualizados com sucesso!</div>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <div class="message error detail-edit-message">
            <?php
                if ($_GET['erro'] === 'permissao') echo 'Você não possui permissão de Administrador para realizar esta exclusão.';
                else echo 'Não foi possível realizar a exclusão do pet. Tente novamente.';
            ?>
        </div>
    <?php endif; ?>

    <!-- Seção de topo com identidade, resumos do pet e botões de ação alinhados à direita -->
    <section class="detail-card pet-profile-top">
        <div class="pet-profile-identity">
            <?php $fotoPet = urlFotoPet($pet['foto_url'] ?? null); ?>
            <?php if ($fotoPet): ?>
                <img src="<?= escapar($fotoPet) ?>" alt="Foto de <?= escapar($pet['nome']) ?>">
            <?php else: ?>
                <div class="pet-photo-fallback large">
                    <i class="fa-solid fa-paw"></i>
                </div>
            <?php endif; ?>

            <div>
                <h1><?= escapar($pet['nome']) ?></h1>
                <strong><?= escapar($pet['raca'] ?: $pet['especie']) ?></strong>
                <a href="ficha-tutor.php?id=<?= (int) $pet['tutor_id'] ?>">
                    <?= escapar($pet['tutor']) ?>
                </a>
            </div>
        </div>

       <div class="pet-summary-column">
        <div class="pet-summary-grid">
            <article class="daily-summary-card emphasis">
                <span>Total comercializado</span>
                <strong><?= escapar(formatarMoeda($pet['total_gasto'])) ?></strong>
                <small>Serviços + adicionais</small>
            </article>

            <article class="daily-summary-card green-soft">
                <span>Crédito do tutor</span>
                <strong><?= escapar(formatarMoeda($pet['saldo_credito_tutor'])) ?></strong>
                <small>Saldo disponível</small>
            </article>

            <article class="daily-summary-card danger-soft">
                <span>Débito do tutor</span>
                <strong>
                    <?= (float) $pet['saldo_debito_tutor'] > 0
                        ? escapar(formatarMoeda($pet['saldo_debito_tutor']))
                        : 'Nenhum' ?>
                </strong>
                <small>Valor pendente</small>
            </article>

            <article class="daily-summary-card">
                <span>Créditos de pacote</span>
                <strong><?= $totalCreditosRestantes ?></strong>
                <small>Ainda disponíveis</small>
            </article>
        </div>

        <!-- Botão de edição e botão Excluir com o design customizado .danger alinhados à direita -->
        <div class="pet-action-wrapper" style="display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
            <a class="detail-edit-button" href="cadastro-pet.php?editar=<?= (int) $id ?>">
                Editar pet
            </a>

            <?php if ($ehAdministrador): ?>
                <form method="post" id="formExcluirPet" onsubmit="return confirm('Tem certeza que deseja excluir este pet? Esta ação desativará o registro do sistema.');" style="margin: 0;">
                    <input type="hidden" name="acao" value="excluir_pet">
                    <button type="submit" class="detail-edit-button icon-action-button danger" style="background: #ffe4e4; color: #b21f1f; border: 1px solid #f0cbc8;">
                        Excluir
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

    <!-- Colunas de detalhes do pet -->
    <div class="pet-detail-columns">
        <section class="detail-card section-card">
            <h2>Dados do pet</h2>

            <div class="data-grid">
                <div>
                    <small>Nome</small>
                    <strong><?= escapar($pet['nome']) ?></strong>
                </div>

                <div>
                    <small>Espécie</small>
                    <strong><?= escapar($pet['especie']) ?></strong>
                </div>

                <div>
                    <small>Raça</small>
                    <strong><?= escapar($pet['raca'] ?: 'Não informada') ?></strong>
                </div>

                <div>
                    <small>Sexo</small>
                    <strong><?= escapar(nomeSexo($pet['sexo'])) ?></strong>
                </div>

                <div>
                    <small>Idade</small>
                    <strong>
                        <?= escapar(
                            idadePet(
                                $pet['data_nascimento'],
                                (bool) $pet['nascimento_aproximado']
                            )
                        ) ?>
                    </strong>
                </div>

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

                <div>
                    <small>Cor</small>
                    <strong><?= escapar($pet['cor'] ?: 'Não informada') ?></strong>
                </div>

                <div>
                    <small>Castrado(a)</small>
                    <strong><?= escapar(textoBooleano($pet['castrado'])) ?></strong>
                </div>

                <div>
                    <small>Vacinação em dia</small>
                    <strong><?= escapar(textoBooleano($pet['vacinacao_em_dia'])) ?></strong>
                </div>

                <div class="wide">
                    <small>Data de nascimento</small>
                    <strong><?= escapar(formatarData($pet['data_nascimento'])) ?></strong>
                </div>
            </div>
        </section>

        <section class="detail-card section-card">
            <h2>Comportamento e saúde</h2>

            <div class="text-information">
                <div>
                    <small>Comportamento</small>
                    <p>
                        <?= nl2br(
                            escapar(
                                $pet['observacoes_comportamentais']
                                ?: 'Nenhuma observação comportamental cadastrada.'
                            )
                        ) ?>
                    </p>
                </div>

                <div>
                    <small>Medicamentos</small>
                    <p>
                        <?= nl2br(
                            escapar(
                                $pet['medicamentos']
                                ?: 'Nenhum medicamento cadastrado.'
                            )
                        ) ?>
                    </p>
                </div>

                <div>
                    <small>Observações médicas</small>
                    <p>
                        <?= nl2br(
                            escapar(
                                $pet['observacoes_medicas']
                                ?: 'Nenhuma observação médica cadastrada.'
                            )
                        ) ?>
                    </p>
                </div>
            </div>
        </section>
    </div>

    <!-- Seção de pacotes ativos -->
    <section class="detail-card section-card">
        <div class="section-title">
            <h2>Pacotes ativos</h2>
            <small>Somente pacotes com créditos restantes.</small>
        </div>

        <?php if (!$pacotes): ?>
            <div class="inline-empty">
                Este pet não possui pacote ativo. Os atendimentos avulsos continuam aparecendo normalmente no histórico.
            </div>
        <?php else: ?>
            <div class="package-grid">
                <?php foreach ($pacotes as $pacote): ?>
                    <article class="package-card">
                        <strong><?= escapar($pacote['pacote']) ?></strong>

                        <div>
                            <span>
                                <?= (int) $pacote['creditos_utilizados'] ?>
                                usado(s)
                            </span>
                            <span>
                                <?= (int) $pacote['creditos_restantes'] ?>
                                restante(s)
                            </span>
                        </div>

                        <small>
                            Compra: <?= escapar(formatarData($pacote['data_compra'])) ?>

                            <?php if (!empty($pacote['data_validade'])): ?>
                                • Validade: <?= escapar(formatarData($pacote['data_validade'])) ?>
                            <?php endif; ?>
                        </small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Seção de histórico financeiro em formato de tabela idêntico ao tutor -->
    <section class="detail-card history-card">
        <div class="section-title">
            <h2>Histórico de atendimentos</h2>
            <small>Valores inseridos nos lançamentos do fechamento diário.</small>
        </div>

        <?php if (!$historico): ?>
            <div class="inline-empty">
                Ainda não existem atendimentos lançados para este pet.
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

            <!-- Barra de paginação numérica do histórico -->
            <?php if ($totalPaginasHistorico > 1): ?>
                <div class="pagination-bar">
                    <?php if ($paginaHistoricoAtual > 1): ?>
                        <a href="ficha-pet.php?id=<?= $id ?>&pagina_historico=<?= $paginaHistoricoAtual - 1 ?>" class="pagination-button">
                            <i class="fa-solid fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="pagination-button disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                    <?php endif; ?>

                    <div class="pagination-numbers">
                        <?php for ($i = 1; $i <= $totalPaginasHistorico; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPaginasHistorico || ($i >= $paginaHistoricoAtual - 2 && $i <= $paginaHistoricoAtual + 2)): ?>
                                <a href="ficha-pet.php?id=<?= $id ?>&pagina_historico=<?= $i ?>" class="pagination-num <?= $i === $paginaHistoricoAtual ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php elseif ($i == $paginaHistoricoAtual - 3 || $i == $paginaHistoricoAtual + 3): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <?php if ($paginaHistoricoAtual < $totalPaginasHistorico): ?>
                        <a href="ficha-pet.php?id=<?= $id ?>&pagina_historico=<?= $paginaHistoricoAtual + 1 ?>" class="pagination-button">
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

<?php require __DIR__ . '/../includes/rodape.php'; ?>