<?php

/**
 * ARQUIVO: agenda.php
 * RESPONSABILIDADE: Agenda diária do sistema web.
 * Cria, edita, exclui e altera o status dos agendamentos; separa os atendimentos por categoria; calcula disponibilidade de banho e tosa; exibe a agenda por dia.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';
// Recupera os dados do usuário autenticado para saber quem está operando o sistema.
$usuarioAtual = obterUsuarioAtual($pdo);
// Guarda somente o ID do usuário atual para registrar autoria das operações no banco.
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;
// Indica se o usuário possui perfil de administrador e, portanto, acesso às funções restritas.
$administrador = ($usuarioAtual['perfil'] ?? '') === 'ADMINISTRADOR';

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
// Define a data que a página irá consultar/exibir, usando a data recebida na URL ou o dia atual.
$dataSelecionada = (string) ($_GET['data'] ?? date('Y-m-d'));
// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? null);

/**
 * Transforma o nome textual da categoria em uma chave padronizada usada pela lógica e pela interface da agenda.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
function chaveCategoriaAgenda(string $categoria): string
{
    // Armazena em "categoria" o valor necessário para as próximas etapas deste fluxo.
    $categoria = normalizarCategoriaAgenda($categoria);

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($categoria, 'creche')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'creche';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($categoria, 'hotel')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'hotel';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($categoria, 'taxi')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'taxidog';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($categoria, 'veterin') || str_contains($categoria, 'consulta')) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'veterinario';
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (ehBanhoTosa($categoria)) {
        // Devolve o resultado calculado para o ponto que chamou esta função.
        return 'banho_tosa';
    }

    // Devolve o resultado calculado para o ponto que chamou esta função.
    return 'outro';
}

/**
 * Gera os botões de editar, iniciar, concluir e excluir para um agendamento.
 * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
 */
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
            <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
            <form method="post" action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
                <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                <input type="hidden" name="acao" value="alterar_status">
                <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
                <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                <!-- Campo "status" utilizado para enviar esta informação no formulário. -->
                <input type="hidden" name="status" value="EM_ATENDIMENTO">
                <button class="small-action-button start" type="submit">Iniciar</button>
            </form>
        <?php elseif ($item['status'] === 'EM_ATENDIMENTO'): ?>
            <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
            <form method="post" action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
                <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                <input type="hidden" name="acao" value="alterar_status">
                <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
                <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                <!-- Campo "status" utilizado para enviar esta informação no formulário. -->
                <input type="hidden" name="status" value="CONCLUIDO">
                <button class="small-action-button finish" type="submit">Concluir</button>
            </form>
        <?php endif; ?>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form
            method="post"
            action="agenda.php?data=<?= escapar($dataSelecionada) ?>"
            onsubmit="return confirm('Excluir este agendamento definitivamente?');">
            <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="acao" value="excluir_agendamento">
            <!-- Campo "agendamento_id" utilizado para enviar esta informação no formulário. -->
            <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
            <button class="icon-action-button danger" type="submit" title="Excluir">
                <i class="fa-solid fa-trash"></i>
            </button>
        </form>
    </div>
<?php
}

// Confere se a data recebida pela URL é válida; se não for, a página volta para a data atual.
if (!dataSqlValida($dataSelecionada)) {
    // Define a data que a página irá consultar/exibir, usando a data recebida na URL ou o dia atual.
    $dataSelecionada = date('Y-m-d');
}

// Carrega o agendamento que será alterado para preencher o formulário com os dados atuais.
$agendamentoEdicao = $editarId !== null
    ? buscarAgendamentoEdicao($pdo, $editarId)
    : null;

