<?php

// Carrega as configurações e a conexão com o banco de dados
require __DIR__ . '/config.php';

// Obtém o ID do tutor enviado pela URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redireciona caso o ID informado seja inválido
if (!$id) {
    header('Location: fichas-tutores.php');
    exit;
}

// Busca os dados do tutor e seus principais indicadores
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

// Executa a consulta do tutor
$stmt = $pdo->prepare($sqlTutor);
$stmt->execute([':id' => $id]);
$tutor = $stmt->fetch();

// Exibe erro caso o tutor não seja encontrado
if (!$tutor) {
    http_response_code(404);
    exit('Tutor não encontrado.');
}

// Busca os pets vinculados ao tutor
$sqlPets = '
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
    WHERE p.cliente_id = :cliente_id
      AND p.ativo = TRUE
    ORDER BY p.nome
';

// Executa a consulta dos pets
$stmt = $pdo->prepare($sqlPets);
$stmt->execute([':cliente_id' => $id]);
$pets = $stmt->fetchAll();

// Busca o histórico financeiro do tutor
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
            GROUP_CONCAT(
                DISTINCT fp.nome
                ORDER BY fp.nome
                SEPARATOR \' + \'
            ) AS formas
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
            GROUP_CONCAT(
                DISTINCT CASE
                    WHEN tipo_saldo = \'CREDITO\' AND operacao = \'SAIDA\'
                        THEN \'Crédito utilizado\'
                    WHEN tipo_saldo = \'CREDITO\' AND operacao = \'ENTRADA\'
                        THEN \'Crédito gerado\'
                    WHEN tipo_saldo = \'DEBITO\' AND operacao = \'ENTRADA\'
                        THEN \'Débito pendente\'
                    WHEN tipo_saldo = \'DEBITO\' AND operacao = \'SAIDA\'
                        THEN \'Débito pago\'
                END
                SEPARATOR \' + \'
            ) AS movimentos
        FROM movimentacoes_cliente
        WHERE lancamento_diario_id IS NOT NULL
        GROUP BY lancamento_diario_id
    ) mov
        ON mov.lancamento_diario_id = h.lancamento_diario_id

    WHERE h.cliente_id = :cliente_id
    ORDER BY h.data_fechamento DESC, h.lancamento_diario_id DESC
    LIMIT 100
';

// Executa a consulta do histórico financeiro
$stmt = $pdo->prepare($sqlHistorico);
$stmt->execute([':cliente_id' => $id]);
$historico = $stmt->fetchAll();

// Define as informações utilizadas pelo menu e pelo título da página
$tituloPagina = 'Ficha de ' . $tutor['nome'];
$paginaAtiva = 'fichas-tutores';

// Carrega o cabeçalho da página
require __DIR__ . '/includes/topo.php';
?>

