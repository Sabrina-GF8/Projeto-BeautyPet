<?php
// Inclui o arquivo de configuração (conexão com banco de dados $pdo, funções auxiliares, etc.)
require __DIR__ . '/config.php';

// Obtém os dados do usuário logado no momento (via sessão/cookie, definido em config.php)
$usuarioAtual = obterUsuarioAtual($pdo);

// Extrai o ID do usuário atual, convertendo para inteiro, ou null se não existir
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;

// Verifica se o usuário logado tem perfil de ADMINISTRADOR;
// caso não tenha, redireciona para a agenda comum com aviso de falta de permissão
if (($usuarioAtual['perfil'] ?? '') !== 'ADMINISTRADOR') {
    header('Location: agenda.php?sem_permissao=1');
    exit;
}

// Variáveis para exibir mensagens de feedback ao usuário (sucesso/erro)
$mensagem = '';
$tipoMensagem = '';

// Captura o ID de uma agenda-padrão a ser editada, vindo via GET (?editar=ID)
$editarId = inteiroOuNull($_GET['editar'] ?? null);

// Mapeamento dos dias da semana (1 = Segunda ... 7 = Domingo) para exibição
$diasSemana = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];

// Bloco executado apenas quando o formulário é enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação foi solicitada pelo formulário (criar, editar ou excluir)
    $acao = (string) ($_POST['acao'] ?? '');

    // Trata tanto a criação quanto a edição de uma agenda-padrão
    if (in_array($acao, ['criar_padrao', 'editar_padrao'], true)) {
        // Se for edição, recupera o ID enviado; se for criação, ainda não existe ID
        $id = $acao === 'editar_padrao'
            ? inteiroOuNull($_POST['id'] ?? null)
            : null;

        // Captura e normaliza os dados enviados pelo formulário
        $diaSemana = inteiroOuNull($_POST['dia_semana'] ?? null);
        $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
        $horaFim = (string) ($_POST['hora_fim'] ?? '');
        $dataInicio = (string) ($_POST['data_inicio'] ?? '');
        $tipoDuracao = (string) ($_POST['tipo_duracao'] ?? '');
        $dataFim = textoOuNull($_POST['data_fim'] ?? null);
        // Checkbox "ativo": se estiver marcado, vem no POST; senão, assume 0
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        // Array com as vagas marcadas por horário, enviado pelo formulário (grade de checkboxes)
        $vagasEnviadas = $_POST['vagas'] ?? [];

        // Gera a lista de horários de 30 em 30 minutos entre hora início e hora fim
        $horarios = gerarHorariosDeTrinta($horaInicio, $horaFim);

        // Validação principal dos campos obrigatórios e formatos
        if (
            $diaSemana === null
            || !isset($diasSemana[$diaSemana])
            || !horaEmBlocoDeTrinta($horaInicio)
            || !horaEmBlocoDeTrinta($horaFim)
            || !$horarios
            || !dataSqlValida($dataInicio)
            || !in_array($tipoDuracao, ['INDETERMINADO', 'ATE_DATA'], true)
        ) {
            // Mensagem de erro genérica quando algum campo obrigatório é inválido
            $mensagem = 'Preencha corretamente o dia, o período e a validade. Os horários devem terminar em :00 ou :30.';
            $tipoMensagem = 'error';
        } elseif (
            // Validação específica: se a duração for "até uma data", a data final deve ser válida
            // e não pode ser anterior à data de início
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
            // Se a duração for indeterminada, garante que não haja data final armazenada
            if ($tipoDuracao === 'INDETERMINADO') {
                $dataFim = null;
            }

            // Verifica se já existe outra agenda-padrão ativa que se sobreponha
            // ao mesmo dia da semana e período (evita conflitos de horário)
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
                    // Inicia transação para garantir consistência entre a tabela principal
                    // e a tabela de horários/vagas
                    $pdo->beginTransaction();

                    if ($acao === 'criar_padrao') {
                        // Monta o INSERT de uma nova agenda-padrão
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

                        // Executa o INSERT com os valores capturados do formulário
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

                        // Recupera o ID gerado para a nova agenda-padrão (necessário para os horários)
                        $id = (int) $pdo->lastInsertId();
                    } else {
                        // Caso de edição: exige que um ID válido tenha sido informado
                        if ($id === null) {
                            throw new RuntimeException(
                                'Agenda-padrão inválida para edição.'
                            );
                        }

                        // Monta o UPDATE da agenda-padrão existente
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

                        // Executa o UPDATE com os novos valores
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

                        // Remove todos os horários/vagas antigos associados a essa agenda-padrão,
                        // pois serão reinseridos a seguir com os valores atualizados
                        $pdo->prepare(
                            'DELETE FROM agenda_padrao_banho_tosa_horarios
                             WHERE agenda_padrao_id = :id'
                        )->execute([':id' => $id]);
                    }

                    // Prepara o INSERT que será reutilizado para cada horário de 30 em 30 minutos
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

                    // Percorre cada horário gerado (ex.: 09:00, 09:30, 10:00...) e insere
                    // a quantidade de vagas marcadas para aquele horário específico
                    foreach ($horarios as $horario) {
                        // Conta quantas "vagas" (checkboxes) foram marcadas para este horário
                        $quantidade = contarVagasEnviadas(
                            $vagasEnviadas[$horario] ?? []
                        );

                        $stmtHorario->execute([
                            ':agenda_padrao_id' => $id,
                            ':horario'          => $horario . ':00',
                            ':quantidade_vagas' => $quantidade,
                        ]);
                    }

                    // Confirma a transação, salvando tanto a agenda-padrão quanto seus horários
                    $pdo->commit();

                    // Redireciona de volta à página com um parâmetro indicando sucesso
                    // (evita reenvio do formulário ao atualizar a página - padrão PRG)
                    header(
                        'Location: agenda-padrao.php?'
                        . ($acao === 'criar_padrao' ? 'salvo=1' : 'editado=1')
                    );
                    exit;
                } catch (Throwable $erro) {
                    // Em caso de qualquer erro, desfaz a transação para não deixar dados inconsistentes
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // Define a mensagem de erro: usa a mensagem customizada da exceção
                    // se for RuntimeException, senão usa uma mensagem genérica
                    $mensagem = $erro instanceof RuntimeException
                        ? $erro->getMessage()
                        : 'Não foi possível salvar a agenda-padrão.';
                    $tipoMensagem = 'error';
                }
            }
        }
    }

    // Trata a ação de exclusão de uma agenda-padrão
    if ($acao === 'excluir_padrao') {
        $id = inteiroOuNull($_POST['id'] ?? null);

        if ($id === null) {
            $mensagem = 'Agenda-padrão inválida para exclusão.';
            $tipoMensagem = 'error';
        } else {
            // Remove a agenda-padrão do banco (assume-se que os horários relacionados
            // são removidos em cascata pelo próprio banco de dados)
            $stmt = $pdo->prepare(
                'DELETE FROM agenda_padrao_banho_tosa
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);

            // Redireciona indicando sucesso na exclusão
            header('Location: agenda-padrao.php?excluido=1');
            exit;
        }
    }
}

