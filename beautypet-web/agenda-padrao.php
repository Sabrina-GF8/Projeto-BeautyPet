<?php
require __DIR__ . '/config.php';
$usuarioAtual = obterUsuarioAtual($pdo);
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;

if (($usuarioAtual['perfil'] ?? '') !== 'ADMINISTRADOR') {
    header('Location: agenda.php?sem_permissao=1');
    exit;
}

$mensagem = '';
$tipoMensagem = '';
$editarId = inteiroOuNull($_GET['editar'] ?? null);

$diasSemana = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    if (in_array($acao, ['criar_padrao', 'editar_padrao'], true)) {
        $id = $acao === 'editar_padrao'
            ? inteiroOuNull($_POST['id'] ?? null)
            : null;

        $diaSemana = inteiroOuNull($_POST['dia_semana'] ?? null);
        $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
        $horaFim = (string) ($_POST['hora_fim'] ?? '');
        $dataInicio = (string) ($_POST['data_inicio'] ?? '');
        $tipoDuracao = (string) ($_POST['tipo_duracao'] ?? '');
        $dataFim = textoOuNull($_POST['data_fim'] ?? null);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $vagasEnviadas = $_POST['vagas'] ?? [];

        $horarios = gerarHorariosDeTrinta($horaInicio, $horaFim);

        if (
            $diaSemana === null
            || !isset($diasSemana[$diaSemana])
            || !horaEmBlocoDeTrinta($horaInicio)
            || !horaEmBlocoDeTrinta($horaFim)
            || !$horarios
            || !dataSqlValida($dataInicio)
            || !in_array($tipoDuracao, ['INDETERMINADO', 'ATE_DATA'], true)
        ) {
            $mensagem = 'Preencha corretamente o dia, o período e a validade. Os horários devem terminar em :00 ou :30.';
            $tipoMensagem = 'error';
        } elseif (
            $tipoDuracao === 'ATE_DATA'
            && (
                $dataFim === null
                || !dataSqlValida($dataFim)
                || $dataFim < $dataInicio
            )
        ) {
            $mensagem = 'A data final deve ser válida e igual ou posterior à data inicial.';
            $tipoMensagem = 'error';
        } else {
            if ($tipoDuracao === 'INDETERMINADO') {
                $dataFim = null;
            }

            if (
                $ativo === 1
                && padraoSobreposto(
                    $pdo,
                    $diaSemana,
                    $dataInicio,
                    $dataFim,
                    $id
                )
            ) {
                $mensagem = 'Já existe uma agenda-padrão ativa para esse dia da semana no mesmo período.';
                $tipoMensagem = 'error';
            } else {
                try {
                    $pdo->beginTransaction();

                    if ($acao === 'criar_padrao') {
                        $stmt = $pdo->prepare(
                            'INSERT INTO agenda_padrao_banho_tosa (
                                dia_semana,
                                hora_inicio,
                                hora_fim,
                                data_inicio,
                                tipo_duracao,
                                data_fim,
                                ativo,
                                criado_por
                             ) VALUES (
                                :dia_semana,
                                :hora_inicio,
                                :hora_fim,
                                :data_inicio,
                                :tipo_duracao,
                                :data_fim,
                                :ativo,
                                :criado_por
                             )'
                        );

                        $stmt->execute([
                            ':dia_semana'    => $diaSemana,
                            ':hora_inicio'   => $horaInicio . ':00',
                            ':hora_fim'      => $horaFim . ':00',
                            ':data_inicio'   => $dataInicio,
                            ':tipo_duracao'  => $tipoDuracao,
                            ':data_fim'      => $dataFim,
                            ':ativo'         => $ativo,
                            ':criado_por'    => $usuarioId,
                        ]);

                        $id = (int) $pdo->lastInsertId();
                    } else {
                        if ($id === null) {
                            throw new RuntimeException(
                                'Agenda-padrão inválida para edição.'
                            );
                        }

                        $stmt = $pdo->prepare(
                            'UPDATE agenda_padrao_banho_tosa
                             SET dia_semana = :dia_semana,
                                 hora_inicio = :hora_inicio,
                                 hora_fim = :hora_fim,
                                 data_inicio = :data_inicio,
                                 tipo_duracao = :tipo_duracao,
                                 data_fim = :data_fim,
                                 ativo = :ativo
                             WHERE id = :id'
                        );

                        $stmt->execute([
                            ':dia_semana'   => $diaSemana,
                            ':hora_inicio'  => $horaInicio . ':00',
                            ':hora_fim'     => $horaFim . ':00',
                            ':data_inicio'  => $dataInicio,
                            ':tipo_duracao' => $tipoDuracao,
                            ':data_fim'     => $dataFim,
                            ':ativo'        => $ativo,
                            ':id'           => $id,
                        ]);

                        $pdo->prepare(
                            'DELETE FROM agenda_padrao_banho_tosa_horarios
                             WHERE agenda_padrao_id = :id'
                        )->execute([':id' => $id]);
                    }

                    $stmtHorario = $pdo->prepare(
                        'INSERT INTO agenda_padrao_banho_tosa_horarios (
                            agenda_padrao_id,
                            horario,
                            quantidade_vagas
                         ) VALUES (
                            :agenda_padrao_id,
                            :horario,
                            :quantidade_vagas
                         )'
                    );

                    foreach ($horarios as $horario) {
                        $quantidade = contarVagasEnviadas(
                            $vagasEnviadas[$horario] ?? []
                        );

                        $stmtHorario->execute([
                            ':agenda_padrao_id' => $id,
                            ':horario'          => $horario . ':00',
                            ':quantidade_vagas' => $quantidade,
                        ]);
                    }

                    $pdo->commit();

                    header(
                        'Location: agenda-padrao.php?'
                        . ($acao === 'criar_padrao' ? 'salvo=1' : 'editado=1')
                    );
                    exit;
                } catch (Throwable $erro) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $mensagem = $erro instanceof RuntimeException
                        ? $erro->getMessage()
                        : 'Não foi possível salvar a agenda-padrão.';
                    $tipoMensagem = 'error';
                }
            }
        }
    }

    if ($acao === 'excluir_padrao') {
        $id = inteiroOuNull($_POST['id'] ?? null);

        if ($id === null) {
            $mensagem = 'Agenda-padrão inválida para exclusão.';
            $tipoMensagem = 'error';
        } else {
            $stmt = $pdo->prepare(
                'DELETE FROM agenda_padrao_banho_tosa
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);

            header('Location: agenda-padrao.php?excluido=1');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['salvo'])) {
        $mensagem = 'Agenda-padrão cadastrada com sucesso!';
        $tipoMensagem = 'success';
    } elseif (isset($_GET['editado'])) {
        $mensagem = 'Agenda-padrão atualizada com sucesso!';
        $tipoMensagem = 'success';
    } elseif (isset($_GET['excluido'])) {
        $mensagem = 'Agenda-padrão excluída com sucesso!';
        $tipoMensagem = 'success';
    }
}