<div class="page records-page">

    <!-- Link para retornar à lista de tutores -->
    <a class="back-link" href="fichas-tutores.php">
        <i class="fa-solid fa-arrow-left"></i>
        Voltar para fichas dos tutores
    </a>

    <div class="detail-layout">

        <!-- Exibe os dados pessoais e de contato do tutor -->
        <section class="detail-card tutor-profile-card">

            <div class="detail-profile-head">

                <div class="record-avatar large">
                    <?= escapar(iniciaisNome($tutor['nome'])) ?>
                </div>

                <div>
                    <h1><?= escapar($tutor['nome']) ?></h1>

                    <p>
                        Tutor cadastrado em
                        <?= escapar(formatarData($tutor['criado_em'])) ?>
                    </p>
                </div>

            </div>

            <!-- Apresenta os dados de contato e endereço -->
            <div class="contact-grid">

                <div>
                    <small>E-mail</small>
                    <strong><?= escapar($tutor['email'] ?: 'Não informado') ?></strong>
                </div>

                <div>
                    <small>CPF</small>
                    <strong><?= escapar(formatarCpf($tutor['cpf'])) ?></strong>
                </div>

                <div>
                    <small>Telefone</small>
                    <strong><?= escapar(formatarTelefone($tutor['telefone_principal'])) ?></strong>
                </div>

                <div>
                    <small>Telefone reserva</small>
                    <strong><?= escapar(formatarTelefone($tutor['telefone_reserva'])) ?></strong>
                </div>

                <div class="wide">
                    <small>Endereço</small>
                    <strong><?= escapar(enderecoCompleto($tutor)) ?></strong>
                </div>

                <!-- Exibe observações somente quando existem -->
                <?php if (!empty($tutor['observacoes'])): ?>

                    <div class="wide">
                        <small>Observações</small>
                        <strong><?= nl2br(escapar($tutor['observacoes'])) ?></strong>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- Apresenta o resumo financeiro e a quantidade de pets -->
        <aside class="summary-column">

            <div class="summary-card">
                <small>Total comercializado</small>
                <strong><?= escapar(formatarMoeda($tutor['total_gasto'])) ?></strong>
            </div>

            <div class="summary-card credit">
                <small>Crédito disponível</small>
                <strong><?= escapar(formatarMoeda($tutor['saldo_credito'])) ?></strong>
            </div>

            <div class="summary-card debt">
                <small>Débito pendente</small>
                <strong>
                    <?= (float) $tutor['saldo_debito'] > 0
                        ? escapar(formatarMoeda($tutor['saldo_debito']))
                        : 'Nenhum' ?>
                </strong>
            </div>

            <div class="summary-card">
                <small>Pets cadastrados</small>
                <strong><?= (int) $tutor['total_pets'] ?></strong>
            </div>

        </aside>

    </div>

    <!-- Lista os pets vinculados ao tutor -->
    <section class="detail-card section-card">

        <div class="section-title">
            <h2>Pets vinculados</h2>
            <a href="cadastro-pet.php">Cadastrar novo pet</a>
        </div>

        <!-- Verifica se existem pets cadastrados -->
        <?php if (!$pets): ?>

            <div class="inline-empty">
                Nenhum pet está vinculado a este tutor.
            </div>

        <?php else: ?>

            <div class="linked-pets-grid">

                <!-- Percorre e apresenta cada pet vinculado -->
                <?php foreach ($pets as $pet): ?>

                    <a class="linked-pet-card"
                       href="ficha-pet.php?id=<?= (int) $pet['id'] ?>">

                        <!-- Exibe a foto do pet ou um ícone padrão -->
                        <?php if (!empty($pet['foto_url'])): ?>

                            <img
                                src="<?= escapar($pet['foto_url']) ?>"
                                alt="Foto de <?= escapar($pet['nome']) ?>">

                        <?php else: ?>

                            <div class="pet-photo-fallback">
                                <i class="fa-solid fa-paw"></i>
                            </div>

                        <?php endif; ?>

                        <strong><?= escapar($pet['nome']) ?></strong>

                        <span>
                            <?= escapar($pet['raca'] ?: $pet['especie']) ?>
                        </span>

                        <!-- Informa a quantidade de créditos do pacote ativo -->
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

    <!-- Exibe o histórico financeiro do tutor -->
    <section class="detail-card history-card">

        <div class="section-title">
            <h2>Histórico financeiro</h2>
            <small>Valores vindos dos lançamentos do fechamento diário.</small>
        </div>

        <!-- Verifica se existem registros financeiros -->
        <?php if (!$historico): ?>

            <div class="inline-empty">
                Ainda não existem lançamentos para este tutor.
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table class="records-table">

                    <!-- Cabeçalho da tabela financeira -->
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

                        <!-- Percorre os registros financeiros encontrados -->
                        <?php foreach ($historico as $item): ?>

                            <tr>

                                <td>
                                    <?= escapar(formatarData($item['data_fechamento'])) ?>
                                </td>

                                <td>
                                    <?= escapar($item['pet']) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= escapar($item['servico']) ?>
                                    </strong>

                                    <!-- Exibe a descrição quando disponível -->
                                    <?php if (!empty($item['descricao'])): ?>

                                        <small>
                                            <?= escapar($item['descricao']) ?>
                                        </small>

                                    <?php endif; ?>

                                    <!-- Exibe os valores adicionais quando existirem -->
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

        <?php endif; ?>

    </section>

</div>

<?php

// Carrega o rodapé da página
require __DIR__ . '/includes/rodape.php';

?>