// Bloco para exibir mensagens de sucesso vindas via query string (redirecionamento após POST)
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

// Variáveis que armazenarão os dados da agenda-padrão em edição (se houver)
$padraoEdicao = null;
$vagasEdicao = [];

// Se um ID de edição foi passado via GET, busca os dados dessa agenda-padrão no banco
if ($editarId !== null) {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM agenda_padrao_banho_tosa
         WHERE id = :id'
    );
    $stmt->execute([':id' => $editarId]);
    $padraoEdicao = $stmt->fetch() ?: null;

    if ($padraoEdicao === null) {
        // Caso o ID informado não corresponda a nenhum registro, avisa o usuário
        // e reseta $editarId para voltar ao modo de criação
        $mensagem = 'A agenda-padrão selecionada não foi encontrada.';
        $tipoMensagem = 'error';
        $editarId = null;
    } else {
        // Busca os horários e quantidades de vagas cadastrados para essa agenda-padrão
        $stmt = $pdo->prepare(
            'SELECT horario, quantidade_vagas
             FROM agenda_padrao_banho_tosa_horarios
             WHERE agenda_padrao_id = :id
             ORDER BY horario'
        );
        $stmt->execute([':id' => $editarId]);

        // Monta um array associativo [horario => quantidade_vagas] para popular o formulário,
        // usando apenas "HH:MM" (removendo os segundos) como chave
        foreach ($stmt->fetchAll() as $item) {
            $vagasEdicao[substr((string) $item['horario'], 0, 5)] =
                (int) $item['quantidade_vagas'];
        }
    }
}

// Busca todas as agendas-padrão cadastradas, ordenadas por dia da semana,
// data de início (mais recente primeiro) e hora de início
$stmt = $pdo->query(
    'SELECT *
     FROM agenda_padrao_banho_tosa
     ORDER BY dia_semana, data_inicio DESC, hora_inicio'
);
$padroes = $stmt->fetchAll();

