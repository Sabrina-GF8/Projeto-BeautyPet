<?php
/**
 * ARQUIVO: ficha-pet.php
 * RESPONSABILIDADE: Ficha detalhada do pet.
 * Carrega dados cadastrais, tutor, pacotes e histórico de serviços/financeiro para exibição da ficha do animal.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Converte o identificador recebido para uso seguro nas consultas e alterações.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Verifica a condição abaixo antes de executar este bloco.
if (!$id) {
    // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
    header('Location: fichas-pets.php');
    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
    exit;
}

// Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
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

// Prepara uma consulta em "vw_saldo_pacotes" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare($sqlPet);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $id]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$pet = $stmt->fetch();

// Verifica a condição abaixo antes de executar este bloco.
if (!$pet) {
    http_response_code(404);
    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
    exit('Pet não encontrado.');
}

// Armazena a coleção "sqlPacotes" carregada para ser percorrida e exibida/processada a seguir.
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

// Prepara uma consulta em "vw_historico_pet" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare($sqlPacotes);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':pet_id' => $id]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$pacotes = $stmt->fetchAll();

// Soma os créditos ainda disponíveis nos pacotes ativos do pet.
$totalCreditosRestantes = array_sum(
    array_map(
        static fn(array $pacote): int => (int) $pacote['creditos_restantes'],
        $pacotes
    )
);

// Prepara uma consulta em "vw_historico_pet" para carregar os dados necessários a esta etapa.
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
    LIMIT 100
';

// Prepara a consulta SQL que será executada com parâmetros separados para manter segurança e clareza.
$stmt = $pdo->prepare($sqlHistorico);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':pet_id' => $id]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$historico = $stmt->fetchAll();

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Ficha de ' . $pet['nome'];
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'fichas-pets';

// Define o(s) CSS desta página em assets/css/ (ficha.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'ficha';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
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

    <div class="detail-page-actions">
        <a class="detail-edit-button" href="cadastro-pet.php?editar=<?= (int) $id ?>">
            <i class="fa-solid fa-pen"></i>
            Editar pet
        </a>
    </div>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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

        <!-- Bloco visual "pet-summary-grid" que organiza esta parte da interface. -->
        <div class="pet-summary-grid">
            <!-- Bloco visual "summary-card" que organiza esta parte da interface. -->
            <div class="summary-card">
                <small>Total comercializado</small>
                <strong><?= escapar(formatarMoeda($pet['total_gasto'])) ?></strong>
            </div>

            <!-- Bloco visual "summary-card credit" que organiza esta parte da interface. -->
            <div class="summary-card credit">
                <small>Crédito do tutor</small>
                <strong><?= escapar(formatarMoeda($pet['saldo_credito_tutor'])) ?></strong>
            </div>

            <!-- Bloco visual "summary-card debt" que organiza esta parte da interface. -->
            <div class="summary-card debt">
                <small>Débito do tutor</small>
                <strong>
                    <?= (float) $pet['saldo_debito_tutor'] > 0
                        ? escapar(formatarMoeda($pet['saldo_debito_tutor']))
                        : 'Nenhum' ?>
                </strong>
            </div>

            <!-- Bloco visual "summary-card" que organiza esta parte da interface. -->
            <div class="summary-card">
                <small>Créditos de pacote</small>
                <strong><?= $totalCreditosRestantes ?></strong>
            </div>
        </div>
    </section>

    <div class="pet-detail-columns">
        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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

        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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
            <div class="timeline">
                <?php foreach ($historico as $item): ?>
                    <article class="timeline-item">
                        <div class="timeline-dot"></div>

                        <div class="timeline-content">
                            <strong><?= escapar($item['servico']) ?></strong>

                            <?php if (!empty($item['descricao'])): ?>
                                <p><?= escapar($item['descricao']) ?></p>
                            <?php endif; ?>

                            <small>
                                Pagamento: <?= escapar($item['forma_pagamento']) ?>
                            </small>

                            <?php if ((float) $item['total_adicionais'] > 0): ?>
                                <small>
                                    Adicionais:
                                    <?= escapar(formatarMoeda($item['total_adicionais'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="timeline-value">
                            <strong><?= escapar(formatarMoeda($item['valor_total'])) ?></strong>
                            <span><?= escapar(formatarData($item['data_fechamento'])) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
