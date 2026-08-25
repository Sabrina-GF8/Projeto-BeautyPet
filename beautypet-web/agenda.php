<?php
require __DIR__ . '/config.php';
$usuarioAtual = obterUsuarioAtual($pdo);
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;
$administrador = ($usuarioAtual['perfil'] ?? '') === 'ADMINISTRADOR';

$mensagem = '';
$tipoMensagem = '';
$dataSelecionada = (string) ($_GET['data'] ?? date('Y-m-d'));
$editarId = inteiroOuNull($_GET['editar'] ?? null);

function renderizarAcoesAgenda(array $item, string $dataSelecionada): void
{
    ?>
    <div class="agenda-row-actions">
        <a
            class="icon-action-button edit"
            href="agenda.php?data=<?= escapar($dataSelecionada) ?>&editar=<?= (int) $item['id'] ?>"
            title="Editar agendamento">
            <i class="fa-solid fa-pen"></i>
        </a>

        <?php if ($item['status'] === 'CONFIRMADO'): ?>
            <form method="post" action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
                <input type="hidden" name="acao" value="alterar_status">
                <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="status" value="EM_ATENDIMENTO">
                <button class="small-action-button start" type="submit">Iniciar</button>
            </form>
        <?php elseif ($item['status'] === 'EM_ATENDIMENTO'): ?>
            <form method="post" action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
                <input type="hidden" name="acao" value="alterar_status">
                <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="status" value="CONCLUIDO">
                <button class="small-action-button finish" type="submit">Concluir</button>
            </form>
        <?php endif; ?>

        <form
            method="post"
            action="agenda.php?data=<?= escapar($dataSelecionada) ?>"
            onsubmit="return confirm('Excluir este agendamento definitivamente?');">
            <input type="hidden" name="acao" value="excluir_agendamento">
            <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
            <button class="icon-action-button danger" type="submit" title="Excluir">
                <i class="fa-solid fa-trash"></i>
            </button>
        </form>
    </div>
    <?php
}

if (!dataSqlValida($dataSelecionada)) {
    $dataSelecionada = date('Y-m-d');
}

$agendamentoEdicao = $editarId !== null
    ? buscarAgendamentoEdicao($pdo, $editarId)
    : null;

