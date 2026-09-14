<?php
/**
 * ARQUIVO: ficha-tutor.php
 * RESPONSABILIDADE: Ficha detalhada do tutor.
 * Carrega dados cadastrais, pets vinculados, saldos de crédito/débito e histórico financeiro do tutor.
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
    header('Location: fichas-tutores.php');
    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
    exit;
}

// Monta a consulta SQL que será preparada e executada logo abaixo.
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

// Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare($sqlTutor);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':id' => $id]);
// Obtém a próxima linha retornada pela consulta como um registro associativo.
$tutor = $stmt->fetch();

// Verifica a condição abaixo antes de executar este bloco.
if (!$tutor) {
    http_response_code(404);
    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
    exit('Tutor não encontrado.');
}

// Armazena a coleção "sqlPets" carregada para ser percorrida e exibida/processada a seguir.
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

// Prepara uma consulta em "vw_historico_cliente" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare($sqlPets);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':cliente_id' => $id]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$pets = $stmt->fetchAll();

// Prepara uma consulta em "vw_historico_cliente" para carregar os dados necessários a esta etapa.
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

// Prepara a consulta SQL que será executada com parâmetros separados para manter segurança e clareza.
$stmt = $pdo->prepare($sqlHistorico);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':cliente_id' => $id]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$historico = $stmt->fetchAll();

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Ficha de ' . $tutor['nome'];
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'fichas-tutores';

// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page records-page">
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

    <div class="detail-layout">
        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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

                <?php if (!empty($tutor['observacoes'])): ?>
                    <div class="wide">
                        <small>Observações</small>
                        <strong><?= nl2br(escapar($tutor['observacoes'])) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="summary-column">
            <!-- Bloco visual "summary-card" que organiza esta parte da interface. -->
            <div class="summary-card">
                <small>Total comercializado</small>
                <strong><?= escapar(formatarMoeda($tutor['total_gasto'])) ?></strong>
            </div>

            <!-- Bloco visual "summary-card credit" que organiza esta parte da interface. -->
            <div class="summary-card credit">
                <small>Crédito disponível</small>
                <strong><?= escapar(formatarMoeda($tutor['saldo_credito'])) ?></strong>
            </div>

            <!-- Bloco visual "summary-card debt" que organiza esta parte da interface. -->
            <div class="summary-card debt">
                <small>Débito pendente</small>
                <strong>
                    <?= (float) $tutor['saldo_debito'] > 0
                        ? escapar(formatarMoeda($tutor['saldo_debito']))
                        : 'Nenhum' ?>
                </strong>
            </div>

            <!-- Bloco visual "summary-card" que organiza esta parte da interface. -->
            <div class="summary-card">
                <small>Pets cadastrados</small>
                <strong><?= (int) $tutor['total_pets'] ?></strong>
            </div>
        </aside>
    </div>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="detail-card section-card">
        <div class="section-title">
            <h2>Pets vinculados</h2>
            <a href="cadastro-pet.php">Cadastrar novo pet</a>
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
                        <span>
                            <?= escapar($pet['raca'] ?: $pet['especie']) ?>
                        </span>

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

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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
                <!-- Tabela usada para organizar os registros em colunas de leitura rápida. -->
                <table class="records-table">
                    <!-- Cabeçalho da tabela com os nomes das informações exibidas em cada coluna. -->
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pet</th>
                            <th>Serviço</th>
                            <th>Valor</th>
                            <th>Forma de pagamento</th>
                        </tr>
                    </thead>

                    <!-- Corpo da tabela onde os registros retornados pelo banco são listados. -->
                    <tbody>
                        <?php foreach ($historico as $item): ?>
                            <tr>
                                <td><?= escapar(formatarData($item['data_fechamento'])) ?></td>
                                <td><?= escapar($item['pet']) ?></td>
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
                                <td><?= escapar($item['forma_pagamento']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