$padraoEdicao = null;
$vagasEdicao = [];

if ($editarId !== null) {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM agenda_padrao_banho_tosa
         WHERE id = :id'
    );
    $stmt->execute([':id' => $editarId]);
    $padraoEdicao = $stmt->fetch() ?: null;

    if ($padraoEdicao === null) {
        $mensagem = 'A agenda-padrão selecionada não foi encontrada.';
        $tipoMensagem = 'error';
        $editarId = null;
    } else {
        $stmt = $pdo->prepare(
            'SELECT horario, quantidade_vagas
             FROM agenda_padrao_banho_tosa_horarios
             WHERE agenda_padrao_id = :id
             ORDER BY horario'
        );
        $stmt->execute([':id' => $editarId]);

        foreach ($stmt->fetchAll() as $item) {
            $vagasEdicao[substr((string) $item['horario'], 0, 5)] =
                (int) $item['quantidade_vagas'];
        }
    }
}

$stmt = $pdo->query(
    'SELECT *
     FROM agenda_padrao_banho_tosa
     ORDER BY dia_semana, data_inicio DESC, hora_inicio'
);
$padroes = $stmt->fetchAll();

$horariosTodos = $pdo->query(
    'SELECT agenda_padrao_id, horario, quantidade_vagas
     FROM agenda_padrao_banho_tosa_horarios
     ORDER BY agenda_padrao_id, horario'
)->fetchAll();