// Detecta quando foi solicitado editar um agendamento que não existe mais ou possui ID inválido.
if ($editarId !== null && $agendamentoEdicao === null) {
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'O agendamento selecionado não foi encontrado.';
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'error';
    // Lê o identificador enviado pela URL para saber se a página está em modo de edição.
    $editarId = null;
}

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica qual ação o formulário solicitou para direcionar o processamento correto.
    $acao = (string) ($_POST['acao'] ?? '');

    // Verifica a condição abaixo antes de executar este bloco.
    if (in_array($acao, ['criar_agendamento', 'editar_agendamento'], true)) {
        // Guarda o ID do agendamento criado ou selecionado para edição, status ou exclusão.
        $agendamentoId = $acao === 'editar_agendamento'
            ? inteiroOuNull($_POST['agendamento_id'] ?? null)
            : null;

        // Converte o identificador do pet selecionado para um inteiro válido.
        $petId = inteiroOuNull($_POST['pet_id'] ?? null);
        // Converte o identificador do serviço selecionado para um inteiro válido.
        $servicoId = inteiroOuNull($_POST['servico_id'] ?? null);
        // Guarda o horário digitado manualmente para serviços que não utilizam a grade de banho e tosa.
        $horaManual = (string) ($_POST['hora'] ?? '');
        // Guarda o horário escolhido dentro da grade de vagas de banho e tosa.
        $horaBanho = (string) ($_POST['hora_banho'] ?? '');
        // Guarda a duração escolhida para o serviço, em minutos, para calcular quantos blocos de 30 minutos serão necessários.
        $duracaoMinutos = inteiroOuNull($_POST['duracao_minutos'] ?? null);
        // Guarda a data de término quando o serviço ocupa mais de um dia, como ocorre no hotel.
        $dataFim = textoOuNull($_POST['data_fim'] ?? null);
        // Guarda o horário de término quando o atendimento possui intervalo de início e fim.
        $horaFim = textoOuNull($_POST['hora_fim'] ?? null);
        // Guarda observações informadas pela equipe para acompanhar o cadastro, agendamento ou lançamento.
        $observacoes = textoOuNull($_POST['observacoes'] ?? null);

        // Carrega os dados do serviço selecionado para validar categoria, regras e comportamento do formulário.
        $servico = $servicoId !== null
            ? buscarServicoAgenda($pdo, $servicoId)
            : null;

        // Impede continuar enquanto pet e serviço obrigatórios não tiverem sido selecionados corretamente.
        if ($petId === null || $servico === null) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Selecione o pet e o serviço.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Indica se o serviço selecionado pertence ao fluxo especial de banho e tosa com controle de vagas.
            $banhoTosa = ehBanhoTosa($servico['categoria'], $servico['nome']);
            // Guarda o horário efetivo de início que será salvo no agendamento ou atendimento.
            $horaInicio = null;
            // Guarda a data e hora completas de término do agendamento quando houver.
            $fim = null;

            // Verifica a condição abaixo antes de executar este bloco.
            if ($banhoTosa) {
                // Armazena a configuração de agenda-padrão válida para a data que está sendo processada.
                $padrao = buscarPadraoBanhoDaData($pdo, $dataSelecionada);
                // Guarda os blocos de horário e a capacidade de vagas definidos na agenda-padrão.
                $horariosPadrao = $padrao
                    ? buscarHorariosPadraoBanho($pdo, (int) $padrao['id'])
                    : [];

                // Guarda quantas vagas já estão ocupadas em cada horário para impedir sobreposição da capacidade.
                $ocupacao = buscarOcupacaoBanhoTosa(
                    $pdo,
                    $dataSelecionada,
                    $horariosPadrao,
                    $agendamentoId
                );

                // Confere se existe uma agenda-padrão válida com horários configurados para a data escolhida.
                if ($padrao === null || !$horariosPadrao) {
                    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                    $mensagem = 'Não existe agenda-padrão de banho e tosa para esta data.';
                    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                    $tipoMensagem = 'error';
                    // Garante que a duração do banho e tosa tenha sido informada antes de calcular as vagas necessárias.
                } elseif ($duracaoMinutos === null) {
                    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                    $mensagem = 'Selecione a duração do banho e tosa.';
                    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                    $tipoMensagem = 'error';
                    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                } else {
                    [$disponivel, $erroVaga] = validarVagasBanhoTosa(
                        $horariosPadrao,
                        $ocupacao,
                        $horaBanho,
                        $duracaoMinutos
                    );

                    // Bloqueia o agendamento quando algum dos blocos necessários não possui vaga disponível.
                    if (!$disponivel) {
                        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                        $mensagem = $erroVaga;
                        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                        $tipoMensagem = 'error';
                        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                    } else {
                        // Guarda o horário efetivo de início que será salvo no agendamento ou atendimento.
                        $horaInicio = $horaBanho;
                        // Armazena em "inicioTemporario" o valor necessário para as próximas etapas deste fluxo.
                        $inicioTemporario = new DateTime(
                            $dataSelecionada . ' ' . $horaBanho . ':00'
                        );
                        // Guarda a data e hora completas de término do agendamento quando houver.
                        $fim = (clone $inicioTemporario)
                            ->modify('+' . $duracaoMinutos . ' minutes')
                            ->format('Y-m-d H:i:s');
                    }
                }
                // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Armazena em "categoriaAgenda" o valor necessário para as próximas etapas deste fluxo.
                $categoriaAgenda = chaveCategoriaAgenda((string) $servico['categoria']);

                // Creche é um compromisso do dia: não precisa escolher horário.
                if ($categoriaAgenda === 'creche') {
                    // Guarda o horário efetivo de início que será salvo no agendamento ou atendimento.
                    $horaInicio = '00:00';
                }

                // Hotel trabalha com datas. O horário fica apenas técnico no banco.
                elseif ($categoriaAgenda === 'hotel') {
                    // Guarda o horário efetivo de início que será salvo no agendamento ou atendimento.
                    $horaInicio = '00:00';

                    // Verifica a condição abaixo antes de executar este bloco.
                    if ($dataFim === null || !dataSqlValida($dataFim)) {
                        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                        $mensagem = 'Informe a data de saída do hotel.';
                        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                        $tipoMensagem = 'error';
                        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
                    } elseif ($dataFim < $dataSelecionada) {
                        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                        $mensagem = 'A data de saída não pode ser anterior à entrada.';
                        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                        $tipoMensagem = 'error';
                        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                    } else {
                        // Guarda a data e hora completas de término do agendamento quando houver.
                        $fim = $dataFim . ' 23:59:00';
                    }
                }

                // Consulta e Taxidog não têm grade de vagas, mas podem ter horário manual.
                else {
                    // Verifica a condição abaixo antes de executar este bloco.
                    if (!horaSqlValida($horaManual)) {
                        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                        $mensagem = 'Informe um horário válido.';
                        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                        $tipoMensagem = 'error';
                        // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                    } else {
                        // Guarda o horário efetivo de início que será salvo no agendamento ou atendimento.
                        $horaInicio = $horaManual;
                    }
                }
            }

            // Só continua para a próxima etapa quando nenhuma validação anterior gerou mensagem de erro.
            if ($mensagem === '' && $horaInicio !== null) {
                // Monta a data e hora completas de início no formato esperado pelo banco de dados.
                $inicio = $dataSelecionada . ' ' . $horaInicio . ':00';

                // Garante que o término seja realmente posterior ao início do atendimento.
                if ($fim !== null && strtotime($fim) <= strtotime($inicio)) {
                    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                    $mensagem = 'O término deve ser posterior ao início.';
                    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                    $tipoMensagem = 'error';
                    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                } else {
                    // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
                    try {
                        // Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
                        $stmt = $pdo->prepare(
                            'SELECT COUNT(*)
                             FROM pets
                             WHERE id = :id
                               AND ativo = TRUE'
                        );
                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                        $stmt->execute([':id' => $petId]);

                        // Verifica a condição abaixo antes de executar este bloco.
                        if ((int) $stmt->fetchColumn() === 0) {
                            // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                            throw new RuntimeException(
                                'O pet selecionado não foi encontrado.'
                            );
                        }

                        // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
                        $pdo->beginTransaction();

                        // Verifica a condição abaixo antes de executar este bloco.
                        if ($acao === 'criar_agendamento') {
                            // Prepara a inclusão de um novo registro em "agendamentos" usando os valores já validados.
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

                            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                            $stmt->execute([
                                ':pet_id'      => $petId,
                                ':inicio'      => $inicio,
                                ':fim'         => $fim,
                                ':origem'      => 'INTERNO',
                                ':status'      => 'CONFIRMADO',
                                ':observacoes' => $observacoes,
                                ':criado_por'  => $usuarioId,
                            ]);

                            // Guarda o ID do agendamento criado ou selecionado para edição, status ou exclusão.
                            $agendamentoId = (int) $pdo->lastInsertId();
                            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
                        } else {
                            // Valida a existência do identificador antes de alterar ou excluir um agendamento.
                            if ($agendamentoId === null) {
                                // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                                throw new RuntimeException(
                                    'Agendamento inválido para edição.'
                                );
                            }

                            // Prepara a inclusão de um novo registro em "agendamento_itens" usando os valores já validados.
                            $stmt = $pdo->prepare(
                                'UPDATE agendamentos
                                 SET pet_id = :pet_id,
                                     data_hora_inicio = :inicio,
                                     data_hora_fim = :fim,
                                     observacoes_internas = :observacoes
                                 WHERE id = :id'
                            );

                            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                            $stmt->execute([
                                ':pet_id'      => $petId,
                                ':inicio'      => $inicio,
                                ':fim'         => $fim,
                                ':observacoes' => $observacoes,
                                ':id'          => $agendamentoId,
                            ]);

                            // Prepara a consulta SQL para receber parâmetros sem concatenar valores diretamente no comando.
                            $pdo->prepare(
                                'DELETE FROM agendamento_itens
                                 WHERE agendamento_id = :id'
                                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                            )->execute([':id' => $agendamentoId]);
                        }

                        // Prepara a inclusão de um novo registro em "agendamento_itens" usando os valores já validados.
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

                        // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                        $stmt->execute([
                            ':agendamento_id' => $agendamentoId,
                            ':servico_id'      => $servicoId,
                            ':observacoes'     => $observacoes,
                        ]);

                        // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
                        $pdo->commit();

                        // Armazena em "parametro" o valor necessário para as próximas etapas deste fluxo.
                        $parametro = $acao === 'criar_agendamento'
                            ? 'salvo=1'
                            : 'editado=1';

                        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                        header(
                            'Location: agenda.php?data='
                                . urlencode($dataSelecionada)
                                . '&'
                                . $parametro
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
                        $mensagem = $erro instanceof RuntimeException
                            ? $erro->getMessage()
                            : 'Não foi possível salvar o agendamento.';
                        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                        $tipoMensagem = 'error';
                    }
                }
            }
        }
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($acao === 'alterar_status') {
        // Guarda o ID do agendamento criado ou selecionado para edição, status ou exclusão.
        $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);
        // Guarda o status solicitado e permite validar se a transição é aceita antes de atualizar o banco.
        $novoStatus = (string) ($_POST['status'] ?? '');
        // Armazena a coleção "statusPermitidos" carregada para ser percorrida e exibida/processada a seguir.
        $statusPermitidos = ['EM_ATENDIMENTO', 'CONCLUIDO', 'CANCELADO'];

        // Verifica a condição abaixo antes de executar este bloco.
        if (
            // Valida a existência do identificador antes de alterar ou excluir um agendamento.
            $agendamentoId === null
            || !in_array($novoStatus, $statusPermitidos, true)
        ) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Não foi possível atualizar esse agendamento.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Prepara a atualização do registro correspondente em "agendamentos" com os novos dados.
            $stmt = $pdo->prepare(
                'UPDATE agendamentos
                 SET status = :status,
                     respondido_por = :usuario_id,
                     respondido_em = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );

            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([
                ':status'     => $novoStatus,
                ':usuario_id' => $usuarioId,
                ':id'         => $agendamentoId,
            ]);

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header(
                'Location: agenda.php?data='
                    . urlencode($dataSelecionada)
                    . '&atualizado=1'
            );
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        }
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if ($acao === 'excluir_agendamento') {
        // Guarda o ID do agendamento criado ou selecionado para edição, status ou exclusão.
        $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);

        // Valida a existência do identificador antes de alterar ou excluir um agendamento.
        if ($agendamentoId === null) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = 'Agendamento inválido para exclusão.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
        } else {
            // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
            try {
                // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
                $pdo->beginTransaction();

                // Prepara a consulta SQL para receber parâmetros sem concatenar valores diretamente no comando.
                $pdo->prepare(
                    'DELETE FROM agendamento_itens
                     WHERE agendamento_id = :id'
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                )->execute([':id' => $agendamentoId]);

                // Prepara a consulta SQL para receber parâmetros sem concatenar valores diretamente no comando.
                $pdo->prepare(
                    'DELETE FROM agendamentos
                     WHERE id = :id'
                    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                )->execute([':id' => $agendamentoId]);

                // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
                $pdo->commit();

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header(
                    'Location: agenda.php?data='
                        . urlencode($dataSelecionada)
                        . '&excluido=1'
                );
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
                // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
            } catch (Throwable) {
                // Confere se existe uma transação aberta antes de tentar desfazê-la.
                if ($pdo->inTransaction()) {
                    // Desfaz as alterações da transação atual porque alguma etapa falhou.
                    $pdo->rollBack();
                }

                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Não foi possível excluir o agendamento.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
            }
        }
    }
}