// Busca todos os horários/vagas de todas as agendas-padrão de uma vez só
// (evita fazer uma consulta por agenda-padrão dentro do loop de exibição)
$horariosTodos = $pdo->query(
    'SELECT agenda_padrao_id, horario, quantidade_vagas
     FROM agenda_padrao_banho_tosa_horarios
     ORDER BY agenda_padrao_id, horario'
)->fetchAll();

// Agrupa os horários por ID da agenda-padrão, para facilitar o acesso na listagem
$horariosPorPadrao = [];

foreach ($horariosTodos as $item) {
    $horariosPorPadrao[(int) $item['agenda_padrao_id']][] = $item;
}

// Define a fonte de dados para preencher o formulário:
// prioriza os dados recém-enviados via POST (em caso de erro de validação);
// caso não haja POST, usa os dados da agenda-padrão em edição (se houver)
$dados = $_POST ?: ($padraoEdicao ?? []);

// Define o valor do campo "hora início" do formulário, com fallback para 09:00
$horaInicioForm = (string) (
    $_POST['hora_inicio']
    ?? (
        !empty($dados['hora_inicio'])
            ? substr((string) $dados['hora_inicio'], 0, 5)
            : '09:00'
    )
);

// Define o valor do campo "hora fim" do formulário, com fallback para 17:00
$horaFimForm = (string) (
    $_POST['hora_fim']
    ?? (
        !empty($dados['hora_fim'])
            ? substr((string) $dados['hora_fim'], 0, 5)
            : '17:00'
    )
);

// Monta o array de vagas que será enviado ao JavaScript para desenhar a grade de horários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se veio de um POST (ex.: erro de validação), reconstrói as vagas a partir
    // do que o usuário tinha marcado antes de reenviar o formulário
    $vagasParaJs = [];

    foreach (gerarHorariosDeTrinta($horaInicioForm, $horaFimForm) as $hora) {
        $vagasParaJs[$hora] = contarVagasEnviadas(
            ($_POST['vagas'] ?? [])[$hora] ?? []
        );
    }
} else {
    // Caso contrário (GET normal ou edição), usa as vagas já carregadas do banco
    $vagasParaJs = $vagasEdicao;
}