if ($editarId !== null && $agendamentoEdicao === null) {
    $mensagem = 'O agendamento selecionado não foi encontrado.';
    $tipoMensagem = 'error';
    $editarId = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    if (in_array($acao, ['criar_agendamento', 'editar_agendamento'], true)) {
        $agendamentoId = $acao === 'editar_agendamento'
            ? inteiroOuNull($_POST['agendamento_id'] ?? null)
            : null;

        $petId = inteiroOuNull($_POST['pet_id'] ?? null);
        $servicoId = inteiroOuNull($_POST['servico_id'] ?? null);
        $horaManual = (string) ($_POST['hora'] ?? '');
        $horaBanho = (string) ($_POST['hora_banho'] ?? '');
        $duracaoMinutos = inteiroOuNull($_POST['duracao_minutos'] ?? null);
        $dataFim = textoOuNull($_POST['data_fim'] ?? null);
        $horaFim = textoOuNull($_POST['hora_fim'] ?? null);
        $observacoes = textoOuNull($_POST['observacoes'] ?? null);

        $servico = $servicoId !== null
            ? buscarServicoAgenda($pdo, $servicoId)
            : null;

        if ($petId === null || $servico === null) {
            $mensagem = 'Selecione o pet e o serviço.';
            $tipoMensagem = 'error';
        } else {
            $banhoTosa = ehBanhoTosa($servico['categoria'], $servico['nome']);
            $horaInicio = null;
            $fim = null;

            if ($banhoTosa) {
                $padrao = buscarPadraoBanhoDaData($pdo, $dataSelecionada);
                $horariosPadrao = $padrao
                    ? buscarHorariosPadraoBanho($pdo, (int) $padrao['id'])
                    : [];

                $ocupacao = buscarOcupacaoBanhoTosa(
                    $pdo,
                    $dataSelecionada,
                    $horariosPadrao,
                    $agendamentoId
                );

                if ($padrao === null || !$horariosPadrao) {
                    $mensagem = 'Não existe agenda-padrão de banho e tosa para esta data.';
                    $tipoMensagem = 'error';
                } elseif ($duracaoMinutos === null) {
                    $mensagem = 'Selecione a duração do banho e tosa.';
                    $tipoMensagem = 'error';
                } else {
                    [$disponivel, $erroVaga] = validarVagasBanhoTosa(
                        $horariosPadrao,
                        $ocupacao,
                        $horaBanho,
                        $duracaoMinutos
                    );

                    if (!$disponivel) {
                        $mensagem = $erroVaga;
                        $tipoMensagem = 'error';
                    } else {
                        $horaInicio = $horaBanho;
                        $inicioTemporario = new DateTime(
                            $dataSelecionada . ' ' . $horaBanho . ':00'
                        );
                        $fim = (clone $inicioTemporario)
                            ->modify('+' . $duracaoMinutos . ' minutes')
                            ->format('Y-m-d H:i:s');
                    }
                }
            } else {
                if (!horaSqlValida($horaManual)) {
                    $mensagem = 'Informe um horário válido.';
                    $tipoMensagem = 'error';
                } else {
                    $horaInicio = $horaManual;
                }

                if (
                    $mensagem === ''
                    && (
                        ($dataFim !== null && !dataSqlValida($dataFim))
                        || ($horaFim !== null && !horaSqlValida($horaFim))
                        || (($dataFim === null) xor ($horaFim === null))
                    )
                ) {
                    $mensagem = 'Para informar o término, preencha data e horário.';
                    $tipoMensagem = 'error';
                }

                if ($mensagem === '' && $dataFim !== null && $horaFim !== null) {
                    $fim = $dataFim . ' ' . $horaFim . ':00';
                }
            }

            if ($mensagem === '' && $horaInicio !== null) {
                $inicio = $dataSelecionada . ' ' . $horaInicio . ':00';

                if ($fim !== null && strtotime($fim) <= strtotime($inicio)) {
                    $mensagem = 'O término deve ser posterior ao início.';
                    $tipoMensagem = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'SELECT COUNT(*)
                             FROM pets
                             WHERE id = :id
                               AND ativo = TRUE'
                        );
                        $stmt->execute([':id' => $petId]);

                        if ((int) $stmt->fetchColumn() === 0) {
                            throw new RuntimeException(
                                'O pet selecionado não foi encontrado.'
                            );
                        }

                        $pdo->beginTransaction();

                        if ($acao === 'criar_agendamento') {
                            $stmt = $pdo->prepare(
                                'INSERT INTO agendamentos (
                                    pet_id,
                                    data_hora_inicio,
                                    data_hora_fim,
                                    origem,
                                    status,
                                    observacoes_internas,
                                    criado_por
                                 ) VALUES (
                                    :pet_id,
                                    :inicio,
                                    :fim,
                                    :origem,
                                    :status,
                                    :observacoes,
                                    :criado_por
                                 )'
                            );

                            $stmt->execute([
                                ':pet_id'      => $petId,
                                ':inicio'      => $inicio,
                                ':fim'         => $fim,
                                ':origem'      => 'INTERNO',
                                ':status'      => 'CONFIRMADO',
                                ':observacoes' => $observacoes,
                                ':criado_por'  => $usuarioId,
                            ]);

                            $agendamentoId = (int) $pdo->lastInsertId();
                        } else {
                            if ($agendamentoId === null) {
                                throw new RuntimeException(
                                    'Agendamento inválido para edição.'
                                );
                            }

                            $stmt = $pdo->prepare(
                                'UPDATE agendamentos
                                 SET pet_id = :pet_id,
                                     data_hora_inicio = :inicio,
                                     data_hora_fim = :fim,
                                     observacoes_internas = :observacoes
                                 WHERE id = :id'
                            );

                            $stmt->execute([
                                ':pet_id'      => $petId,
                                ':inicio'      => $inicio,
                                ':fim'         => $fim,
                                ':observacoes' => $observacoes,
                                ':id'          => $agendamentoId,
                            ]);

                            $pdo->prepare(
                                'DELETE FROM agendamento_itens
                                 WHERE agendamento_id = :id'
                            )->execute([':id' => $agendamentoId]);
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO agendamento_itens (
                                agendamento_id,
                                servico_id,
                                observacoes
                             ) VALUES (
                                :agendamento_id,
                                :servico_id,
                                :observacoes
                             )'
                        );

                        $stmt->execute([
                            ':agendamento_id' => $agendamentoId,
                            ':servico_id'      => $servicoId,
                            ':observacoes'     => $observacoes,
                        ]);

                        $pdo->commit();

                        $parametro = $acao === 'criar_agendamento'
                            ? 'salvo=1'
                            : 'editado=1';

                        header(
                            'Location: agenda.php?data='
                            . urlencode($dataSelecionada)
                            . '&'
                            . $parametro
                        );
                        exit;
                    } catch (Throwable $erro) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $mensagem = $erro instanceof RuntimeException
                            ? $erro->getMessage()
                            : 'Não foi possível salvar o agendamento.';
                        $tipoMensagem = 'error';
                    }
                }
            }
        }
    }

    if ($acao === 'alterar_status') {
        $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);
        $novoStatus = (string) ($_POST['status'] ?? '');
        $statusPermitidos = ['EM_ATENDIMENTO', 'CONCLUIDO', 'CANCELADO'];

        if (
            $agendamentoId === null
            || !in_array($novoStatus, $statusPermitidos, true)
        ) {
            $mensagem = 'Não foi possível atualizar esse agendamento.';
            $tipoMensagem = 'error';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE agendamentos
                 SET status = :status,
                     respondido_por = :usuario_id,
                     respondido_em = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );

            $stmt->execute([
                ':status'     => $novoStatus,
                ':usuario_id' => $usuarioId,
                ':id'         => $agendamentoId,
            ]);

            header(
                'Location: agenda.php?data='
                . urlencode($dataSelecionada)
                . '&atualizado=1'
            );
            exit;
        }
    }

    if ($acao === 'excluir_agendamento') {
        $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);

        if ($agendamentoId === null) {
            $mensagem = 'Agendamento inválido para exclusão.';
            $tipoMensagem = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->prepare(
                    'DELETE FROM agendamento_itens
                     WHERE agendamento_id = :id'
                )->execute([':id' => $agendamentoId]);

                $pdo->prepare(
                    'DELETE FROM agendamentos
                     WHERE id = :id'
                )->execute([':id' => $agendamentoId]);

                $pdo->commit();

                header(
                    'Location: agenda.php?data='
                    . urlencode($dataSelecionada)
                    . '&excluido=1'
                );
                exit;
            } catch (Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $mensagem = 'Não foi possível excluir o agendamento.';
                $tipoMensagem = 'error';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['salvo'])) {
        $mensagem = 'Agendamento cadastrado com sucesso!';
        $tipoMensagem = 'success';
    } elseif (isset($_GET['editado'])) {
        $mensagem = 'Agendamento atualizado com sucesso!';
        $tipoMensagem = 'success';
    } elseif (isset($_GET['excluido'])) {
        $mensagem = 'Agendamento excluído com sucesso!';
        $tipoMensagem = 'success';
    } elseif (isset($_GET['atualizado'])) {
        $mensagem = 'Status do agendamento atualizado.';
        $tipoMensagem = 'success';
    } elseif (isset($_GET['sem_permissao'])) {
        $mensagem = 'Somente administradores podem configurar a agenda-padrão.';
        $tipoMensagem = 'error';
    }
}