$horariosPorPadrao = [];

foreach ($horariosTodos as $item) {
    $horariosPorPadrao[(int) $item['agenda_padrao_id']][] = $item;
}

$dados = $_POST ?: ($padraoEdicao ?? []);

$horaInicioForm = (string) (
    $_POST['hora_inicio']
    ?? (
        !empty($dados['hora_inicio'])
            ? substr((string) $dados['hora_inicio'], 0, 5)
            : '09:00'
    )
);

$horaFimForm = (string) (
    $_POST['hora_fim']
    ?? (
        !empty($dados['hora_fim'])
            ? substr((string) $dados['hora_fim'], 0, 5)
            : '17:00'
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vagasParaJs = [];

    foreach (gerarHorariosDeTrinta($horaInicioForm, $horaFimForm) as $hora) {
        $vagasParaJs[$hora] = contarVagasEnviadas(
            ($_POST['vagas'] ?? [])[$hora] ?? []
        );
    }
} else {
    $vagasParaJs = $vagasEdicao;
}

$tituloPagina = 'Agenda-padrão';
$paginaAtiva = 'agenda';
require __DIR__ . '/includes/topo.php';
?>

<div class="page agenda-page standard-schedule-page">
    <div class="page-heading agenda-heading">
        <div>
            <a class="back-link" href="agenda.php">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar para a agenda
            </a>

            <h1>Agenda-padrão de banho e tosa</h1>
            <small>
                Cada quadradinho marcado representa uma vaga. Os horários são gerados de 30 em 30 minutos.
            </small>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <form class="agenda-form-card" method="post" action="agenda-padrao.php">
        <input
            type="hidden"
            name="acao"
            value="<?= $editarId !== null ? 'editar_padrao' : 'criar_padrao' ?>">

        <?php if ($editarId !== null): ?>
            <input type="hidden" name="id" value="<?= $editarId ?>">
        <?php endif; ?>

        <div class="agenda-card-title">
            <div>
                <h2><?= $editarId !== null ? 'Editar agenda-padrão' : 'Criar agenda-padrão' ?></h2>
                <small>
                    Não há separação por funcionário. Cada faixa pode ter de zero a cinco vagas.
                </small>
            </div>

            <?php if ($editarId !== null): ?>
                <a href="agenda-padrao.php" aria-label="Cancelar edição">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="form-grid">
            <div class="field span-4">
                <label for="diaSemanaPadrao">Dia da semana</label>
                <select id="diaSemanaPadrao" name="dia_semana" required>
                    <option value="">Selecione</option>

                    <?php foreach ($diasSemana as $numero => $nome): ?>
                        <option
                            value="<?= $numero ?>"
                            <?= (string) ($dados['dia_semana'] ?? '') === (string) $numero ? 'selected' : '' ?>>
                            <?= escapar($nome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field span-2">
                <label for="horaInicioPadrao">Das</label>
                <input
                    id="horaInicioPadrao"
                    name="hora_inicio"
                    type="time"
                    step="1800"
                    value="<?= escapar($horaInicioForm) ?>"
                    required>
            </div>

            <div class="field span-2">
                <label for="horaFimPadrao">Até</label>
                <input
                    id="horaFimPadrao"
                    name="hora_fim"
                    type="time"
                    step="1800"
                    value="<?= escapar($horaFimForm) ?>"
                    required>
            </div>

            <div class="field span-4">
                <label for="dataInicioPadrao">Começa a valer em</label>
                <input
                    id="dataInicioPadrao"
                    name="data_inicio"
                    type="date"
                    value="<?= escapar($dados['data_inicio'] ?? date('Y-m-d')) ?>"
                    required>
            </div>

            <div class="field span-4">
                <label for="duracaoPadrao">Duração do padrão</label>
                <select id="duracaoPadrao" name="tipo_duracao" required>
                    <option
                        value="INDETERMINADO"
                        <?= ($dados['tipo_duracao'] ?? 'INDETERMINADO') === 'INDETERMINADO' ? 'selected' : '' ?>>
                        Tempo indeterminado
                    </option>
                    <option
                        value="ATE_DATA"
                        <?= ($dados['tipo_duracao'] ?? '') === 'ATE_DATA' ? 'selected' : '' ?>>
                        Até uma data
                    </option>
                </select>
            </div>

            <div class="field span-4" id="dataFimPadraoBox">
                <label for="dataFimPadrao">Válido até</label>
                <input
                    id="dataFimPadrao"
                    name="data_fim"
                    type="date"
                    value="<?= escapar($dados['data_fim'] ?? '') ?>">
            </div>

            <div class="checkbox-field span-4 standard-active-checkbox">
                <input
                    id="ativoPadrao"
                    name="ativo"
                    type="checkbox"
                    value="1"
                    <?= !isset($dados['ativo']) || (int) $dados['ativo'] === 1 ? 'checked' : '' ?>>
                <label for="ativoPadrao">Agenda-padrão ativa</label>
            </div>
        </div>

        <section class="capacity-editor">
            <div class="capacity-editor-heading">
                <div>
                    <h3>Vagas por horário</h3>
                    <small>
                        Exemplo: cinco quadradinhos marcados = cinco vagas disponíveis naquele bloco de 30 minutos.
                    </small>
                </div>

                <button class="secondary-action-button small" type="button" id="marcarTodasVagas">
                    Marcar todas
                </button>
            </div>

            <div class="capacity-grid-header">
                <strong>Horário</strong>
                <span>Vaga 1</span>
                <span>Vaga 2</span>
                <span>Vaga 3</span>
                <span>Vaga 4</span>
                <span>Vaga 5</span>
                <strong>Total</strong>
            </div>

            <div id="capacityGrid" class="capacity-grid"></div>
        </section>

        <div class="form-actions">
            <button class="save-button" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $editarId !== null ? 'Salvar alterações' : 'Criar agenda-padrão' ?>
            </button>
        </div>
    </form>

    <section class="agenda-section">
        <div class="agenda-section-title">
            <div>
                <i class="fa-solid fa-calendar-week"></i>
                <h2>Agendas-padrão cadastradas</h2>
            </div>
            <span><?= count($padroes) ?> padrão(ões)</span>
        </div>

        <?php if (!$padroes): ?>
            <div class="agenda-empty">
                Nenhuma agenda-padrão foi criada.
            </div>
        <?php else: ?>
            <div class="standard-pattern-list">
                <?php foreach ($padroes as $padrao): ?>
                    <?php $horariosPadrao = $horariosPorPadrao[(int) $padrao['id']] ?? []; ?>

                    <article class="standard-pattern-card">
                        <div class="standard-pattern-summary">
                            <div>
                                <strong><?= escapar($diasSemana[(int) $padrao['dia_semana']] ?? '-') ?></strong>
                                <span>
                                    <?= escapar(substr((string) $padrao['hora_inicio'], 0, 5)) ?>
                                    às
                                    <?= escapar(substr((string) $padrao['hora_fim'], 0, 5)) ?>
                                </span>
                                <small>
                                    A partir de <?= escapar(formatarData($padrao['data_inicio'])) ?>
                                    <?php if ($padrao['tipo_duracao'] === 'INDETERMINADO'): ?>
                                        • tempo indeterminado
                                    <?php else: ?>
                                        • até <?= escapar(formatarData($padrao['data_fim'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <span class="status-badge <?= (int) $padrao['ativo'] === 1 ? 'success' : 'neutral' ?>">
                                <?= (int) $padrao['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                            </span>

                            <div class="agenda-row-actions">
                                <a
                                    class="icon-action-button edit"
                                    href="agenda-padrao.php?editar=<?= (int) $padrao['id'] ?>"
                                    title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <form
                                    method="post"
                                    action="agenda-padrao.php"
                                    onsubmit="return confirm('Excluir esta agenda-padrão?');">
                                    <input type="hidden" name="acao" value="excluir_padrao">
                                    <input type="hidden" name="id" value="<?= (int) $padrao['id'] ?>">
                                    <button class="icon-action-button danger" type="submit" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <details class="standard-pattern-details">
                            <summary>Ver vagas de 30 em 30 minutos</summary>

                            <div class="pattern-preview-grid">
                                <?php foreach ($horariosPadrao as $horario): ?>
                                    <div class="pattern-preview-row">
                                        <strong><?= escapar(substr((string) $horario['horario'], 0, 5)) ?></strong>

                                        <div class="pattern-preview-dots">
                                            <?php for ($vaga = 1; $vaga <= 5; $vaga++): ?>
                                                <span class="<?= $vaga <= (int) $horario['quantidade_vagas'] ? 'active' : '' ?>"></span>
                                            <?php endfor; ?>
                                        </div>

                                        <small>
                                            <?= (int) $horario['quantidade_vagas'] ?> vaga(s)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
(() => {
    const valoresIniciais = <?= json_encode(
        $vagasParaJs,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const inicio = document.querySelector('#horaInicioPadrao');
    const fim = document.querySelector('#horaFimPadrao');
    const grid = document.querySelector('#capacityGrid');
    const marcarTodas = document.querySelector('#marcarTodasVagas');
    const tipoDuracao = document.querySelector('#duracaoPadrao');
    const dataFimBox = document.querySelector('#dataFimPadraoBox');
    const dataFim = document.querySelector('#dataFimPadrao');

    if (!inicio || !fim || !grid) {
        return;
    }

    const paraMinutos = (hora) => {
        const [h, m] = hora.split(':').map(Number);
        return (h * 60) + m;
    };

    const paraHora = (total) => {
        const h = Math.floor(total / 60);
        const m = total % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    };

    const atualizarTotalLinha = (linha) => {
        const total = linha.querySelectorAll('input[type="checkbox"]:checked').length;
        const texto = linha.querySelector('.capacity-total');
        texto.textContent = `${total} vaga${total === 1 ? '' : 's'}`;
    };

    const renderizarGrade = () => {
        const inicioMinutos = paraMinutos(inicio.value);
        const fimMinutos = paraMinutos(fim.value);

        grid.innerHTML = '';

        if (
            !Number.isFinite(inicioMinutos)
            || !Number.isFinite(fimMinutos)
            || fimMinutos <= inicioMinutos
        ) {
            grid.innerHTML =
                '<div class="capacity-grid-empty">Escolha um horário inicial e final válidos.</div>';
            return;
        }

        for (
            let minutoAtual = inicioMinutos;
            minutoAtual < fimMinutos;
            minutoAtual += 30
        ) {
            const hora = paraHora(minutoAtual);
            const quantidadeInicial =
                Object.prototype.hasOwnProperty.call(valoresIniciais, hora)
                    ? Number(valoresIniciais[hora])
                    : 5;

            const linha = document.createElement('div');
            linha.className = 'capacity-grid-row';

            const horaElemento = document.createElement('strong');
            horaElemento.className = 'capacity-time';
            horaElemento.textContent = hora;
            linha.appendChild(horaElemento);

            for (let vaga = 1; vaga <= 5; vaga++) {
                const label = document.createElement('label');
                label.className = 'capacity-check';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = `vagas[${hora}][]`;
                checkbox.value = String(vaga);
                checkbox.checked = vaga <= quantidadeInicial;

                const visual = document.createElement('span');
                visual.textContent = String(vaga);

                checkbox.addEventListener('change', () => {
                    atualizarTotalLinha(linha);
                });

                label.appendChild(checkbox);
                label.appendChild(visual);
                linha.appendChild(label);
            }

            const total = document.createElement('strong');
            total.className = 'capacity-total';
            linha.appendChild(total);

            grid.appendChild(linha);
            atualizarTotalLinha(linha);
        }
    };

    const atualizarDuracao = () => {
        if (!tipoDuracao || !dataFimBox || !dataFim) {
            return;
        }

        const ateData = tipoDuracao.value === 'ATE_DATA';
        dataFimBox.hidden = !ateData;
        dataFim.required = ateData;

        if (!ateData) {
            dataFim.value = '';
        }
    };

    inicio.addEventListener('change', renderizarGrade);
    fim.addEventListener('change', renderizarGrade);

    if (marcarTodas) {
        marcarTodas.addEventListener('click', () => {
            grid.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                checkbox.checked = true;
            });

            grid.querySelectorAll('.capacity-grid-row').forEach(atualizarTotalLinha);
        });
    }

    if (tipoDuracao) {
        tipoDuracao.addEventListener('change', atualizarDuracao);
    }

    renderizarGrade();
    atualizarDuracao();
})();
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
