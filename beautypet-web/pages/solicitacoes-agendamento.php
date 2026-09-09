<?php

require __DIR__ . '/config.php';

// Obtém o usuário atualmente logado
$usuarioAtualSolicitacao = obterUsuarioAtual($pdo);
$usuarioId = isset($usuarioAtualSolicitacao['id'])
    ? (int) $usuarioAtualSolicitacao['id']
    : null;

$mensagem = '';
$tipoMensagem = '';

// Define a aba atual (pendentes ou confirmadas)
$aba = (string) ($_GET['aba'] ?? 'pendentes');

if (!in_array($aba, ['pendentes', 'confirmadas'], true)) {
    $aba = 'pendentes';
}

// Processa as ações enviadas pelos formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);

    if ($agendamentoId === null) {
        $mensagem = 'A solicitação selecionada é inválida.';
        $tipoMensagem = 'error';

    // Confirma, recusa ou cancela uma solicitação
    } elseif (in_array($acao, ['confirmar', 'recusar', 'cancelar'], true)) {
        $novoStatus = match ($acao) {
            'confirmar' => 'CONFIRMADO',
            'recusar'   => 'RECUSADO',
            'cancelar'  => 'CANCELADO',
        };

        $stmt = $pdo->prepare(
            "UPDATE agendamentos
             SET status = :status,
                 respondido_por = :usuario_id,
                 respondido_em = CURRENT_TIMESTAMP
             WHERE id = :id
               AND origem = 'MOBILE'"
        );

        $stmt->execute([
            ':status'     => $novoStatus,
            ':usuario_id' => $usuarioId,
            ':id'         => $agendamentoId,
        ]);

        // Define para qual aba o usuário será redirecionado após a ação
        $abaRetorno = $acao === 'cancelar'
            ? 'confirmadas'
            : 'pendentes';

        header(
            'Location: solicitacoes-agendamento.php?aba='
            . $abaRetorno
            . '&atualizado=1'
        );
        exit;

    // Processa o reagendamento
    } elseif ($acao === 'reagendar') {
        $data = (string) ($_POST['data'] ?? '');
        $hora = (string) ($_POST['hora'] ?? '');

        // Valida a nova data e o novo horário
        if (!dataSqlValida($data) || !horaSqlValida($hora)) {
            $mensagem = 'Informe uma nova data e um novo horário válidos.';
            $tipoMensagem = 'error';

        } else {
            // Busca o agendamento atual para preservar sua duração
            $stmt = $pdo->prepare(
                "SELECT data_hora_inicio, data_hora_fim
                 FROM agendamentos
                 WHERE id = :id
                   AND origem = 'MOBILE'"
            );
            $stmt->execute([':id' => $agendamentoId]);
            $atual = $stmt->fetch();

            if (!$atual) {
                $mensagem = 'A solicitação não foi encontrada.';
                $tipoMensagem = 'error';
            } else {
                $novoInicio = new DateTime($data . ' ' . $hora . ':00');
                $novoFim = null;

                // Mantém a mesma duração do agendamento anterior
                if (!empty($atual['data_hora_fim'])) {
                    $inicioAnterior = new DateTime($atual['data_hora_inicio']);
                    $fimAnterior = new DateTime($atual['data_hora_fim']);

                    $duracao = max(
                        0,
                        $fimAnterior->getTimestamp()
                        - $inicioAnterior->getTimestamp()
                    );

                    $novoFim = (clone $novoInicio)
                        ->modify('+' . $duracao . ' seconds')
                        ->format('Y-m-d H:i:s');
                }

                // Atualiza a data, horário e confirma o agendamento
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

                $stmt->execute([
                    ':inicio'     => $novoInicio->format('Y-m-d H:i:s'),
                    ':fim'        => $novoFim,
                    ':usuario_id' => $usuarioId,
                    ':id'         => $agendamentoId,
                ]);

                header(
                    'Location: solicitacoes-agendamento.php'
                    . '?aba=confirmadas&reagendado=1'
                );
                exit;
            }
        }
    }
}

// Exibe mensagem após confirmar, recusar ou cancelar
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['atualizado'])) {
    $mensagem = 'Solicitação atualizada com sucesso.';
    $tipoMensagem = 'success';
}

// Exibe mensagem após um reagendamento
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reagendado'])) {
    $mensagem = 'Novo horário salvo e agendamento confirmado.';
    $tipoMensagem = 'success';
}

// Obtém a quantidade de solicitações pendentes e confirmadas
$totais = $pdo->query(
    "SELECT
        SUM(status = 'SOLICITADO') AS pendentes,
        SUM(status = 'CONFIRMADO') AS confirmadas
     FROM agendamentos
     WHERE origem = 'MOBILE'"
)->fetch();