$pets = $pdo->query(
    'SELECT p.id, p.nome, c.nome AS tutor
     FROM pets p
     JOIN clientes c
       ON c.id = p.cliente_id
     WHERE p.ativo = TRUE
       AND c.ativo = TRUE
     ORDER BY p.nome, c.nome'
)->fetchAll();

$servicos = $pdo->query(
    "SELECT
        s.id,
        s.nome,
        cs.nome AS categoria
     FROM servicos s
     JOIN categorias_servico cs
       ON cs.id = s.categoria_id
     WHERE s.ativo = TRUE
       AND cs.ativo = TRUE
       AND LOWER(cs.nome) <> 'loja'
     ORDER BY cs.nome, s.nome"
)->fetchAll();

$totalSolicitacoes = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM agendamentos
     WHERE origem = 'MOBILE'
       AND status = 'SOLICITADO'"
)->fetchColumn();

$padraoBanho = buscarPadraoBanhoDaData($pdo, $dataSelecionada);
$horariosPadraoBanho = $padraoBanho
    ? buscarHorariosPadraoBanho($pdo, (int) $padraoBanho['id'])
    : [];

$ocupacaoBanho = buscarOcupacaoBanhoTosa(
    $pdo,
    $dataSelecionada,
    $horariosPadraoBanho,
    $editarId
);

$slotsBanhoJs = [];

foreach ($horariosPadraoBanho as $hora => $capacidade) {
    $ocupadas = (int) ($ocupacaoBanho[$hora] ?? 0);

    $slotsBanhoJs[] = [
        'hora'       => $hora,
        'capacidade' => $capacidade,
        'ocupadas'   => $ocupadas,
        'restantes'  => max(0, $capacidade - $ocupadas),
    ];
}

