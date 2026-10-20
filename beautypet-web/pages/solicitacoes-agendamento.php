<?php
/**
 * ARQUIVO: solicitacoes-agendamento.php
 * RESPONSABILIDADE: Gerenciamento de solicitações de agendamento.
 * Lista solicitações recebidas, permite confirmar, recusar ou reagendar e registra quem respondeu a cada pedido.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';
// Armazena em "usuarioAtualSolicitacao" o valor necessário para as próximas etapas deste fluxo.
$usuarioAtualSolicitacao = obterUsuarioAtual($pdo);
// Guarda somente o ID do usuário atual para registrar autoria das operações no banco.
$usuarioId = isset($usuarioAtualSolicitacao['id'])
    ? (int) $usuarioAtualSolicitacao['id']
    : null;

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
// Armazena em "aba" o valor necessário para as próximas etapas deste fluxo.
$aba = (string) ($_GET['aba'] ?? 'pendentes');

// Verifica a condição abaixo antes de executar este bloco.
if (!in_array($aba, ['pendentes', 'confirmadas'], true)) {
    // Armazena em "aba" o valor necessário para as próximas etapas deste fluxo.
    $aba = 'pendentes';
}

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação o formulário solicitou para direcionar o processamento correto.
    $acao = (string) ($_POST['acao'] ?? '');
    // Guarda o ID do agendamento criado ou selecionado para edição, status ou exclusão.
    $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);

    // Valida a existência do identificador antes de alterar ou excluir um agendamento.
    if ($agendamentoId === null) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'A solicitação selecionada é inválida.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (in_array($acao, ['confirmar', 'recusar', 'cancelar'], true)) {
        // Guarda o status solicitado e permite validar se a transição é aceita antes de atualizar o banco.
        $novoStatus = match ($acao) {
            'confirmar' => 'CONFIRMADO',
            'recusar'   => 'RECUSADO',
            'cancelar'  => 'CANCELADO',
        };

        // Prepara a atualização do registro correspondente em "agendamentos" com os novos dados.
        $stmt = $pdo->prepare(
            "UPDATE agendamentos
             SET status = :status,
                 respondido_por = :usuario_id,
                 respondido_em = CURRENT_TIMESTAMP
             WHERE id = :id
               AND origem = 'MOBILE'"
        );

        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([
            ':status'     => $novoStatus,
            ':usuario_id' => $usuarioId,
            ':id'         => $agendamentoId,
        ]);

        // Armazena em "abaRetorno" o valor necessário para as próximas etapas deste fluxo.
        $abaRetorno = $acao === 'cancelar'
            ? 'confirmadas'
            : 'pendentes';

        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header(
            'Location: solicitacoes-agendamento.php?aba='
            . $abaRetorno
            . '&atualizado=1'
        );
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($acao === 'reagendar') {
        // Armazena a data "data" usada nas validações e consultas desta página.
        $data = (string) ($_POST['data'] ?? '');
        // Armazena o horário "hora" utilizado na regra de agendamento/atendimento.
        $hora = (string) ($_POST['hora'] ?? '');

        // Verifica a condição abaixo antes de executar este bloco.
        if (!dataSqlValida($data) || !horaSqlValida($hora)) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Informe uma nova data e um novo horário válidos.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Prepara uma consulta em "agendamentos" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                "SELECT data_hora_inicio, data_hora_fim
                 FROM agendamentos
                 WHERE id = :id
                   AND origem = 'MOBILE'"
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':id' => $agendamentoId]);
            // Obtém a próxima linha retornada pela consulta como um registro associativo.
            $atual = $stmt->fetch();

            // Verifica a condição abaixo antes de executar este bloco.
            if (!$atual) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'A solicitação não foi encontrada.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Armazena em "novoInicio" o valor necessário para as próximas etapas deste fluxo.
                $novoInicio = new DateTime($data . ' ' . $hora . ':00');
                // Armazena em "novoFim" o valor necessário para as próximas etapas deste fluxo.
                $novoFim = null;

                // Verifica a condição abaixo antes de executar este bloco.
                if (!empty($atual['data_hora_fim'])) {
                    // Armazena em "inicioAnterior" o valor necessário para as próximas etapas deste fluxo.
                    $inicioAnterior = new DateTime($atual['data_hora_inicio']);
                    // Armazena em "fimAnterior" o valor necessário para as próximas etapas deste fluxo.
                    $fimAnterior = new DateTime($atual['data_hora_fim']);
                    // Armazena em "duracao" o valor necessário para as próximas etapas deste fluxo.
                    $duracao = max(
                        0,
                        $fimAnterior->getTimestamp()
                        - $inicioAnterior->getTimestamp()
                    );

                    // Armazena em "novoFim" o valor necessário para as próximas etapas deste fluxo.
                    $novoFim = (clone $novoInicio)
                        ->modify('+' . $duracao . ' seconds')
                        ->format('Y-m-d H:i:s');
                }

                // Prepara a atualização do registro correspondente em "agendamentos" com os novos dados.
                $stmt = $pdo->prepare(
                    "UPDATE agendamentos
                     SET data_hora_inicio = :inicio,
                         data_hora_fim = :fim,
                         status = 'CONFIRMADO',
                         respondido_por = :usuario_id,
                         respondido_em = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND origem = 'MOBILE'"
                );

                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute([
                    ':inicio'     => $novoInicio->format('Y-m-d H:i:s'),
                    ':fim'        => $novoFim,
                    ':usuario_id' => $usuarioId,
                    ':id'         => $agendamentoId,
                ]);

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header(
                    'Location: solicitacoes-agendamento.php'
                    . '?aba=confirmadas&reagendado=1'
                );
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            }
        }
    }
}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['atualizado'])) {
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Solicitação atualizada com sucesso.';
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success';
}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reagendado'])) {
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Novo horário salvo e agendamento confirmado.';
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success';
}

// Agrupa os totais calculados para exibição no resumo financeiro da página.
$totais = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status = 'SOLICITADO' THEN 1 ELSE 0 END), 0) AS pendentes,
        COALESCE(SUM(CASE WHEN status = 'CONFIRMADO' THEN 1 ELSE 0 END), 0) AS confirmadas
     FROM agendamentos
     WHERE origem = 'MOBILE'"
)->fetch();

// Calcula/armazena o total de "totalPendentes" que será usado nos resumos e na interface.
$totalPendentes = (int) ($totais['pendentes'] ?? 0);
// Calcula/armazena o total de "totalConfirmadas" que será usado nos resumos e na interface.
$totalConfirmadas = (int) ($totais['confirmadas'] ?? 0);

// Armazena em "statusConsulta" o valor necessário para as próximas etapas deste fluxo.
$statusConsulta = $aba === 'pendentes'
    ? 'SOLICITADO'
    : 'CONFIRMADO';

// Prepara uma consulta em "agendamentos" para carregar os dados necessários a esta etapa.
$stmt = $pdo->prepare(
    "SELECT
        a.id,
        a.data_hora_inicio,
        a.data_hora_fim,
        a.status,
        a.observacoes_cliente,
        p.nome AS pet,
        p.foto_url,
        c.nome AS tutor,
        STRING_AGG(DISTINCT s.nome, ', ' ORDER BY s.nome) AS servicos
     FROM agendamentos a
     JOIN pets p
        ON p.id = a.pet_id
     JOIN clientes c
        ON c.id = p.cliente_id
     LEFT JOIN agendamento_itens ai
        ON ai.agendamento_id = a.id
     LEFT JOIN servicos s
        ON s.id = ai.servico_id
     WHERE a.origem = 'MOBILE'
        AND a.status = :status
     GROUP BY
        a.id,
        a.data_hora_inicio,
        a.data_hora_fim,
        a.status,
        a.observacoes_cliente,
        p.nome,
        p.foto_url,
        c.nome
     ORDER BY a.data_hora_inicio"
);
// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([':status' => $statusConsulta]);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$solicitacoes = $stmt->fetchAll();

// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? null);
// Carrega a solicitação selecionada para preencher os campos durante o reagendamento ou edição.
$solicitacaoEditar = null;

// Verifica a condição abaixo antes de executar este bloco.
if ($editarId !== null) {
    // Prepara uma consulta em "agendamentos" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        "SELECT
            a.id,
            a.data_hora_inicio,
            p.nome AS pet,
            c.nome AS tutor
         FROM agendamentos a
         JOIN pets p
           ON p.id = a.pet_id
         JOIN clientes c
           ON c.id = p.cliente_id
         WHERE a.id = :id
           AND a.origem = 'MOBILE'
           AND a.status IN ('SOLICITADO', 'CONFIRMADO')"
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $editarId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $solicitacaoEditar = $stmt->fetch() ?: null;
}

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Solicitações de agendamento';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'solicitacoes-agendamento';
// Define o(s) CSS desta página em assets/css/ (solicitacoes-agendamento.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'solicitacoes-agendamento';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page requests-page">
    <!-- Bloco visual "page-heading agenda-heading" que organiza esta parte da interface. -->
    <div class="page-heading agenda-heading">
        <div>
            <a class="back-link" href="agenda.php">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar para a agenda
            </a>
            <h1>Solicitações de agendamento</h1>
            <small>Pedidos enviados pelo aplicativo do cliente.</small>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($solicitacaoEditar): ?>
        <?php
        // Armazena a data "dataHoraEditar" usada nas validações e consultas desta página.
        $dataHoraEditar = new DateTime($solicitacaoEditar['data_hora_inicio']);
        ?>
        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form class="reschedule-card" method="post"
            action="solicitacoes-agendamento.php?aba=<?= escapar($aba) ?>">
            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="acao" value="reagendar">
            <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="agendamento_id"
                value="<?= (int) $solicitacaoEditar['id'] ?>">

            <div>
                <h2>Reagendar <?= escapar($solicitacaoEditar['pet']) ?></h2>
                <small>Tutor: <?= escapar($solicitacaoEditar['tutor']) ?></small>
            </div>

            <div class="field">
                <!-- Rótulo do campo "Nova data", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="novaData">Nova data</label>
                <!-- Campo "data" utilizado para enviar esta informação no formulário. -->
                <input id="novaData" name="data" type="date"
                    value="<?= escapar($dataHoraEditar->format('Y-m-d')) ?>"
                    required>
            </div>

            <div class="field">
                <!-- Rótulo do campo "Novo horário", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="novaHora">Novo horário</label>
                <!-- Campo "hora" utilizado para enviar esta informação no formulário. -->
                <input id="novaHora" name="hora" type="time"
                    value="<?= escapar($dataHoraEditar->format('H:i')) ?>"
                    required>
            </div>

            <button class="primary-action-button" type="submit">
                Salvar e confirmar
            </button>

            <a class="secondary-action-button"
                href="solicitacoes-agendamento.php?aba=<?= escapar($aba) ?>">
                Cancelar
            </a>
        </form>
    <?php endif; ?>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="requests-card">
        <nav class="request-tabs">
            <a href="solicitacoes-agendamento.php?aba=pendentes"
                class="<?= $aba === 'pendentes' ? 'active' : '' ?>">
                Solicitações pendentes
                <span><?= $totalPendentes ?></span>
            </a>

            <a href="solicitacoes-agendamento.php?aba=confirmadas"
                class="<?= $aba === 'confirmadas' ? 'active' : '' ?>">
                Solicitações confirmadas
                <span><?= $totalConfirmadas ?></span>
            </a>
        </nav>

        <?php if (!$solicitacoes): ?>
            <div class="agenda-empty">
                Nenhuma solicitação nesta categoria.
            </div>
        <?php else: ?>
            <div class="request-list">
                <?php foreach ($solicitacoes as $item): ?>
                    <article class="request-item">
                        <div class="agenda-pet">
                            <?php $fotoPet = urlFotoPet($item['foto_url'] ?? null); ?>
                            <?php if ($fotoPet): ?>
                                <img src="<?= escapar($fotoPet) ?>"
                                    alt="Foto de <?= escapar($item['pet']) ?>">
                            <?php else: ?>
                                <span class="agenda-pet-fallback">
                                    <i class="fa-solid fa-paw"></i>
                                </span>
                            <?php endif; ?>

                            <div>
                                <strong><?= escapar($item['pet']) ?></strong>
                                <small><?= escapar($item['tutor']) ?></small>
                            </div>
                        </div>

                        <div class="request-info">
                            <small>Data e horário</small>
                            <strong><?= escapar(formatarDataHoraAgenda($item['data_hora_inicio'])) ?></strong>
                        </div>

                        <div class="request-info">
                            <small>Serviço</small>
                            <strong><?= escapar($item['servicos'] ?: 'Não informado') ?></strong>
                        </div>

                        <?php if ($aba === 'pendentes'): ?>
                            <div class="request-actions">
                                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                                <form method="post"
                                    action="solicitacoes-agendamento.php?aba=pendentes">
                                    <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="acao" value="confirmar">
                                    <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="agendamento_id"
                                        value="<?= (int) $item['id'] ?>">
                                    <button class="request-button confirm" type="submit">
                                        <i class="fa-solid fa-check"></i>
                                        Confirmar
                                    </button>
                                </form>

                                <a class="request-button reschedule"
                                    href="solicitacoes-agendamento.php?aba=pendentes&editar=<?= (int) $item['id'] ?>">
                                    <i class="fa-regular fa-calendar"></i>
                                    Reagendar
                                </a>

                                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                                <form method="post"
                                    action="solicitacoes-agendamento.php?aba=pendentes">
                                    <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="acao" value="recusar">
                                    <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="agendamento_id"
                                        value="<?= (int) $item['id'] ?>">
                                    <button class="icon-action-button danger"
                                        type="submit" title="Recusar">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="request-actions">
                                <span class="status-badge success">Confirmado</span>

                                <a class="request-button reschedule"
                                    href="solicitacoes-agendamento.php?aba=confirmadas&editar=<?= (int) $item['id'] ?>">
                                    <i class="fa-solid fa-pen"></i>
                                    Editar
                                </a>

                                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                                <form method="post"
                                    action="solicitacoes-agendamento.php?aba=confirmadas">
                                    <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="acao" value="cancelar">
                                    <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="agendamento_id"
                                        value="<?= (int) $item['id'] ?>">
                                    <button class="icon-action-button danger"
                                        type="submit" title="Cancelar">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>