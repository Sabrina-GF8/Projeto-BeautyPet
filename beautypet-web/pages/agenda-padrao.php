<?php
/**
 * ARQUIVO: agenda-padrao.php
 * RESPONSABILIDADE: Configuração da agenda-padrão de banho e tosa.
 * Permite ao administrador definir dias, horários e quantidade de vagas que serão usados para calcular os horários disponíveis na agenda.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';
// Recupera os dados do usuário autenticado para saber quem está operando o sistema.
$usuarioAtual = obterUsuarioAtual($pdo);
// Guarda somente o ID do usuário atual para registrar autoria das operações no banco.
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;

// Verifica a condição abaixo antes de executar este bloco.
if (($usuarioAtual['perfil'] ?? '') !== 'ADMINISTRADOR') {
    // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
    header('Location: agenda.php?sem_permissao=1');
    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
    exit;
}

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? null);

// Armazena em "diasSemana" o valor necessário para as próximas etapas deste fluxo.
$diasSemana = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação o formulário solicitou para direcionar o processamento correto.
    $acao = (string) ($_POST['acao'] ?? '');

    // Verifica a condição abaixo antes de executar este bloco.
    if (in_array($acao, ['criar_padrao', 'editar_padrao'], true)) {
        // Converte o identificador recebido para uso seguro nas consultas e alterações.
        $id = $acao === 'editar_padrao'
            ? inteiroOuNull($_POST['id'] ?? null)
            : null;

        // Armazena em "diaSemana" o valor necessário para as próximas etapas deste fluxo.
        $diaSemana = inteiroOuNull($_POST['dia_semana'] ?? null);
        // Guarda o horário efetivo de início que será salvo no agendamento ou atendimento.
        $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
        // Guarda o horário de término quando o atendimento possui intervalo de início e fim.
        $horaFim = (string) ($_POST['hora_fim'] ?? '');
        // Armazena a data "dataInicio" usada nas validações e consultas desta página.
        $dataInicio = (string) ($_POST['data_inicio'] ?? '');
        // Guarda a forma como a duração ou validade será interpretada na regra em processamento.
        $tipoDuracao = (string) ($_POST['tipo_duracao'] ?? '');
        // Guarda a data de término quando o serviço ocupa mais de um dia, como ocorre no hotel.
        $dataFim = textoOuNull($_POST['data_fim'] ?? null);
        // Armazena em "ativo" o valor necessário para as próximas etapas deste fluxo.
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        // Armazena a coleção "vagasEnviadas" carregada para ser percorrida e exibida/processada a seguir.
        $vagasEnviadas = $_POST['vagas'] ?? [];

        // Armazena a coleção "horarios" carregada para ser percorrida e exibida/processada a seguir.
        $horarios = gerarHorariosDeTrinta($horaInicio, $horaFim);

        // Verifica a condição abaixo antes de executar este bloco.
        if (
            // Armazena em "diaSemana" o valor necessário para as próximas etapas deste fluxo.
            $diaSemana === null
            || !isset($diasSemana[$diaSemana])
            || !horaEmBlocoDeTrinta($horaInicio)
            || !horaEmBlocoDeTrinta($horaFim)
            || !$horarios
            || !dataSqlValida($dataInicio)
            || !in_array($tipoDuracao, ['INDETERMINADO', 'ATE_DATA'], true)
        ) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Preencha corretamente o dia, o período e a validade. Os horários devem terminar em :00 ou :30.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
        } elseif (
            // Guarda a forma como a duração ou validade será interpretada na regra em processamento.
            $tipoDuracao === 'ATE_DATA'
            && (
                // Guarda a data de término quando o serviço ocupa mais de um dia, como ocorre no hotel.
                $dataFim === null
                || !dataSqlValida($dataFim)
                || $dataFim < $dataInicio
            )
        ) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'A data final deve ser válida e igual ou posterior à data inicial.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Verifica a condição abaixo antes de executar este bloco.
            if ($tipoDuracao === 'INDETERMINADO') {
                // Guarda a data de término quando o serviço ocupa mais de um dia, como ocorre no hotel.
                $dataFim = null;
            }

            // Verifica a condição abaixo antes de executar este bloco.
            if (
                // Armazena em "ativo" o valor necessário para as próximas etapas deste fluxo.
                $ativo === 1
                && padraoSobreposto(
                    $pdo,
                    $diaSemana,
                    $dataInicio,
                    $dataFim,
                    $id
                )
            ) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Já existe uma agenda-padrão ativa para esse dia da semana no mesmo período.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
                try {
                    // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
                    $pdo->beginTransaction();

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($acao === 'criar_padrao') {
                        // Prepara a inclusão de um novo registro em "agenda_padrao_banho_tosa" usando os valores já validados.
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

                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
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

                        // Converte o identificador recebido para uso seguro nas consultas e alterações.
                        $id = (int) $pdo->lastInsertId();
                    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                    } else {
                        // Interrompe a ação quando o identificador obrigatório não foi informado ou não é válido.
                        if ($id === null) {
                            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                            throw new RuntimeException(
                                'Agenda-padrão inválida para edição.'
                            );
                        }

                        // Prepara a inclusão de um novo registro em "agenda_padrao_banho_tosa_horarios" usando os valores já validados.
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

                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
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

                        // Prepara a consulta SQL para receber parâmetros sem concatenar valores diretamente no comando.
                        $pdo->prepare(
                            'DELETE FROM agenda_padrao_banho_tosa_horarios
                             WHERE agenda_padrao_id = :id'
                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                        )->execute([':id' => $id]);
                    }

                    // Armazena em "stmtHorario" o valor necessário para as próximas etapas deste fluxo.
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

                    // Percorre cada item da coleção para processar os registros individualmente.
                    foreach ($horarios as $horario) {
                        // HEURÍSTICA 9: antes um valor como 9 ou -1 era trocado em silêncio; agora o usuário é avisado.
                        if (vagasForaDaFaixa($vagasEnviadas[$horario] ?? 0)) {
                            throw new RuntimeException('A quantidade de vagas das ' . $horario . ' deve ser um número inteiro entre 0 e 5. Corrija e salve novamente.');
                        }

                        // Guarda a quantidade informada e usada para vagas, créditos, itens ou repetições.
                        $quantidade = quantidadeVagas(
                            $vagasEnviadas[$horario] ?? 0
                        );

                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                        $stmtHorario->execute([
                            ':agenda_padrao_id' => $id,
                            ':horario'          => $horario . ':00',
                            ':quantidade_vagas' => $quantidade,
                        ]);
                    }

                    // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
                    $pdo->commit();

                    // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                    header(
                        'Location: agenda-padrao.php?'
                        . ($acao === 'criar_padrao' ? 'salvo=1' : 'editado=1')
                    );
                    // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                    exit;
                // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
                } catch (Throwable $erro) {
                    // Confere se existe uma transação aberta antes de tentar desfazê-la.
                    if ($pdo->inTransaction()) {
                        // Desfaz as alterações da transação atual porque alguma etapa falhou.
                        $pdo->rollBack();
                    }

                    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                    // HEURÍSTICA 9: erro técnico vai para o log; o usuário recebe o próximo passo.
                    if (!erroDeRegra($erro)) {
                        error_log('[BeautyPet] agenda-padrao: ' . $erro->getMessage());
                    }
                    $mensagem = erroDeRegra($erro)
                        ? $erro->getMessage()
                        : 'Não foi possível salvar a agenda-padrão agora. Nada foi alterado: confira a internet e tente novamente.';
                    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                    $tipoMensagem = 'error';
                }
            }
        }
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($acao === 'excluir_padrao') {
        // Converte o identificador recebido para uso seguro nas consultas e alterações.
        $id = inteiroOuNull($_POST['id'] ?? null);

        // Interrompe a ação quando o identificador obrigatório não foi informado ou não é válido.
        if ($id === null) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Agenda-padrão inválida para exclusão.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Prepara uma consulta em "agenda_padrao_banho_tosa" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare(
                'DELETE FROM agenda_padrao_banho_tosa
                 WHERE id = :id'
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([':id' => $id]);

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header('Location: agenda-padrao.php?excluido=1');
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }
    }
}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verifica a condição abaixo antes de executar este bloco.
    if (isset($_GET['salvo'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Agenda-padrão cadastrada com sucesso!';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['editado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Agenda-padrão atualizada com sucesso!';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['excluido'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Agenda-padrão excluída com sucesso!';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
    }
}

// Carrega a configuração da agenda-padrão selecionada para preencher os campos de edição.
$padraoEdicao = null;
// Armazena em "vagasEdicao" o valor necessário para as próximas etapas deste fluxo.
$vagasEdicao = [];

// Verifica a condição abaixo antes de executar este bloco.
if ($editarId !== null) {
    // Prepara uma consulta em "agenda_padrao_banho_tosa" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT *
         FROM agenda_padrao_banho_tosa
         WHERE id = :id'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $editarId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $padraoEdicao = $stmt->fetch() ?: null;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($padraoEdicao === null) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'A agenda-padrão selecionada não foi encontrada.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
        // Lê o identificador enviado pela URL para saber se a página está em modo de edição.
        $editarId = null;
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else {
        // Prepara uma consulta em "agenda_padrao_banho_tosa_horarios" para carregar os dados necessários a esta etapa.
        $stmt = $pdo->prepare(
            'SELECT horario, quantidade_vagas
             FROM agenda_padrao_banho_tosa_horarios
             WHERE agenda_padrao_id = :id
             ORDER BY horario'
        );
        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
        $stmt->execute([':id' => $editarId]);

        // Percorre cada item da coleção para processar os registros individualmente.
        foreach ($stmt->fetchAll() as $item) {
            $vagasEdicao[substr((string) $item['horario'], 0, 5)] =
                (int) $item['quantidade_vagas'];
        }
    }
}

// Prepara uma consulta em "agenda_padrao_banho_tosa" para carregar os dados necessários a esta etapa.
$stmt = $pdo->query(
    'SELECT *
     FROM agenda_padrao_banho_tosa
     ORDER BY dia_semana, data_inicio DESC, hora_inicio'
);
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$padroes = $stmt->fetchAll();

// Armazena a coleção "horariosTodos" carregada para ser percorrida e exibida/processada a seguir.
$horariosTodos = $pdo->query(
    'SELECT agenda_padrao_id, horario, quantidade_vagas
     FROM agenda_padrao_banho_tosa_horarios
     ORDER BY agenda_padrao_id, horario'
// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
)->fetchAll();

// Armazena o horário "horariosPorPadrao" utilizado na regra de agendamento/atendimento.
$horariosPorPadrao = [];

// Percorre cada item da coleção para processar os registros individualmente.
foreach ($horariosTodos as $item) {
    $horariosPorPadrao[(int) $item['agenda_padrao_id']][] = $item;
}

// Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
$dados = $_POST ?: ($padraoEdicao ?? []);

// Armazena o horário "horaInicioForm" utilizado na regra de agendamento/atendimento.
$horaInicioForm = (string) (
    $_POST['hora_inicio']
    ?? (
        !empty($dados['hora_inicio'])
            ? substr((string) $dados['hora_inicio'], 0, 5)
            : '09:00'
    )
);

// Armazena o horário "horaFimForm" utilizado na regra de agendamento/atendimento.
$horaFimForm = (string) (
    $_POST['hora_fim']
    ?? (
        !empty($dados['hora_fim'])
            ? substr((string) $dados['hora_fim'], 0, 5)
            : '17:00'
    )
);

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Monta a estrutura de horários e quantidades que será enviada ao JavaScript da agenda-padrão.
    $vagasParaJs = [];

    // Percorre cada item da coleção para processar os registros individualmente.
    foreach (gerarHorariosDeTrinta($horaInicioForm, $horaFimForm) as $hora) {
        $vagasParaJs[$hora] = quantidadeVagas(
            ($_POST['vagas'] ?? [])[$hora] ?? 0
        );
    }
// Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
} else {
    // Monta a estrutura de horários e quantidades que será enviada ao JavaScript da agenda-padrão.
    $vagasParaJs = $vagasEdicao;
}

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Agenda-padrão';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'agenda';
// Define o(s) CSS desta página em assets/css/ (agenda.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'agenda';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page agenda-page standard-schedule-page">
    <!-- Bloco visual "page-heading agenda-heading" que organiza esta parte da interface. -->
    <div class="page-heading agenda-heading">
        <div>
            <a class="back-link" href="agenda.php">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar para a agenda
            </a>

            <h1>Agenda-padrão de banho e tosa</h1>
            <small>
                Os horários são gerados de 30 em 30 minutos e você informa a quantidade de vagas.
            </small>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?>" role="<?= $tipoMensagem === 'error' ? 'alert' : 'status' ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
    <form class="agenda-form-card" method="post" action="agenda-padrao.php">
        <input
            type="hidden"
            name="acao"
            value="<?= $editarId !== null ? 'editar_padrao' : 'criar_padrao' ?>">

        <?php if ($editarId !== null): ?>
            <!-- Campo "id" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="id" value="<?= $editarId ?>">
        <?php endif; ?>

        <div class="agenda-card-title">
            <div>
                <h2><?= $editarId !== null ? 'Editar agenda-padrão' : 'Criar agenda-padrão' ?></h2>
                <small>
                    Não há separação por funcionário. Digite de 0 a 5 vagas para cada horário.
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
                <!-- Rótulo do campo "Dia da semana", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="diaSemanaPadrao">Dia da semana</label>
                <!-- Lista de seleção "dia_semana" com as opções válidas para este dado. -->
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
                <!-- Rótulo do campo "Das", indicando ao usuário qual informação deve ser preenchida. -->
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
                <!-- Rótulo do campo "Até", indicando ao usuário qual informação deve ser preenchida. -->
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
                <!-- Rótulo do campo "Começa a valer em", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="dataInicioPadrao">Começa a valer em</label>
                <input
                    id="dataInicioPadrao"
                    name="data_inicio"
                    type="date"
                    value="<?= escapar($dados['data_inicio'] ?? date('Y-m-d')) ?>"
                    required>
            </div>

            <div class="field span-4">
                <!-- Rótulo do campo "Duração do padrão", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="duracaoPadrao">Duração do padrão</label>
                <!-- Lista de seleção "tipo_duracao" com as opções válidas para este dado. -->
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
                <!-- Rótulo do campo "Válido até", indicando ao usuário qual informação deve ser preenchida. -->
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
                <!-- Rótulo do campo "Agenda-padrão ativa", indicando ao usuário qual informação deve ser preenchida. -->
                <label for="ativoPadrao">Agenda-padrão ativa</label>
            </div>
        </div>

        <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
        <section class="capacity-editor">
            <div class="capacity-editor-heading">
                <div>
                    <h3>Vagas por horário</h3>
                    <small>
                        Digite quantas vagas ficam disponíveis em cada bloco de 30 minutos.
                    </small>
                </div>
            </div>

            <div class="capacity-grid-header">
                <strong>Horário</strong>
                <strong>Quantidade de vagas</strong>
            </div>

            <div id="capacityGrid" class="capacity-grid"></div>
        </section>

        <div class="form-actions">
            <button class="save-button" type="submit">
                <?= $editarId !== null ? 'Salvar' : 'Criar agenda-padrão' ?>
            </button>
        </div>
    </form>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
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
                        <!-- Bloco visual "standard-pattern-summary" que organiza esta parte da interface. -->
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

                                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                                <form
                                    method="post"
                                    action="agenda-padrao.php"
                                    onsubmit="return confirm('Excluir esta agenda-padrão?');">
                                    <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                                    <input type="hidden" name="acao" value="excluir_padrao">
                                    <!-- Campo "id" utilizado para enviar esta informação no formulário. -->
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

                                        <span class="pattern-vagas-number">
                                            <?= (int) $horario['quantidade_vagas'] ?>
                                        </span>

                                        <small>
                                            vaga(s)
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

<!-- Script responsável pelas interações desta parte da página no navegador. -->
<script>
(() => {
    // Armazena em "valoresIniciais" um valor usado pela lógica executada no navegador.
    const valoresIniciais = <?= json_encode(
        $vagasParaJs,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    // Obtém a referência do elemento "inicio" para ler ou alterar seu conteúdo/comportamento.
    const inicio = document.querySelector('#horaInicioPadrao');
    // Obtém a referência do elemento "fim" para ler ou alterar seu conteúdo/comportamento.
    const fim = document.querySelector('#horaFimPadrao');
    // Obtém a referência do elemento "grid" para ler ou alterar seu conteúdo/comportamento.
    const grid = document.querySelector('#capacityGrid');
    // Obtém a referência do elemento "tipoDuracao" para ler ou alterar seu conteúdo/comportamento.
    const tipoDuracao = document.querySelector('#duracaoPadrao');
    // Obtém a referência do elemento "dataFimBox" para ler ou alterar seu conteúdo/comportamento.
    const dataFimBox = document.querySelector('#dataFimPadraoBox');
    // Obtém a referência do elemento "dataFim" para ler ou alterar seu conteúdo/comportamento.
    const dataFim = document.querySelector('#dataFimPadrao');

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.
    if (!inicio || !fim || !grid) {
        return;
    }

    // Armazena em "paraMinutos" um valor usado pela lógica executada no navegador.
    const paraMinutos = (hora) => {
        const [h, m] = hora.split(':').map(Number);
        return (h * 60) + m;
    };

    // Armazena em "paraHora" um valor usado pela lógica executada no navegador.
    const paraHora = (total) => {
        // Armazena em "h" um valor usado pela lógica executada no navegador.
        const h = Math.floor(total / 60);
        // Armazena em "m" um valor usado pela lógica executada no navegador.
        const m = total % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    };

    // Armazena em "renderizarGrade" um valor usado pela lógica executada no navegador.
    const renderizarGrade = () => {
        // Armazena em "inicioMinutos" um valor usado pela lógica executada no navegador.
        const inicioMinutos = paraMinutos(inicio.value);
        // Armazena em "fimMinutos" um valor usado pela lógica executada no navegador.
        const fimMinutos = paraMinutos(fim.value);

        grid.innerHTML = '';

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
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
            // Armazena em "minutoAtual" um valor usado pela lógica executada no navegador.
            let minutoAtual = inicioMinutos;
            minutoAtual < fimMinutos;
            minutoAtual += 30
        ) {
            // Armazena em "hora" um valor usado pela lógica executada no navegador.
            const hora = paraHora(minutoAtual);
            // Armazena em "quantidadeInicial" um valor usado pela lógica executada no navegador.
            const quantidadeInicial =
                Object.prototype.hasOwnProperty.call(valoresIniciais, hora)
                    ? Number(valoresIniciais[hora])
                    : 5;

            // Armazena em "linha" um valor usado pela lógica executada no navegador.
            const linha = document.createElement('div');
            linha.className = 'capacity-grid-row';

            // Armazena em "horaElemento" um valor usado pela lógica executada no navegador.
            const horaElemento = document.createElement('strong');
            horaElemento.className = 'capacity-time';
            horaElemento.textContent = hora;
            linha.appendChild(horaElemento);

            // Armazena em "input" um valor usado pela lógica executada no navegador.
            const input = document.createElement('input');
            input.className = 'capacity-number';
            input.type = 'number';
            input.name = `vagas[${hora}]`;
            input.min = '0';
            input.max = '5';
            input.step = '1';
            input.value = String(quantidadeInicial);
            input.required = true;
            input.setAttribute('aria-label', `Quantidade de vagas às ${hora}`);

            linha.appendChild(input);
            grid.appendChild(linha);
        }
    };

    // Armazena em "atualizarDuracao" um valor usado pela lógica executada no navegador.
    const atualizarDuracao = () => {
        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!tipoDuracao || !dataFimBox || !dataFim) {
            return;
        }

        // Armazena em "ateData" um valor usado pela lógica executada no navegador.
        const ateData = tipoDuracao.value === 'ATE_DATA';
        dataFimBox.hidden = !ateData;
        dataFim.required = ateData;

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.
        if (!ateData) {
            dataFim.value = '';
        }
    };

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    inicio.addEventListener('change', renderizarGrade);
    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
    fim.addEventListener('change', renderizarGrade);

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.
    if (tipoDuracao) {
        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.
        tipoDuracao.addEventListener('change', atualizarDuracao);
    }

    renderizarGrade();
    atualizarDuracao();
})();
</script>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