// Trata os parâmetros da URL usados principalmente pdesara exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verifica a condição abaixo antes de executar este bloco.
    if (isset($_GET['salvo'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Agendamento cadastrado com sucesso!';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['editado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Agendamento atualizado com sucesso!';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['excluido'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Agendamento excluído com sucesso!';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['atualizado'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Status do agendamento atualizado.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'success';
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (isset($_GET['sem_permissao'])) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Somente administradores podem configurar a agenda-padrão.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    }
}

// Reúne os serviços ativos disponíveis para seleção, classificação ou resumo.
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
       AND LOWER(cs.nome) NOT IN ('loja', 'financeiro')
     ORDER BY cs.nome, s.nome"
    // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
)->fetchAll();

// Calcula/armazena o total de "totalSolicitacoes" que será usado nos resumos e na interface.
$totalSolicitacoes = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM agendamentos
     WHERE origem = 'MOBILE'
       AND status = 'SOLICITADO'"
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
)->fetchColumn();

// Armazena em "padraoBanho" o valor necessário para as próximas etapas deste fluxo.
$padraoBanho = buscarPadraoBanhoDaData($pdo, $dataSelecionada);
// Armazena o horário "horariosPadraoBanho" utilizado na regra de agendamento/atendimento.
$horariosPadraoBanho = $padraoBanho
    ? buscarHorariosPadraoBanho($pdo, (int) $padraoBanho['id'])
    : [];

// Armazena em "ocupacaoBanho" o valor necessário para as próximas etapas deste fluxo.
$ocupacaoBanho = buscarOcupacaoBanhoTosa(
    $pdo,
    $dataSelecionada,
    $horariosPadraoBanho,
    $editarId
);

// Armazena a coleção "slotsBanhoJs" carregada para ser percorrida e exibida/processada a seguir.
$slotsBanhoJs = [];