// Define metadados da página usados pelo template de topo (título, item de menu ativo)
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
        <!-- Exibe a mensagem de feedback (sucesso ou erro) definida no processamento acima -->
        <div class="message <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de criação/edição de agenda-padrão. A ação (criar/editar) e o método
         são definidos dinamicamente conforme o modo atual da página -->
    <form class="agenda-form-card" method="post" action="agenda-padrao.php">
        <input
            type="hidden"
            name="acao"
            value="<?= $editarId !== null ? 'editar_padrao' : 'criar_padrao' ?>">

        <?php if ($editarId !== null): ?>
            <!-- Campo oculto com o ID da agenda-padrão, presente apenas em modo de edição -->
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
                <!-- Link para cancelar a edição e voltar ao modo de criação -->
                <a href="agenda-padrao.php" aria-label="Cancelar edição">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="form-grid">
            <!-- Seleção do dia da semana para o qual a agenda-padrão vale -->
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

            <!-- Campo de hora inicial, em blocos de 30 minutos (step=1800 segundos) -->
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

            <!-- Campo de hora final, também em blocos de 30 minutos -->
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

            <!-- Data a partir da qual a agenda-padrão passa a valer -->
            <div class="field span-4">
                <label for="dataInicioPadrao">Começa a valer em</label>
                <input
                    id="dataInicioPadrao"
                    name="data_inicio"
                    type="date"
                    value="<?= escapar($dados['data_inicio'] ?? date('Y-m-d')) ?>"
                    required>
            </div>

            <!-- Tipo de duração: indeterminada ou até uma data específica -->
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

            <!-- Campo de data final, exibido/obrigatório apenas quando a duração é "ATE_DATA"
                 (controle de exibição feito via JavaScript mais abaixo) -->
            <div class="field span-4" id="dataFimPadraoBox">
                <label for="dataFimPadrao">Válido até</label>
                <input
                    id="dataFimPadrao"
                    name="data_fim"
                    type="date"
                    value="<?= escapar($dados['data_fim'] ?? '') ?>">
            </div>

            <!-- Checkbox que define se a agenda-padrão está ativa; marcado por padrão
                 quando não há dado prévio (nova agenda) -->
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

        <!-- Seção onde o usuário marca, para cada horário de 30 minutos,
             quantas vagas (checkboxes de 1 a 5) estarão disponíveis.
             A grade em si (#capacityGrid) é montada dinamicamente via JavaScript -->
        <section class="capacity-editor">
            <div class="capacity-editor-heading">
                <div>
                    <h3>Vagas por horário</h3>
                    <small>
                        Exemplo: cinco quadradinhos marcados = cinco vagas disponíveis naquele bloco de 30 minutos.
                    </small>
                </div>

                <!-- Botão que aciona o JS para marcar todas as vagas de todos os horários -->
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

            <!-- Container vazio: as linhas de horário/vagas são inseridas via JS
                 (função renderizarGrade), com base no intervalo de hora início/fim -->
            <div id="capacityGrid" class="capacity-grid"></div>
        </section>

        <div class="form-actions">
            <button class="save-button" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $editarId !== null ? 'Salvar alterações' : 'Criar agenda-padrão' ?>
            </button>
        </div>
    </form>

    <!-- Listagem de todas as agendas-padrão já cadastradas no sistema -->
    <section class="agenda-section">
        <div class="agenda-section-title">
            <div>
                <i class="fa-solid fa-calendar-week"></i>
                <h2>Agendas-padrão cadastradas</h2>
            </div>
            <span><?= count($padroes) ?> padrão(ões)</span>
        </div>

        <?php if (!$padroes): ?>
            <!-- Mensagem exibida quando não há nenhuma agenda-padrão cadastrada -->
            <div class="agenda-empty">
                Nenhuma agenda-padrão foi criada.
            </div>
        <?php else: ?>
            <div class="standard-pattern-list">
                <?php foreach ($padroes as $padrao): ?>
                    <?php $horariosPadrao = $horariosPorPadrao[(int) $padrao['id']] ?? []; ?>

                    <!-- Card com o resumo de uma agenda-padrão: dia, horário, vigência e status -->
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

                            <!-- Selo indicando se a agenda-padrão está ativa ou inativa -->
                            <span class="status-badge <?= (int) $padrao['ativo'] === 1 ? 'success' : 'neutral' ?>">
                                <?= (int) $padrao['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                            </span>

                            <!-- Ações rápidas: editar (link) e excluir (formulário com confirmação) -->
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

                        <!-- Detalhamento (recolhível) de cada horário de 30 minutos e suas vagas -->
                        <details class="standard-pattern-details">
                            <summary>Ver vagas de 30 em 30 minutos</summary>

                            <div class="pattern-preview-grid">
                                <?php foreach ($horariosPadrao as $horario): ?>
                                    <div class="pattern-preview-row">
                                        <strong><?= escapar(substr((string) $horario['horario'], 0, 5)) ?></strong>

                                        <!-- Representação visual das vagas: bolinhas preenchidas
                                             conforme a quantidade de vagas daquele horário -->
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
    // Valores iniciais de vagas por horário (vindos do PHP), usados para pré-marcar
    // os checkboxes da grade ao carregar a página (edição) ou após erro de validação
    const valoresIniciais = <?= json_encode(
        $vagasParaJs,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    // Referências aos elementos do formulário manipulados pelo script
    const inicio = document.querySelector('#horaInicioPadrao');
    const fim = document.querySelector('#horaFimPadrao');
    const grid = document.querySelector('#capacityGrid');
    const marcarTodas = document.querySelector('#marcarTodasVagas');
    const tipoDuracao = document.querySelector('#duracaoPadrao');
    const dataFimBox = document.querySelector('#dataFimPadraoBox');
    const dataFim = document.querySelector('#dataFimPadrao');

    // Se os elementos essenciais não existirem na página, interrompe a execução do script
    if (!inicio || !fim || !grid) {
        return;
    }

    // Converte uma hora no formato "HH:MM" para o total de minutos desde 00:00
    const paraMinutos = (hora) => {
        const [h, m] = hora.split(':').map(Number);
        return (h * 60) + m;
    };

    // Converte um total de minutos de volta para o formato "HH:MM"
    const paraHora = (total) => {
        const h = Math.floor(total / 60);
        const m = total % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    };

    // Atualiza o texto de total de vagas marcadas em uma linha específica da grade
    const atualizarTotalLinha = (linha) => {
        const total = linha.querySelectorAll('input[type="checkbox"]:checked').length;
        const texto = linha.querySelector('.capacity-total');
        texto.textContent = `${total} vaga${total === 1 ? '' : 's'}`;
    };

    // Monta (ou remonta) toda a grade de horários/vagas com base no intervalo
    // definido nos campos de hora início e hora fim
    const renderizarGrade = () => {
        const inicioMinutos = paraMinutos(inicio.value);
        const fimMinutos = paraMinutos(fim.value);

        // Limpa a grade atual antes de reconstruir
        grid.innerHTML = '';

        // Valida se o intervalo de horários é coerente (fim deve ser maior que início)
        if (
            !Number.isFinite(inicioMinutos)
            || !Number.isFinite(fimMinutos)
            || fimMinutos <= inicioMinutos
        ) {
            grid.innerHTML =
                '<div class="capacity-grid-empty">Escolha um horário inicial e final válidos.</div>';
            return;
        }

        // Itera de 30 em 30 minutos dentro do intervalo, criando uma linha para cada horário
        for (
            let minutoAtual = inicioMinutos;
            minutoAtual < fimMinutos;
            minutoAtual += 30
        ) {
            const hora = paraHora(minutoAtual);
            // Define quantas vagas já estavam marcadas para esse horário (do PHP);
            // se não houver valor prévio, assume o máximo (5) por padrão
            const quantidadeInicial =
                Object.prototype.hasOwnProperty.call(valoresIniciais, hora)
                    ? Number(valoresIniciais[hora])
                    : 5;

            // Cria a linha (row) da grade para este horário
            const linha = document.createElement('div');
            linha.className = 'capacity-grid-row';

            // Coluna com o rótulo do horário (ex.: "09:00")
            const horaElemento = document.createElement('strong');
            horaElemento.className = 'capacity-time';
            horaElemento.textContent = hora;
            linha.appendChild(horaElemento);

            // Cria os 5 checkboxes de vaga para este horário (vaga 1 a vaga 5)
            for (let vaga = 1; vaga <= 5; vaga++) {
                const label = document.createElement('label');
                label.className = 'capacity-check';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                // Nome do campo no formato vagas[HH:MM][] para ser recebido como array no PHP
                checkbox.name = `vagas[${hora}][]`;
                checkbox.value = String(vaga);
                // Marca o checkbox se o número da vaga estiver dentro da quantidade inicial
                checkbox.checked = vaga <= quantidadeInicial;

                const visual = document.createElement('span');
                visual.textContent = String(vaga);

                // Atualiza o total da linha sempre que um checkbox for alterado
                checkbox.addEventListener('change', () => {
                    atualizarTotalLinha(linha);
                });

                label.appendChild(checkbox);
                label.appendChild(visual);
                linha.appendChild(label);
            }

            // Coluna de total de vagas marcadas nesta linha (atualizada dinamicamente)
            const total = document.createElement('strong');
            total.className = 'capacity-total';
            linha.appendChild(total);

            grid.appendChild(linha);
            // Calcula o total inicial assim que a linha é inserida na grade
            atualizarTotalLinha(linha);
        }
    };

    // Mostra/oculta e torna obrigatório (ou não) o campo de "Válido até"
    // conforme a opção selecionada em "Duração do padrão"
    const atualizarDuracao = () => {
        if (!tipoDuracao || !dataFimBox || !dataFim) {
            return;
        }

        const ateData = tipoDuracao.value === 'ATE_DATA';
        dataFimBox.hidden = !ateData;
        dataFim.required = ateData;

        // Se a duração voltar a ser indeterminada, limpa o valor da data final
        if (!ateData) {
            dataFim.value = '';
        }
    };

    // Sempre que a hora início ou hora fim mudar, a grade de vagas é reconstruída
    inicio.addEventListener('change', renderizarGrade);
    fim.addEventListener('change', renderizarGrade);

    // Botão "Marcar todas": marca todos os checkboxes de vaga de todos os horários
    if (marcarTodas) {
        marcarTodas.addEventListener('click', () => {
            grid.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                checkbox.checked = true;
            });

            // Recalcula o total de cada linha após marcar tudo
            grid.querySelectorAll('.capacity-grid-row').forEach(atualizarTotalLinha);
        });
    }

    // Reage à troca do tipo de duração, exibindo/ocultando o campo de data final
    if (tipoDuracao) {
        tipoDuracao.addEventListener('change', atualizarDuracao);
    }

    // Execução inicial ao carregar a página: monta a grade e ajusta o campo de duração
    renderizarGrade();
    atualizarDuracao();
})();
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