$totalPendentes = (int) ($totais['pendentes'] ?? 0);
$totalConfirmadas = (int) ($totais['confirmadas'] ?? 0);

// Define qual status será usado na consulta da aba atual
$statusConsulta = $aba === 'pendentes'
    ? 'SOLICITADO'
    : 'CONFIRMADO';

// Busca os agendamentos da aba selecionada
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
        GROUP_CONCAT(
            DISTINCT s.nome
            ORDER BY s.nome
            SEPARATOR ', '
        ) AS servicos
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
$stmt->execute([':status' => $statusConsulta]);
$solicitacoes = $stmt->fetchAll();

// Verifica se existe uma solicitação selecionada para edição/reagendamento
$editarId = inteiroOuNull($_GET['editar'] ?? null);
$solicitacaoEditar = null;

if ($editarId !== null) {
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
    $stmt->execute([':id' => $editarId]);
    $solicitacaoEditar = $stmt->fetch() ?: null;
}

// Configurações utilizadas pelo layout da página
$tituloPagina = 'Solicitações de agendamento';
$paginaAtiva = 'solicitacoes-agendamento';

require __DIR__ . '/includes/topo.php';
?>

<div class="page requests-page">
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
        // Converte a data atual para preencher os campos do formulário
        $dataHoraEditar = new DateTime($solicitacaoEditar['data_hora_inicio']);
        ?>
        <form class="reschedule-card" method="post"
            action="solicitacoes-agendamento.php?aba=<?= escapar($aba) ?>">
            <input type="hidden" name="acao" value="reagendar">
            <input type="hidden" name="agendamento_id"
                value="<?= (int) $solicitacaoEditar['id'] ?>">

            <div>
                <h2>Reagendar <?= escapar($solicitacaoEditar['pet']) ?></h2>
                <small>Tutor: <?= escapar($solicitacaoEditar['tutor']) ?></small>
            </div>

            <div class="field">
                <label for="novaData">Nova data</label>
                <input id="novaData" name="data" type="date"
                    value="<?= escapar($dataHoraEditar->format('Y-m-d')) ?>"
                    required>
            </div>

            <div class="field">
                <label for="novaHora">Novo horário</label>
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

    <section class="requests-card">
        <!-- Abas para alternar entre solicitações pendentes e confirmadas -->
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

            <!-- Lista de solicitações encontradas -->
            <div class="request-list">
                <?php foreach ($solicitacoes as $item): ?>
                    <article class="request-item">

                        <!-- Informações do pet e do tutor -->
                        <div class="agenda-pet">
                            <?php if (!empty($item['foto_url'])): ?>
                                <img src="<?= escapar($item['foto_url']) ?>"
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

                        <!-- Data e horário solicitados -->
                        <div class="request-info">
                            <small>Data e horário</small>
                            <strong><?= escapar(formatarDataHoraAgenda($item['data_hora_inicio'])) ?></strong>
                        </div>

                        <!-- Serviços selecionados pelo cliente -->
                        <div class="request-info">
                            <small>Serviço</small>
                            <strong><?= escapar($item['servicos'] ?: 'Não informado') ?></strong>
                        </div>

                        <?php if ($aba === 'pendentes'): ?>

                            <!-- Ações disponíveis para uma solicitação pendente -->
                            <div class="request-actions">
                                <form method="post"
                                    action="solicitacoes-agendamento.php?aba=pendentes">
                                    <input type="hidden" name="acao" value="confirmar">
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

                                <form method="post"
                                    action="solicitacoes-agendamento.php?aba=pendentes">
                                    <input type="hidden" name="acao" value="recusar">
                                    <input type="hidden" name="agendamento_id"
                                        value="<?= (int) $item['id'] ?>">

                                    <button class="icon-action-button danger"
                                        type="submit" title="Recusar">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>

                        <?php else: ?>

                            <!-- Ações disponíveis para um agendamento confirmado -->
                            <div class="request-actions">
                                <span class="status-badge success">Confirmado</span>

                                <a class="request-button reschedule"
                                    href="solicitacoes-agendamento.php?aba=confirmadas&editar=<?= (int) $item['id'] ?>">
                                    <i class="fa-solid fa-pen"></i>
                                    Editar
                                </a>

                                <form method="post"
                                    action="solicitacoes-agendamento.php?aba=confirmadas">
                                    <input type="hidden" name="acao" value="cancelar">
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

<?php require __DIR__ . '/includes/rodape.php'; ?>