$stmt = $pdo->prepare(
    "SELECT
        a.id,
        a.data_hora_inicio,
        a.data_hora_fim,
        a.status,
        a.origem,
        a.observacoes_cliente,
        a.observacoes_internas,
        p.id AS pet_id,
        p.nome AS pet,
        p.foto_url,
        c.nome AS tutor,
        c.logradouro,
        c.numero,
        c.bairro,
        c.cidade,
        c.estado,
        uc.nome AS registrado_por,
        GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS servicos,
        GROUP_CONCAT(DISTINCT cs.nome ORDER BY cs.nome SEPARATOR ', ') AS categorias
     FROM agendamentos a
     JOIN pets p
       ON p.id = a.pet_id
     JOIN clientes c
       ON c.id = p.cliente_id
     LEFT JOIN usuarios uc
       ON uc.id = a.criado_por
     LEFT JOIN agendamento_itens ai
       ON ai.agendamento_id = a.id
     LEFT JOIN servicos s
       ON s.id = ai.servico_id
     LEFT JOIN categorias_servico cs
       ON cs.id = s.categoria_id
     WHERE a.status IN ('CONFIRMADO', 'EM_ATENDIMENTO', 'CONCLUIDO')
       AND (
            DATE(a.data_hora_inicio) = :data_inicio
            OR (
                a.data_hora_fim IS NOT NULL
                AND :data_intervalo BETWEEN
                    DATE(a.data_hora_inicio)
                    AND DATE(a.data_hora_fim)
            )
       )
     GROUP BY
        a.id,
        a.data_hora_inicio,
        a.data_hora_fim,
        a.status,
        a.origem,
        a.observacoes_cliente,
        a.observacoes_internas,
        p.id,
        p.nome,
        p.foto_url,
        c.nome,
        c.logradouro,
        c.numero,
        c.bairro,
        c.cidade,
        c.estado,
        uc.nome
     ORDER BY a.data_hora_inicio, p.nome"
);

$stmt->execute([
    ':data_inicio'    => $dataSelecionada,
    ':data_intervalo' => $dataSelecionada,
]);

$agendamentos = $stmt->fetchAll();

$grupos = [
    'creche'      => [],
    'hotel'       => [],
    'banho_tosa'  => [],
    'veterinario' => [],
    'taxidog'     => [],
];

foreach ($agendamentos as $agendamento) {
    $identificacao = normalizarCategoriaAgenda(
        ($agendamento['categorias'] ?? '')
        . ' '
        . ($agendamento['servicos'] ?? '')
    );

    if (str_contains($identificacao, 'loja')) {
        continue;
    }

    if (str_contains($identificacao, 'hotel')) {
        $grupos['hotel'][] = $agendamento;
    } elseif (str_contains($identificacao, 'creche')) {
        $grupos['creche'][] = $agendamento;
    } elseif (
        str_contains($identificacao, 'taxi')
        || str_contains($identificacao, 'taxidog')
    ) {
        $grupos['taxidog'][] = $agendamento;
    } elseif (
        str_contains($identificacao, 'veterin')
        || str_contains($identificacao, 'consulta')
    ) {
        $grupos['veterinario'][] = $agendamento;
    } elseif (ehBanhoTosa($identificacao)) {
        $grupos['banho_tosa'][] = $agendamento;
    }
}

$dataAnterior = (new DateTime($dataSelecionada))
    ->modify('-1 day')
    ->format('Y-m-d');

$dataSeguinte = (new DateTime($dataSelecionada))
    ->modify('+1 day')
    ->format('Y-m-d');

$mostrarFormulario = isset($_GET['novo'])
    || $editarId !== null
    || ($mensagem !== '' && $tipoMensagem === 'error');

$dadosFormulario = $agendamentoEdicao ?? [];

$servicoSelecionado = (string) (
    $_POST['servico_id']
    ?? $dadosFormulario['servico_id']
    ?? ''
);

$petSelecionado = (string) (
    $_POST['pet_id']
    ?? $dadosFormulario['pet_id']
    ?? ''
);

$horaManualSelecionada = (string) (
    $_POST['hora']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
            ? substr((string) $dadosFormulario['data_hora_inicio'], 11, 5)
            : ''
    )
);

$horaBanhoSelecionada = (string) (
    $_POST['hora_banho']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
            ? substr((string) $dadosFormulario['data_hora_inicio'], 11, 5)
            : ''
    )
);

$duracaoSelecionada = (int) (
    $_POST['duracao_minutos']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
        && !empty($dadosFormulario['data_hora_fim'])
            ? max(
                30,
                (int) (
                    (
                        strtotime((string) $dadosFormulario['data_hora_fim'])
                        - strtotime((string) $dadosFormulario['data_hora_inicio'])
                    ) / 60
                )
            )
            : 60
    )
);