// Percorre cada item da coleção para processar os registros individualmente.
foreach ($horariosPadraoBanho as $hora => $capacidade) {
    // Armazena a coleção "ocupadas" carregada para ser percorrida e exibida/processada a seguir.
    $ocupadas = (int) ($ocupacaoBanho[$hora] ?? 0);

    $slotsBanhoJs[] = [
        'hora'       => $hora,
        'capacidade' => $capacidade,
        'ocupadas'   => $ocupadas,
        'restantes'  => max(0, $capacidade - $ocupadas),
    ];
}

// Prepara uma consulta em "agendamentos" para carregar os dados necessários a esta etapa.
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
        STRING_AGG(DISTINCT s.nome, ', ' ORDER BY s.nome) AS servicos,
        STRING_AGG(DISTINCT cs.nome, ', ' ORDER BY cs.nome) AS categorias
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
            a.data_hora_inicio::date = :data_inicio
            OR (
                a.data_hora_fim IS NOT NULL
                AND :data_intervalo BETWEEN
                    a.data_hora_inicio::date
                    AND a.data_hora_fim::date
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

// Executa a instrução preparada enviando os parâmetros de forma separada e segura.
$stmt->execute([
    ':data_inicio'    => $dataSelecionada,
    ':data_intervalo' => $dataSelecionada,
]);

// Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
$agendamentos = $stmt->fetchAll();

// Armazena a coleção "grupos" carregada para ser percorrida e exibida/processada a seguir.
$grupos = [
    'creche'      => [],
    'hotel'       => [],
    'banho_tosa'  => [],
    'veterinario' => [],
    'taxidog'     => [],
];

// Percorre cada item da coleção para processar os registros individualmente.
foreach ($agendamentos as $agendamento) {
    // Armazena em "identificacao" o valor necessário para as próximas etapas deste fluxo.
    $identificacao = normalizarCategoriaAgenda(
        ($agendamento['categorias'] ?? '')
            . ' '
            . ($agendamento['servicos'] ?? '')
    );

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($identificacao, 'loja')) {
        continue;
    }

    // Verifica a condição abaixo antes de executar este bloco.
    if (str_contains($identificacao, 'hotel')) {
        $grupos['hotel'][] = $agendamento;
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (str_contains($identificacao, 'creche')) {
        $grupos['creche'][] = $agendamento;
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (
        str_contains($identificacao, 'taxi')
        || str_contains($identificacao, 'taxidog')
    ) {
        $grupos['taxidog'][] = $agendamento;
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (
        str_contains($identificacao, 'veterin')
        || str_contains($identificacao, 'consulta')
    ) {
        $grupos['veterinario'][] = $agendamento;
        // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (ehBanhoTosa($identificacao)) {
        $grupos['banho_tosa'][] = $agendamento;
    }
}

// Calcula a data anterior para permitir navegar para o período precedente.
$dataAnterior = (new DateTime($dataSelecionada))
    ->modify('-1 day')
    ->format('Y-m-d');

// Calcula a data seguinte para permitir navegar para o próximo período.
$dataSeguinte = (new DateTime($dataSelecionada))
    ->modify('+1 day')
    ->format('Y-m-d');

// Guarda uma condição booleana em "mostrarFormulario" para controlar o fluxo ou a exibição da página.
$mostrarFormulario = isset($_GET['novo'])
    || $editarId !== null
    || ($mensagem !== '' && $tipoMensagem === 'error');

// Armazena em "dadosFormulario" o valor necessário para as próximas etapas deste fluxo.
$dadosFormulario = $agendamentoEdicao ?? [];

// Preserva o valor selecionado de "servicoSelecionado" para preencher o formulário corretamente.
$servicoSelecionado = (string) (
    $_POST['servico_id']
    ?? $dadosFormulario['servico_id']
    ?? ''
);

// Preserva o valor selecionado de "petSelecionado" para preencher o formulário corretamente.
$petSelecionado = (string) (
    $_POST['pet_id']
    ?? $dadosFormulario['pet_id']
    ?? ''
);

// Preserva o valor selecionado de "petSelecionadoId" para preencher o formulário corretamente.
$petSelecionadoId = inteiroOuNull($petSelecionado);
// Preserva o valor selecionado de "petBuscaSelecionado" para preencher o formulário corretamente.
$petBuscaSelecionado = buscarPetParaCampo($pdo, $petSelecionadoId);
// Preserva o valor selecionado de "rotuloPetSelecionado" para preencher o formulário corretamente.
$rotuloPetSelecionado = $petBuscaSelecionado
    ? $petBuscaSelecionado['nome'] . ' — ' . $petBuscaSelecionado['tutor']
    : '';

// Preserva o valor selecionado de "horaManualSelecionada" para preencher o formulário corretamente.
$horaManualSelecionada = (string) (
    $_POST['hora']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
        ? substr((string) $dadosFormulario['data_hora_inicio'], 11, 5)
        : ''
    )
);

// Preserva o valor selecionado de "horaBanhoSelecionada" para preencher o formulário corretamente.
$horaBanhoSelecionada = (string) (
    $_POST['hora_banho']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
        ? substr((string) $dadosFormulario['data_hora_inicio'], 11, 5)
        : ''
    )
);

// Preserva o valor selecionado de "duracaoSelecionada" para preencher o formulário corretamente.
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

// Preserva o valor selecionado de "dataFimSelecionada" para preencher o formulário corretamente.
$dataFimSelecionada = (string) (
    $_POST['data_fim']
    ?? (
        !empty($dadosFormulario['data_hora_fim'])
        ? substr((string) $dadosFormulario['data_hora_fim'], 0, 10)
        : ''
    )
);

// Preserva o valor selecionado de "horaFimSelecionada" para preencher o formulário corretamente.
$horaFimSelecionada = (string) (
    $_POST['hora_fim']
    ?? (
        !empty($dadosFormulario['data_hora_fim'])
        ? substr((string) $dadosFormulario['data_hora_fim'], 11, 5)
        : ''
    )
);

// Armazena a coleção "observacoesSelecionadas" carregada para ser percorrida e exibida/processada a seguir.
$observacoesSelecionadas = (string) (
    $_POST['observacoes']
    ?? $dadosFormulario['observacoes_internas']
    ?? ''
);

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Agenda';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'agenda';
// Define o(s) CSS desta página em assets/css/ (agenda.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'agenda';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page agenda-page google-agenda-page">
    <!-- Bloco visual "page-heading agenda-heading" que organiza esta parte da interface. -->
    <div class="page-heading agenda-heading">
        <div>
            <h1>Agenda</h1>
            <small>Clique em um horário ou em uma categoria para adicionar um agendamento.</small>
        </div>

        <!-- Bloco visual "agenda-heading-actions" que organiza esta parte da interface. -->
        <div class="agenda-heading-actions">
            <?php if ($administrador): ?>
                <a class="secondary-action-button" href="agenda-padrao.php">
                    <i class="fa-solid fa-sliders"></i>
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
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <div class="google-calendar-toolbar">
        <a class="calendar-today-button" href="agenda.php?data=<?= escapar(date('Y-m-d')) ?>">Hoje</a>

        <div class="calendar-nav-buttons">
            <a href="agenda.php?data=<?= escapar($dataAnterior) ?>" aria-label="Dia anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a href="agenda.php?data=<?= escapar($dataSeguinte) ?>" aria-label="Dia seguinte">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form class="calendar-date-form" method="get" action="agenda.php">
            <!-- Rótulo do campo "Data", indicando ao usuário qual informação deve ser preenchida. -->
            <label for="dataAgenda">Data</label>
            <!-- Campo "data" utilizado para enviar esta informação no formulário. -->
            <input id="dataAgenda" name="data" type="date" value="<?= escapar($dataSelecionada) ?>" onchange="this.form.submit()">
        </form>

        <strong class="calendar-date-title">
            <?= escapar(formatarData($dataSelecionada)) ?>
        </strong>
    </div>

    <?php
    // Armazena em "banhosPorHora" o valor necessário para as próximas etapas deste fluxo.
    $banhosPorHora = [];

    // Percorre cada item da coleção para processar os registros individualmente.
    foreach ($grupos['banho_tosa'] as $itemBanho) {
        // Armazena o horário "horaItem" utilizado na regra de agendamento/atendimento.
        $horaItem = substr((string) $itemBanho['data_hora_inicio'], 11, 5);
        $banhosPorHora[$horaItem][] = $itemBanho;
    }
    ?>

    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
    <section class="google-calendar-card bath-calendar-card">
        <div class="google-calendar-card-header">
            <div>
                <span class="calendar-category-icon"><i class="fa-solid fa-scissors"></i></span>
                <div>
                    <h2>Banho e tosa</h2>
                    <small>Clique em um horário livre para agendar.</small>
                </div>
            </div>
            <span class="calendar-count"><?= count($grupos['banho_tosa']) ?> agendamento(s)</span>
        </div>

        <?php if (!$horariosPadraoBanho): ?>
            <div class="calendar-no-schedule">
                <i class="fa-regular fa-calendar-xmark"></i>
                <div>
                    <strong>Sem horários configurados para esta data.</strong>
                    <small>Cadastre a agenda-padrão para liberar os horários de banho e tosa.</small>
                </div>
                <?php if ($administrador): ?>
                    <a href="agenda-padrao.php">Configurar</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="google-time-grid">
                <?php foreach ($slotsBanhoJs as $slot): ?>
                    <?php
                    // Armazena o horário "horaSlot" utilizado na regra de agendamento/atendimento.
                    $horaSlot = (string) $slot['hora'];
                    // Armazena em "restantesSlot" o valor necessário para as próximas etapas deste fluxo.
                    $restantesSlot = (int) $slot['restantes'];
                    // Armazena em "itensDoHorario" o valor necessário para as próximas etapas deste fluxo.
                    $itensDoHorario = $banhosPorHora[$horaSlot] ?? [];
                    ?>
                    <div class="google-time-row <?= $restantesSlot <= 0 ? 'is-full' : '' ?>">
                        <div class="google-time-label"><?= escapar($horaSlot) ?></div>

                        <div class="google-time-content">
                            <?php if ($itensDoHorario): ?>
                                <div class="time-events">
                                    <?php foreach ($itensDoHorario as $item): ?>
                                        <?php
                                        // Armazena em "duracaoItem" o valor necessário para as próximas etapas deste fluxo.
                                        $duracaoItem = null;
                                        // Verifica a condição abaixo antes de executar este bloco.
                                        if (!empty($item['data_hora_fim'])) {
                                            // Armazena em "duracaoItem" o valor necessário para as próximas etapas deste fluxo.
                                            $duracaoItem = (int) ((
                                                strtotime((string) $item['data_hora_fim'])
                                                - strtotime((string) $item['data_hora_inicio'])
                                            ) / 60);
                                        }
                                        ?>
                                        <article class="calendar-event bath-event">
                                            <div class="calendar-event-main">
                                                <strong><?= escapar($item['pet']) ?></strong>
                                                <span><?= escapar($item['servicos'] ?: 'Banho e tosa') ?></span>
                                                <small>
                                                    <?= escapar($item['tutor']) ?>
                                                    <?php if ($duracaoItem !== null): ?>
                                                        • <?= escapar(formatarDuracaoAgenda($duracaoItem)) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>

                                            <span class="status-badge <?= escapar(classeStatusAgendamento($item['status'])) ?>">
                                                <?= escapar(nomeStatusAgendamento($item['status'])) ?>
                                            </span>

                                            <?php renderizarAcoesAgenda($item, $dataSelecionada); ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <button
                                class="time-add-button"
                                type="button"
                                data-open-agenda
                                data-category="banho_tosa"
                                data-time="<?= escapar($horaSlot) ?>"
                                <?= $restantesSlot <= 0 ? 'disabled' : '' ?>>
                                <?php if ($restantesSlot > 0): ?>
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Adicionar</span>
                                    <small><?= $restantesSlot ?> vaga(s) livre(s)</small>
                                <?php else: ?>
                                    <span>Lotado</span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php
    // Armazena em "categoriasDia" o valor necessário para as próximas etapas deste fluxo.
    $categoriasDia = [
        'creche' => [
            'titulo' => 'Creche',
            'icone' => 'fa-solid fa-house',
            'itens' => $grupos['creche'],
            'textoAdicionar' => 'Adicionar pet na creche',
            'vazio' => 'Nenhum pet na creche nesta data.',
        ],
        'hotel' => [
            'titulo' => 'Hotel',
            'icone' => 'fa-solid fa-hotel',
            'itens' => $grupos['hotel'],
            'textoAdicionar' => 'Adicionar hospedagem',
            'vazio' => 'Nenhuma hospedagem nesta data.',
        ],
        'veterinario' => [
            'titulo' => 'Consulta veterinária',
            'icone' => 'fa-solid fa-stethoscope',
            'itens' => $grupos['veterinario'],
            'textoAdicionar' => 'Adicionar consulta',
            'vazio' => 'Nenhuma consulta nesta data.',
        ],
        'taxidog' => [
            'titulo' => 'Taxidog',
            'icone' => 'fa-solid fa-car-side',
            'itens' => $grupos['taxidog'],
            'textoAdicionar' => 'Adicionar Taxidog',
            'vazio' => 'Nenhum Taxidog nesta data.',
        ],
    ];
    ?>

    <div class="calendar-category-grid">
        <?php foreach ($categoriasDia as $chave => $categoria): ?>
            <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
            <section class="google-calendar-card category-day-card">
                <button
                    class="category-add-header"
                    type="button"
                    data-open-agenda
                    data-category="<?= escapar($chave) ?>">
                    <span class="calendar-category-icon"><i class="<?= escapar($categoria['icone']) ?>"></i></span>
                    <span class="category-heading-text">
                        <strong><?= escapar($categoria['titulo']) ?></strong>
                        <small><?= count($categoria['itens']) ?> agendamento(s)</small>
                    </span>
                    <span class="category-plus"><i class="fa-solid fa-plus"></i></span>
                </button>

                <div class="category-day-events">
                    <?php if (!$categoria['itens']): ?>
                        <button
                            class="category-empty-add"
                            type="button"
                            data-open-agenda
                            data-category="<?= escapar($chave) ?>">
                            <i class="fa-solid fa-plus"></i>
                            <?= escapar($categoria['textoAdicionar']) ?>
                        </button>
                    <?php else: ?>
                        <?php foreach ($categoria['itens'] as $item): ?>
                            <article class="calendar-event category-event">
                                <div class="calendar-event-main">
                                    <strong><?= escapar($item['pet']) ?></strong>
                                    <span><?= escapar($item['servicos'] ?: $categoria['titulo']) ?></span>
                                    <small>
                                        <?= escapar($item['tutor']) ?>

                                        <?php if ($chave === 'hotel'): ?>
                                            • até <?= escapar(formatarData(substr((string) $item['data_hora_fim'], 0, 10))) ?>
                                        <?php elseif ($chave === 'veterinario' || $chave === 'taxidog'): ?>
                                            • <?= escapar(formatarHoraAgenda($item['data_hora_inicio'])) ?>
                                        <?php endif; ?>
                                    </small>

                                    <?php if ($chave === 'taxidog'): ?>
                                        <small class="agenda-address">
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?= escapar(enderecoAbreviadoAgenda($item)) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <span class="status-badge <?= escapar(classeStatusAgendamento($item['status'])) ?>">
                                    <?= escapar(nomeStatusAgendamento($item['status'])) ?>
                                </span>

                                <?php renderizarAcoesAgenda($item, $dataSelecionada); ?>
                            </article>
                        <?php endforeach; ?>

                        <button
                            class="category-inline-add"
                            type="button"
                            data-open-agenda
                            data-category="<?= escapar($chave) ?>">
                            <i class="fa-solid fa-plus"></i>
                            <?= escapar($categoria['textoAdicionar']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<div
    class="agenda-event-modal <?= $mostrarFormulario ? 'is-open' : '' ?>"
    id="agendaEventModal"
    aria-hidden="<?= $mostrarFormulario ? 'false' : 'true' ?>">
    <div class="agenda-event-dialog" role="dialog" aria-modal="true" aria-labelledby="agendaModalTitle">
        <!-- Bloco visual "agenda-modal-header" que organiza esta parte da interface. -->
        <div class="agenda-modal-header">
            <div>
                <span class="agenda-modal-category" id="agendaModalCategory">Novo agendamento</span>
                <h2 id="agendaModalTitle"><?= $editarId !== null ? 'Editar agendamento' : 'Adicionar agendamento' ?></h2>
            </div>
            <button class="agenda-modal-close" id="agendaModalClose" type="button" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
        <form
            class="agenda-modal-form"
            id="agendaEventForm"
            method="post"
            action="agenda.php?data=<?= escapar($dataSelecionada) ?>"
            data-editing="<?= $editarId !== null ? '1' : '0' ?>">
            <input
                id="agendaFormAction"
                type="hidden"
                name="acao"
                value="<?= $editarId !== null ? 'editar_agendamento' : 'criar_agendamento' ?>">
            <input
                id="agendaFormId"
                type="hidden"
                name="agendamento_id"
                value="<?= $editarId !== null ? (int) $editarId : '' ?>">

            <!-- Bloco visual "agenda-modal-grid" que organiza esta parte da interface. -->
            <div class="agenda-modal-grid">
                <div class="field span-12">
                    <!-- Rótulo do campo "Pet", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="petAgendaBusca">Pet</label>
                    <div
                        class="search-picker"
                        data-search-picker="pet"
                        data-required="1">
                        <!-- Bloco visual "search-picker-input-wrap" que organiza esta parte da interface. -->
                        <div class="search-picker-input-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input
                                id="petAgendaBusca"
                                data-search-input
                                type="search"
                                autocomplete="off"
                                value="<?= escapar($rotuloPetSelecionado) ?>"
                                data-selected-label="<?= escapar($rotuloPetSelecionado) ?>"
                                placeholder="Digite o nome do pet ou do tutor"
                                required>
                            <input
                                id="petAgenda"
                                data-search-value
                                type="hidden"
                                name="pet_id"
                                value="<?= $petSelecionadoId !== null ? (int) $petSelecionadoId : '' ?>">
                        </div>
                        <!-- Bloco visual "search-picker-results" que organiza esta parte da interface. -->
                        <div class="search-picker-results" data-search-results hidden></div>
                    </div>
                </div>

                <div class="field span-12">
                    <!-- Rótulo do campo "Serviço", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="servicoAgenda">Serviço</label>
                    <!-- Lista de seleção "servico_id" com as opções válidas para este dado. -->
                    <select id="servicoAgenda" name="servico_id" required>
                        <option value="">Selecione o serviço</option>
                        <?php foreach ($servicos as $servico): ?>
                            <?php $chaveServico = chaveCategoriaAgenda((string) $servico['categoria']); ?>
                            <option
                                value="<?= (int) $servico['id'] ?>"
                                data-category="<?= escapar($chaveServico) ?>"
                                data-banho-tosa="<?= $chaveServico === 'banho_tosa' ? '1' : '0' ?>"
                                <?= $servicoSelecionado === (string) $servico['id'] ? 'selected' : '' ?>>
                                <?= escapar($servico['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field span-6">
                    <label>Data</label>
                    <input type="date" value="<?= escapar($dataSelecionada) ?>" readonly>
                </div>

                <div class="field span-6 bath-schedule-field" id="duracaoBanhoBox">
                    <!-- Rótulo do campo "Duração", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="duracaoBanho">Duração</label>
                    <!-- Lista de seleção "duracao_minutos" com as opções válidas para este dado. -->
                    <select id="duracaoBanho" name="duracao_minutos">
                        <?php for ($minutos = 30; $minutos <= 480; $minutos += 30): ?>
                            <option
                                value="<?= $minutos ?>"
                                <?= $duracaoSelecionada === $minutos ? 'selected' : '' ?>>
                                <?= escapar(formatarDuracaoAgenda($minutos)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="field span-12 bath-schedule-field" id="horaBanhoBox">
                    <!-- Rótulo do campo "Horário", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="horaBanho">Horário</label>
                    <select
                        id="horaBanho"
                        name="hora_banho"
                        data-selected="<?= escapar($horaBanhoSelecionada) ?>">
                        <option value="">Selecione o horário</option>
                    </select>
                    <small class="field-help">As vagas são verificadas automaticamente pela duração.</small>
                </div>

                <div class="field span-6 manual-schedule-field" id="horaManualBox">
                    <!-- Rótulo do campo "Horário", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="horaInicio">Horário</label>
                    <input
                        id="horaInicio"
                        name="hora"
                        type="time"
                        value="<?= escapar($horaManualSelecionada) ?>">
                </div>

                <div class="field span-6 hotel-schedule-field" id="dataFimHotelBox">
                    <!-- Rótulo do campo "Saída do hotel", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="dataFim">Saída do hotel</label>
                    <input
                        id="dataFim"
                        name="data_fim"
                        type="date"
                        min="<?= escapar($dataSelecionada) ?>"
                        value="<?= escapar($dataFimSelecionada) ?>">
                </div>

                <div class="field span-12 creche-info-field" id="crecheInfoBox">
                    <div class="agenda-simple-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Na creche não precisa escolher horário. É só adicionar o pet neste dia.</span>
                    </div>
                </div>

                <div class="field span-12">
                    <!-- Rótulo do campo "Observações", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="observacoesAgenda">Observações</label>
                    <textarea
                        id="observacoesAgenda"
                        name="observacoes"
                        placeholder="Orientações ou observações do serviço"><?= escapar($observacoesSelecionadas) ?></textarea>
                </div>
            </div>

            <!-- Bloco visual "agenda-modal-actions" que organiza esta parte da interface. -->
            <div class="agenda-modal-actions">
                <button class="secondary-action-button" id="agendaModalCancel" type="button">Cancelar</button>
                <button class="save-button" type="submit">
                    <i class="fa-solid fa-check"></i>
                    <?= $editarId !== null ? 'Salvar alterações' : 'Adicionar' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Script responsável pelas interações da agenda e busca de pets -->
<script>
    (() => {
        const slots = <?= json_encode(
                            $slotsBanhoJs ?? [],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ) ?>;

        const modal = document.querySelector('#agendaEventModal');
        const form = document.querySelector('#agendaEventForm');
        const fechar = document.querySelector('#agendaModalClose');
        const cancelar = document.querySelector('#agendaModalCancel');
        const botoesAbrir = document.querySelectorAll('[data-open-agenda]');
        const titulo = document.querySelector('#agendaModalTitle');
        const categoriaTexto = document.querySelector('#agendaModalCategory');
        const acao = document.querySelector('#agendaFormAction');
        const agendamentoId = document.querySelector('#agendaFormId');
        
        // Elementos do componente de busca de pet
        const petPicker = document.querySelector('[data-search-picker="pet"]');
        const petInput = document.querySelector('#petAgendaBusca');
        const petValue = document.querySelector('#petAgenda');
        const petResults = document.querySelector('[data-search-results]');

        const servico = document.querySelector('#servicoAgenda');
        const duracao = document.querySelector('#duracaoBanho');
        const horaBanho = document.querySelector('#horaBanho');
        const horaManual = document.querySelector('#horaInicio');
        const dataFim = document.querySelector('#dataFim');
        const observacoes = document.querySelector('#observacoesAgenda');
        
        const bathFields = document.querySelectorAll('.bath-schedule-field');
        const manualFields = document.querySelectorAll('.manual-schedule-field');
        const hotelFields = document.querySelectorAll('.hotel-schedule-field');
        const crecheFields = document.querySelectorAll('.creche-info-field');

        const nomesCategorias = {
            banho_tosa: 'Banho e tosa',
            creche: 'Creche',
            hotel: 'Hotel',
            veterinario: 'Consulta veterinária',
            taxidog: 'Taxidog'
        };

        let categoriaAtual = '';
        let debounceTimer = null;

        const minutos = (hora) => {
            const partes = String(hora || '').split(':').map(Number);
            return ((partes[0] || 0) * 60) + (partes[1] || 0);
        };

        const formatarDuracao = (total) => {
            const horas = Math.floor(total / 60);
            const resto = total % 60;
            if (!horas) return `${resto} min`;
            if (!resto) return `${horas}h`;
            return `${horas}h${String(resto).padStart(2, '0')}`;
        };

        // Reset do seletor de pet
        const resetarBuscaPet = () => {
            if (petInput) {
                petInput.value = '';
                petInput.dataset.selectedLabel = '';
            }
            if (petValue) {
                petValue.value = '';
            }
            if (petResults) {
                petResults.hidden = true;
                petResults.innerHTML = '';
            }
        };

        // Busca dinâmica ajustada para o seu arquivo busca-registros.php
        const buscarPets = async (termo) => {
            if (!termo || termo.length < 2) {
                if (petResults) {
                    petResults.hidden = true;
                    petResults.innerHTML = '';
                }
                return;
            }

            try {
                // Rota atualizada com o nome do arquivo correto e tipo=pet
                const response = await fetch(`busca-registros.php?tipo=pet&q=${encodeURIComponent(termo)}`);
                const pets = await response.json();

                if (!petResults) return;

                if (!pets || pets.length === 0) {
                    petResults.innerHTML = '<div class="search-picker-empty" style="padding: 10px; color: #777;">Nenhum pet encontrado</div>';
                } else {
                    // Mapeia usando a propriedade 'label' retornada pelo seu PHP
                    petResults.innerHTML = pets.map(pet => `
    <button type="button" 
            class="search-picker-item" 
            data-pet-id="${pet.id}" 
            data-pet-name="${pet.label}">
        ${pet.label}
    </button>
`).join('');
                }

                petResults.hidden = false;
            } catch (erro) {
                console.error('Erro ao buscar pets:', erro);
            }
        };

        // Evento ao digitar no campo de busca de pet
        if (petInput) {
            petInput.addEventListener('input', (e) => {
                const termo = e.target.value.trim();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => buscarPets(termo), 300);
            });
        }

        // Evento de seleção do pet na lista
        if (petResults) {
            petResults.addEventListener('click', (e) => {
                const item = e.target.closest('[data-pet-id]');
                if (!item) return;

                const id = item.dataset.petId;
                const nome = item.dataset.petName;

                if (petValue) petValue.value = id;
                if (petInput) {
                    petInput.value = nome;
                    petInput.dataset.selectedLabel = nome;
                }

                petResults.hidden = true;
                petResults.innerHTML = '';
            });
        }

        // Oculta a lista de resultados se clicar fora do componente
        document.addEventListener('click', (e) => {
            if (petPicker && !petPicker.contains(e.target) && petResults) {
                petResults.hidden = true;
            }
        });

        const abrirModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('agenda-modal-open');
        };

        const fecharModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('agenda-modal-open');
            resetarBuscaPet();
        };

        const mostrar = (elementos, ativo) => {
            elementos.forEach((elemento) => {
                elemento.hidden = !ativo;
            });
        };

        const categoriaDoServico = () => {
            const opcao = servico.options[servico.selectedIndex];
            return opcao?.dataset?.category || '';
        };

        const filtrarServicos = (categoria, manterSelecionado = false) => {
            let quantidade = 0;
            let unicoValor = '';
            const valorAtual = servico.value;

            Array.from(servico.options).forEach((opcao, indice) => {
                if (indice === 0) {
                    opcao.hidden = false;
                    opcao.disabled = false;
                    return;
                }

                const pertence = opcao.dataset.category === categoria;
                opcao.hidden = !pertence;
                opcao.disabled = !pertence;

                if (pertence) {
                    quantidade += 1;
                    unicoValor = opcao.value;
                }
            });

            if (manterSelecionado && valorAtual) {
                const atual = Array.from(servico.options).find((opcao) => opcao.value === valorAtual);
                if (atual && !atual.disabled) {
                    servico.value = valorAtual;
                    return;
                }
            }

            servico.value = quantidade === 1 ? unicoValor : '';
        };

        const atualizarHorariosBanho = (horarioPreferido = '') => {
            if (!duracao || !horaBanho) return;

            const anterior = horarioPreferido || horaBanho.value || horaBanho.dataset.selected || '';
            const duracaoMinutos = Number(duracao.value || 30);
            const blocos = Math.ceil(duracaoMinutos / 30);
            const opcoes = [];

            slots.forEach((slot, indice) => {
                let valido = true;
                let menorRestante = Infinity;
                const inicio = minutos(slot.hora);

                for (let bloco = 0; bloco < blocos; bloco += 1) {
                    const atual = slots[indice + bloco];
                    const esperado = inicio + (bloco * 30);

                    if (!atual || minutos(atual.hora) !== esperado || Number(atual.restantes) <= 0) {
                        valido = false;
                        break;
                    }

                    menorRestante = Math.min(menorRestante, Number(atual.restantes));
                }

                if (valido) {
                    opcoes.push({
                        hora: slot.hora,
                        vagas: menorRestante
                    });
                }
            });

            horaBanho.innerHTML = '<option value="">Selecione o horário</option>';

            opcoes.forEach((opcao) => {
                const elemento = document.createElement('option');
                elemento.value = opcao.hora;
                elemento.textContent = `${opcao.hora} — ${opcao.vagas} vaga(s) livre(s) para ${formatarDuracao(duracaoMinutos)}`;
                elemento.selected = opcao.hora === anterior;
                horaBanho.appendChild(elemento);
            });

            if (!opcoes.length) {
                horaBanho.options[0].textContent = 'Nenhum horário disponível';
            }
        };

        const configurarCategoria = (categoria, horarioPreferido = '') => {
            categoriaAtual = categoria;
            categoriaTexto.textContent = nomesCategorias[categoria] || 'Novo agendamento';

            mostrar(bathFields, categoria === 'banho_tosa');
            mostrar(crecheFields, categoria === 'creche');
            mostrar(hotelFields, categoria === 'hotel');
            mostrar(manualFields, categoria === 'veterinario' || categoria === 'taxidog');

            horaManual.required = categoria === 'veterinario' || categoria === 'taxidog';
            dataFim.required = categoria === 'hotel';
            duracao.required = categoria === 'banho_tosa';
            horaBanho.required = categoria === 'banho_tosa';

            filtrarServicos(categoria, form.dataset.editing === '1');

            if (categoria === 'banho_tosa') {
                atualizarHorariosBanho(horarioPreferido);
            }
        };

        const prepararNovo = (categoria, horario = '') => {
            form.dataset.editing = '0';
            acao.value = 'criar_agendamento';
            agendamentoId.value = '';
            titulo.textContent = 'Adicionar agendamento';
            
            resetarBuscaPet();

            observacoes.value = '';
            horaManual.value = '';
            dataFim.value = '';
            duracao.value = '30';
            horaBanho.dataset.selected = horario;

            configurarCategoria(categoria, horario);
            abrirModal();
            
            setTimeout(() => petInput?.focus(), 50);
        };

        botoesAbrir.forEach((botao) => {
            botao.addEventListener('click', () => {
                prepararNovo(botao.dataset.category || '', botao.dataset.time || '');
            });
        });

        servico.addEventListener('change', () => {
            const categoria = categoriaDoServico();
            if (categoria && categoria !== categoriaAtual) {
                configurarCategoria(categoria);
            }
        });

        duracao.addEventListener('change', () => {
            horaBanho.dataset.selected = '';
            atualizarHorariosBanho();
        });

        fechar.addEventListener('click', fecharModal);
        cancelar.addEventListener('click', fecharModal);

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                fecharModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                fecharModal();
            }
        });

        if (form.dataset.editing === '1' || modal.classList.contains('is-open')) {
            const categoriaInicial = categoriaDoServico();
            if (categoriaInicial) {
                configurarCategoria(categoriaInicial, horaBanho.dataset.selected || '');
                abrirModal();
            }
        }
    })();
</script>



<?php require __DIR__ . '/../includes/rodape.php'; ?>