$dataFimSelecionada = (string) (
    $_POST['data_fim']
    ?? (
        !empty($dadosFormulario['data_hora_fim'])
            ? substr((string) $dadosFormulario['data_hora_fim'], 0, 10)
            : ''
    )
);

$horaFimSelecionada = (string) (
    $_POST['hora_fim']
    ?? (
        !empty($dadosFormulario['data_hora_fim'])
            ? substr((string) $dadosFormulario['data_hora_fim'], 11, 5)
            : ''
    )
);

$observacoesSelecionadas = (string) (
    $_POST['observacoes']
    ?? $dadosFormulario['observacoes_internas']
    ?? ''
);

$tituloPagina = 'Agenda';
$paginaAtiva = 'agenda';
require __DIR__ . '/includes/topo.php';
?>

<div class="page agenda-page">
    <div class="page-heading agenda-heading">
        <div>
            <h1>Agenda</h1>
            <small>Organize os serviços confirmados do Beauty Pet.</small>
        </div>

        <div class="agenda-heading-actions">
            <?php if ($administrador): ?>
                <a class="secondary-action-button" href="agenda-padrao.php">
                    <i class="fa-solid fa-clock"></i>
                    Agenda-padrão
                </a>
            <?php endif; ?>

            <a class="secondary-action-button" href="solicitacoes-agendamento.php">
                <i class="fa-solid fa-calendar-check"></i>
                Solicitações
                <?php if ($totalSolicitacoes > 0): ?>
                    <span class="request-count"><?= $totalSolicitacoes ?></span>
                <?php endif; ?>
            </a>

            <a
                class="primary-action-button"
                href="agenda.php?data=<?= escapar($dataSelecionada) ?>&novo=1">
                <i class="fa-solid fa-plus"></i>
                Novo agendamento
            </a>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <div class="agenda-date-bar">
        <a href="agenda.php?data=<?= escapar($dataAnterior) ?>" title="Dia anterior">
            <i class="fa-solid fa-chevron-left"></i>
        </a>

        <form method="get" action="agenda.php">
            <label for="dataAgenda">Data da agenda</label>
            <input id="dataAgenda" name="data" type="date"
                value="<?= escapar($dataSelecionada) ?>">
            <button type="submit">Abrir</button>
        </form>

        <a href="agenda.php?data=<?= escapar($dataSeguinte) ?>" title="Dia seguinte">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>

    <?php if ($mostrarFormulario): ?>
        <form
            class="agenda-form-card"
            method="post"
            action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
            <input
                type="hidden"
                name="acao"
                value="<?= $editarId !== null ? 'editar_agendamento' : 'criar_agendamento' ?>">

            <?php if ($editarId !== null): ?>
                <input type="hidden" name="agendamento_id" value="<?= $editarId ?>">
            <?php endif; ?>

            <div class="agenda-card-title">
                <div>
                    <h2><?= $editarId !== null ? 'Editar agendamento' : 'Novo agendamento' ?></h2>
                    <small>
                        Banho e tosa usa vagas de 30 em 30 minutos.
                    </small>
                </div>

                <a href="agenda.php?data=<?= escapar($dataSelecionada) ?>" aria-label="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>

            <div class="form-grid">
                <div class="field span-6">
                    <label for="petAgenda">Pet</label>
                    <select id="petAgenda" name="pet_id" required>
                        <option value="">Selecione o pet</option>

                        <?php foreach ($pets as $pet): ?>
                            <option
                                value="<?= (int) $pet['id'] ?>"
                                <?= $petSelecionado === (string) $pet['id'] ? 'selected' : '' ?>>
                                <?= escapar($pet['nome']) ?> — <?= escapar($pet['tutor']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field span-6">
                    <label for="servicoAgenda">Serviço</label>
                    <select id="servicoAgenda" name="servico_id" required>
                        <option value="">Selecione o serviço</option>
                        <?php $categoriaAtual = null; ?>

                        <?php foreach ($servicos as $servico): ?>
                            <?php if ($categoriaAtual !== $servico['categoria']): ?>
                                <?php if ($categoriaAtual !== null): ?></optgroup><?php endif; ?>
                                <optgroup label="<?= escapar(nomeCategoriaAgenda($servico['categoria'])) ?>">
                                <?php $categoriaAtual = $servico['categoria']; ?>
                            <?php endif; ?>

                            <option
                                value="<?= (int) $servico['id'] ?>"
                                data-banho-tosa="<?= ehBanhoTosa($servico['categoria'], $servico['nome']) ? '1' : '0' ?>"
                                <?= $servicoSelecionado === (string) $servico['id'] ? 'selected' : '' ?>>
                                <?= escapar($servico['nome']) ?>
                            </option>
                        <?php endforeach; ?>

                        <?php if ($categoriaAtual !== null): ?></optgroup><?php endif; ?>
                    </select>
                </div>

                <div class="field span-3">
                    <label>Data</label>
                    <input type="date" value="<?= escapar($dataSelecionada) ?>" readonly>
                </div>

                <div class="field span-3 bath-schedule-field" id="duracaoBanhoBox">
                    <label for="duracaoBanho">Duração</label>
                    <select id="duracaoBanho" name="duracao_minutos">
                        <?php for ($minutos = 30; $minutos <= 480; $minutos += 30): ?>
                            <option
                                value="<?= $minutos ?>"
                                <?= $duracaoSelecionada === $minutos ? 'selected' : '' ?>>
                                <?= escapar(formatarDuracaoAgenda($minutos)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <small class="field-help">A duração avança de 30 em 30 minutos.</small>
                </div>

                <div class="field span-6 bath-schedule-field" id="horaBanhoBox">
                    <label for="horaBanho">Horário disponível</label>
                    <select
                        id="horaBanho"
                        name="hora_banho"
                        data-selected="<?= escapar($horaBanhoSelecionada) ?>">
                        <option value="">Selecione a duração primeiro</option>
                    </select>

                    <?php if (!$horariosPadraoBanho): ?>
                        <small class="field-help warning-text">
                            Não existe agenda-padrão para esta data.
                            <?php if ($administrador): ?>
                                <a href="agenda-padrao.php">Cadastrar agenda-padrão</a>
                            <?php endif; ?>
                        </small>
                    <?php else: ?>
                        <small class="field-help">
                            O sistema verifica todas as faixas de 30 minutos usadas pelo serviço.
                        </small>
                    <?php endif; ?>
                </div>

                <div class="field span-3 manual-schedule-field" id="horaManualBox">
                    <label for="horaInicio">Horário</label>
                    <input
                        id="horaInicio"
                        name="hora"
                        type="time"
                        value="<?= escapar($horaManualSelecionada) ?>">
                </div>

                <div class="field span-3 manual-schedule-field">
                    <label for="dataFim">Término — data</label>
                    <input
                        id="dataFim"
                        name="data_fim"
                        type="date"
                        value="<?= escapar($dataFimSelecionada) ?>">
                </div>

                <div class="field span-3 manual-schedule-field">
                    <label for="horaFim">Término — horário</label>
                    <input
                        id="horaFim"
                        name="hora_fim"
                        type="time"
                        value="<?= escapar($horaFimSelecionada) ?>">
                </div>

                <div class="field span-12">
                    <label for="observacoesAgenda">Observações</label>
                    <textarea
                        id="observacoesAgenda"
                        name="observacoes"
                        placeholder="Orientações ou observações do serviço"><?= escapar($observacoesSelecionadas) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button class="save-button" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $editarId !== null ? 'Salvar alterações' : 'Salvar agendamento' ?>
                </button>
            </div>
        </form>
    <?php endif; ?>

    <?php
    $compactos = [
        [
            'titulo' => 'Creche',
            'icone'  => 'fa-solid fa-house',
            'itens'  => $grupos['creche'],
            'vazio'  => 'Nenhum pet na creche nesta data.',
            'entrada'=> 'Entrada',
            'saida'  => 'Saída',
            'dataCompleta' => false,
        ],
        [
            'titulo' => 'Hotel',
            'icone'  => 'fa-solid fa-hotel',
            'itens'  => $grupos['hotel'],
            'vazio'  => 'Nenhuma hospedagem nesta data.',
            'entrada'=> 'Check-in',
            'saida'  => 'Check-out',
            'dataCompleta' => true,
        ],
    ];
    ?>

    <?php foreach ($compactos as $grupo): ?>
        <section class="agenda-section">
            <div class="agenda-section-title">
                <div>
                    <i class="<?= escapar($grupo['icone']) ?>"></i>
                    <h2><?= escapar($grupo['titulo']) ?></h2>
                </div>
                <span><?= count($grupo['itens']) ?> agendamento(s)</span>
            </div>

            <?php if (!$grupo['itens']): ?>
                <div class="agenda-empty"><?= escapar($grupo['vazio']) ?></div>
            <?php else: ?>
                <div class="agenda-compact-list">
                    <?php foreach ($grupo['itens'] as $item): ?>
                        <article class="agenda-compact-item has-actions">
                            <div class="agenda-pet">
                                <?php if (!empty($item['foto_url'])): ?>
                                    <img src="<?= escapar($item['foto_url']) ?>" alt="">
                                <?php else: ?>
                                    <span class="agenda-pet-fallback">
                                        <i class="fa-solid fa-paw"></i>
                                    </span>
                                <?php endif; ?>

                                <div>
                                    <strong><?= escapar($item['pet']) ?></strong>
                                    <small>Tutor: <?= escapar($item['tutor']) ?></small>
                                </div>
                            </div>

                            <div class="agenda-times">
                                <span>
                                    <?= escapar($grupo['entrada']) ?>:
                                    <strong>
                                        <?= escapar(
                                            $grupo['dataCompleta']
                                                ? formatarDataHoraAgenda($item['data_hora_inicio'])
                                                : formatarHoraAgenda($item['data_hora_inicio'])
                                        ) ?>
                                    </strong>
                                </span>
                                <span>
                                    <?= escapar($grupo['saida']) ?>:
                                    <strong>
                                        <?= escapar(
                                            $grupo['dataCompleta']
                                                ? formatarDataHoraAgenda($item['data_hora_fim'])
                                                : formatarHoraAgenda($item['data_hora_fim'])
                                        ) ?>
                                    </strong>
                                </span>
                            </div>

                            <span class="status-badge <?= escapar(classeStatusAgendamento($item['status'])) ?>">
                                <?= escapar(nomeStatusAgendamento($item['status'])) ?>
                            </span>

                            <?php renderizarAcoesAgenda($item, $dataSelecionada); ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php
    $tabelas = [
        [
            'titulo' => 'Banho e tosa',
            'icone'  => 'fa-solid fa-scissors',
            'itens'  => $grupos['banho_tosa'],
            'vazio'  => 'Nenhum banho ou tosa agendado nesta data.',
            'tipo'   => 'banho',
        ],
        [
            'titulo' => 'Consulta veterinária',
            'icone'  => 'fa-solid fa-stethoscope',
            'itens'  => $grupos['veterinario'],
            'vazio'  => 'Nenhuma consulta veterinária agendada nesta data.',
            'tipo'   => 'consulta',
        ],
        [
            'titulo' => 'Taxidog',
            'icone'  => 'fa-solid fa-car-side',
            'itens'  => $grupos['taxidog'],
            'vazio'  => 'Nenhum Taxidog agendado nesta data.',
            'tipo'   => 'taxidog',
        ],
    ];
    ?>

    <?php foreach ($tabelas as $grupo): ?>
        <section class="agenda-section">
            <div class="agenda-section-title">
                <div>
                    <i class="<?= escapar($grupo['icone']) ?>"></i>
                    <h2><?= escapar($grupo['titulo']) ?></h2>
                </div>
                <span><?= count($grupo['itens']) ?> agendamento(s)</span>
            </div>

            <?php if (!$grupo['itens']): ?>
                <div class="agenda-empty"><?= escapar($grupo['vazio']) ?></div>
            <?php else: ?>
                <div class="agenda-table-wrap">
                    <table class="agenda-table">
                        <thead>
                            <tr>
                                <th>Pet</th>
                                <th>Tutor<?= $grupo['tipo'] === 'taxidog' ? ' e endereço' : '' ?></th>
                                <th>Horário</th>
                                <th>Serviço</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($grupo['itens'] as $item): ?>
                                <?php
                                $duracaoItem = null;

                                if (
                                    !empty($item['data_hora_inicio'])
                                    && !empty($item['data_hora_fim'])
                                ) {
                                    $duracaoItem = (int) (
                                        (
                                            strtotime((string) $item['data_hora_fim'])
                                            - strtotime((string) $item['data_hora_inicio'])
                                        ) / 60
                                    );
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="agenda-table-pet">
                                            <?php if (!empty($item['foto_url'])): ?>
                                                <img src="<?= escapar($item['foto_url']) ?>" alt="">
                                            <?php else: ?>
                                                <span class="agenda-pet-fallback">
                                                    <i class="fa-solid fa-paw"></i>
                                                </span>
                                            <?php endif; ?>

                                            <strong><?= escapar($item['pet']) ?></strong>
                                        </div>
                                    </td>

                                    <td>
                                        <strong><?= escapar($item['tutor']) ?></strong>

                                        <?php if ($grupo['tipo'] === 'taxidog'): ?>
                                            <small class="agenda-address">
                                                <i class="fa-solid fa-location-dot"></i>
                                                <?= escapar(enderecoAbreviadoAgenda($item)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <strong><?= escapar(formatarHoraAgenda($item['data_hora_inicio'])) ?></strong>

                                        <?php if ($grupo['tipo'] === 'banho' && $duracaoItem !== null): ?>
                                            <small class="agenda-duration-label">
                                                até <?= escapar(formatarHoraAgenda($item['data_hora_fim'])) ?>
                                                • <?= escapar(formatarDuracaoAgenda($duracaoItem)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= escapar($item['servicos'] ?: 'Não informado') ?></td>

                                    <td>
                                        <span class="status-badge <?= escapar(classeStatusAgendamento($item['status'])) ?>">
                                            <?= escapar(nomeStatusAgendamento($item['status'])) ?>
                                        </span>
                                    </td>

                                    <td><?php renderizarAcoesAgenda($item, $dataSelecionada); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<script>
(() => {
    const slots = <?= json_encode(
        $slotsBanhoJs,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const servico = document.querySelector('#servicoAgenda');
    const duracao = document.querySelector('#duracaoBanho');
    const horaBanho = document.querySelector('#horaBanho');
    const bathFields = document.querySelectorAll('.bath-schedule-field');
    const manualFields = document.querySelectorAll('.manual-schedule-field');
    const horaManual = document.querySelector('#horaInicio');

    if (!servico) {
        return;
    }

    const minutos = (hora) => {
        const [h, m] = hora.split(':').map(Number);
        return (h * 60) + m;
    };

    const formatarDuracao = (total) => {
        const horas = Math.floor(total / 60);
        const minutosRestantes = total % 60;

        if (horas === 0) {
            return `${minutosRestantes} min`;
        }

        if (minutosRestantes === 0) {
            return `${horas}h`;
        }

        return `${horas}h${String(minutosRestantes).padStart(2, '0')}`;
    };

    const atualizarHorariosBanho = () => {
        if (!duracao || !horaBanho) {
            return;
        }

        const selecionadoAnterior =
            horaBanho.value || horaBanho.dataset.selected || '';

        const duracaoMinutos = Number(duracao.value || 30);
        const blocos = Math.ceil(duracaoMinutos / 30);
        const opcoes = [];

        slots.forEach((slot, indice) => {
            let valido = true;
            let menorRestante = Infinity;
            const inicio = minutos(slot.hora);

            for (let bloco = 0; bloco < blocos; bloco++) {
                const atual = slots[indice + bloco];
                const esperado = inicio + (bloco * 30);

                if (
                    !atual
                    || minutos(atual.hora) !== esperado
                    || Number(atual.restantes) <= 0
                ) {
                    valido = false;
                    break;
                }

                menorRestante = Math.min(
                    menorRestante,
                    Number(atual.restantes)
                );
            }

            if (valido) {
                opcoes.push({
                    hora: slot.hora,
                    vagas: menorRestante
                });
            }
        });

        horaBanho.innerHTML = '';

        const primeira = document.createElement('option');
        primeira.value = '';
        primeira.textContent = opcoes.length
            ? 'Selecione o horário'
            : 'Nenhum horário disponível';
        horaBanho.appendChild(primeira);

        opcoes.forEach((opcao) => {
            const elemento = document.createElement('option');
            elemento.value = opcao.hora;
            elemento.textContent =
                `${opcao.hora} — ${opcao.vagas} vaga(s) para ${formatarDuracao(duracaoMinutos)}`;

            if (opcao.hora === selecionadoAnterior) {
                elemento.selected = true;
            }

            horaBanho.appendChild(elemento);
        });
    };

    const atualizarTipoServico = () => {
        const option = servico.options[servico.selectedIndex];
        const banhoTosa = option?.dataset?.banhoTosa === '1';

        bathFields.forEach((campo) => {
            campo.hidden = !banhoTosa;
        });

        manualFields.forEach((campo) => {
            campo.hidden = banhoTosa;
        });

        if (duracao) {
            duracao.required = banhoTosa;
        }

        if (horaBanho) {
            horaBanho.required = banhoTosa;
        }

        if (horaManual) {
            horaManual.required = !banhoTosa;
        }

        if (banhoTosa) {
            atualizarHorariosBanho();
        }
    };

    servico.addEventListener('change', atualizarTipoServico);

    if (duracao) {
        duracao.addEventListener('change', () => {
            if (horaBanho) {
                horaBanho.dataset.selected = '';
            }

            atualizarHorariosBanho();
        });
    }

    atualizarTipoServico();
})();
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
