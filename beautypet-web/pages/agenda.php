<?php
// Inclui configuração (conexão PDO, funções auxiliares) usada em toda a página
require __DIR__ . '/config.php';

// Recupera o usuário logado atualmente
$usuarioAtual = obterUsuarioAtual($pdo);

// ID do usuário atual (usado para registrar quem criou/alterou um agendamento)
$usuarioId = isset($usuarioAtual['id']) ? (int) $usuarioAtual['id'] : null;

// Flag que indica se o usuário logado é administrador
// (controla exibição de links como "Agenda-padrão")
$administrador = ($usuarioAtual['perfil'] ?? '') === 'ADMINISTRADOR';

// Variáveis de mensagem de feedback (sucesso/erro) exibidas na página
$mensagem = '';
$tipoMensagem = '';

// Data atualmente selecionada na agenda; vem da query string ou usa hoje como padrão
$dataSelecionada = (string) ($_GET['data'] ?? date('Y-m-d'));

// ID do agendamento a ser editado, se houver (via ?editar=ID)
$editarId = inteiroOuNull($_GET['editar'] ?? null);

/**
 * Renderiza os botões de ação (editar, iniciar/concluir, excluir) de um agendamento.
 * Função auxiliar usada tanto na listagem compacta (creche/hotel) quanto nas tabelas
 * (banho e tosa, consulta, taxidog), evitando duplicar o HTML das ações.
 */
function renderizarAcoesAgenda(array $item, string $dataSelecionada): void
{
    ?>
    <div class="agenda-row-actions">
        <!-- Link para editar o agendamento, mantendo a data selecionada na URL -->
        <a
            class="icon-action-button edit"
            href="agenda.php?data=<?= escapar($dataSelecionada) ?>&editar=<?= (int) $item['id'] ?>"
            title="Editar agendamento">
            <i class="fa-solid fa-pen"></i>
        </a>

        <?php if ($item['status'] === 'CONFIRMADO'): ?>
            <!-- Se o agendamento está confirmado, permite avançar para "Em atendimento" -->
            <form method="post" action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
                <input type="hidden" name="acao" value="alterar_status">
                <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="status" value="EM_ATENDIMENTO">
                <button class="small-action-button start" type="submit">Iniciar</button>
            </form>
        <?php elseif ($item['status'] === 'EM_ATENDIMENTO'): ?>
            <!-- Se já está em atendimento, permite marcar como concluído -->
            <form method="post" action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
                <input type="hidden" name="acao" value="alterar_status">
                <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="status" value="CONCLUIDO">
                <button class="small-action-button finish" type="submit">Concluir</button>
            </form>
        <?php endif; ?>

        <!-- Formulário de exclusão do agendamento, com confirmação via JS antes de enviar -->
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

// Garante que a data selecionada seja válida; caso contrário, volta para o dia atual
if (!dataSqlValida($dataSelecionada)) {
    $dataSelecionada = date('Y-m-d');
}

// Se um ID de edição foi informado, busca os dados completos desse agendamento
$agendamentoEdicao = $editarId !== null
    ? buscarAgendamentoEdicao($pdo, $editarId)
    : null;

// Se o agendamento informado não existir, avisa o usuário e volta ao modo de criação
if ($editarId !== null && $agendamentoEdicao === null) {
    $mensagem = 'O agendamento selecionado não foi encontrado.';
    $tipoMensagem = 'error';
    $editarId = null;
}

// Bloco de processamento do formulário, executado apenas em requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Identifica a ação solicitada (criar, editar, alterar status ou excluir)
    $acao = (string) ($_POST['acao'] ?? '');

    // Trata tanto criação quanto edição de agendamento
    if (in_array($acao, ['criar_agendamento', 'editar_agendamento'], true)) {
        // Se for edição, recupera o ID do agendamento enviado
        $agendamentoId = $acao === 'editar_agendamento'
            ? inteiroOuNull($_POST['agendamento_id'] ?? null)
            : null;

        // Captura os dados enviados pelo formulário
        $petId = inteiroOuNull($_POST['pet_id'] ?? null);
        $servicoId = inteiroOuNull($_POST['servico_id'] ?? null);
        $horaManual = (string) ($_POST['hora'] ?? '');
        $horaBanho = (string) ($_POST['hora_banho'] ?? '');
        $duracaoMinutos = inteiroOuNull($_POST['duracao_minutos'] ?? null);
        $dataFim = textoOuNull($_POST['data_fim'] ?? null);
        $horaFim = textoOuNull($_POST['hora_fim'] ?? null);
        $observacoes = textoOuNull($_POST['observacoes'] ?? null);

        // Busca os dados do serviço selecionado, para saber sua categoria/nome
        $servico = $servicoId !== null
            ? buscarServicoAgenda($pdo, $servicoId)
            : null;

        if ($petId === null || $servico === null) {
            // Pet e serviço são obrigatórios para qualquer agendamento
            $mensagem = 'Selecione o pet e o serviço.';
            $tipoMensagem = 'error';
        } else {
            // Verifica se o serviço escolhido é do tipo "banho e tosa"
            // (esse tipo de serviço usa vagas fixas de 30 em 30 minutos, diferente dos demais)
            $banhoTosa = ehBanhoTosa($servico['categoria'], $servico['nome']);
            $horaInicio = null;
            $fim = null;

            if ($banhoTosa) {
                // Fluxo específico para banho e tosa: usa a agenda-padrão do dia
                // para descobrir os horários e vagas disponíveis
                $padrao = buscarPadraoBanhoDaData($pdo, $dataSelecionada);
                $horariosPadrao = $padrao
                    ? buscarHorariosPadraoBanho($pdo, (int) $padrao['id'])
                    : [];

                // Verifica quantas vagas já estão ocupadas em cada horário da agenda-padrão,
                // ignorando o próprio agendamento em edição (para não contar ele mesmo)
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
                    // Confere se há vagas suficientes em todos os blocos de 30 minutos
                    // necessários para cobrir a duração escolhida do serviço
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

                        // Calcula automaticamente o horário de término somando
                        // a duração selecionada ao horário de início
                        $inicioTemporario = new DateTime(
                            $dataSelecionada . ' ' . $horaBanho . ':00'
                        );
                        $fim = (clone $inicioTemporario)
                            ->modify('+' . $duracaoMinutos . ' minutes')
                            ->format('Y-m-d H:i:s');
                    }
                }
            } else {
                // Fluxo para serviços que não são banho e tosa: horário definido manualmente
                if (!horaSqlValida($horaManual)) {
                    $mensagem = 'Informe um horário válido.';
                    $tipoMensagem = 'error';
                } else {
                    $horaInicio = $horaManual;
                }

                // Validação do término opcional: se informado, data e hora devem ser
                // ambos preenchidos e válidos (não pode ter um sem o outro)
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

                // Monta o timestamp de término, se data e hora de fim foram informadas
                if ($mensagem === '' && $dataFim !== null && $horaFim !== null) {
                    $fim = $dataFim . ' ' . $horaFim . ':00';
                }
            }

            if ($mensagem === '' && $horaInicio !== null) {
                // Monta o timestamp completo de início do agendamento
                $inicio = $dataSelecionada . ' ' . $horaInicio . ':00';

                // Garante que o término (quando informado) seja depois do início
                if ($fim !== null && strtotime($fim) <= strtotime($inicio)) {
                    $mensagem = 'O término deve ser posterior ao início.';
                    $tipoMensagem = 'error';
                } else {
                    try {
                        // Confirma que o pet selecionado existe e está ativo antes de prosseguir
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

                        // Inicia transação: agendamento + item do agendamento devem
                        // ser salvos juntos de forma consistente
                        $pdo->beginTransaction();

                        if ($acao === 'criar_agendamento') {
                            // Insere um novo agendamento, já como CONFIRMADO
                            // (agendamentos criados internamente pelo painel entram confirmados)
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

                            // Recupera o ID gerado para inserir o item do agendamento em seguida
                            $agendamentoId = (int) $pdo->lastInsertId();
                        } else {
                            // Edição exige um ID de agendamento válido
                            if ($agendamentoId === null) {
                                throw new RuntimeException(
                                    'Agendamento inválido para edição.'
                                );
                            }

                            // Atualiza os dados principais do agendamento
                            // (não altera status/origem/criado_por na edição)
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

                            // Remove o(s) item(ns) de serviço anteriores, para recriá-los
                            // logo abaixo com os dados atuais do formulário
                            $pdo->prepare(
                                'DELETE FROM agendamento_itens
                                 WHERE agendamento_id = :id'
                            )->execute([':id' => $agendamentoId]);
                        }

                        // Insere o item de serviço vinculado a este agendamento
                        // (tanto na criação quanto após recriar na edição)
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

                        // Confirma a transação, persistindo agendamento + item juntos
                        $pdo->commit();

                        // Define o parâmetro de sucesso conforme a ação executada
                        $parametro = $acao === 'criar_agendamento'
                            ? 'salvo=1'
                            : 'editado=1';

                        // Redireciona de volta à agenda do dia, evitando reenvio do form (padrão PRG)
                        header(
                            'Location: agenda.php?data='
                            . urlencode($dataSelecionada)
                            . '&'
                            . $parametro
                        );
                        exit;
                    } catch (Throwable $erro) {
                        // Desfaz a transação em caso de qualquer falha
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        // Usa a mensagem específica da exceção quando for RuntimeException,
                        // senão exibe uma mensagem genérica de erro
                        $mensagem = $erro instanceof RuntimeException
                            ? $erro->getMessage()
                            : 'Não foi possível salvar o agendamento.';
                        $tipoMensagem = 'error';
                    }
                }
            }
        }
    }

    // Ação de alterar apenas o status de um agendamento (ex.: Iniciar, Concluir, Cancelar)
    if ($acao === 'alterar_status') {
        $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);
        $novoStatus = (string) ($_POST['status'] ?? '');
        // Lista de status permitidos para alteração manual via este endpoint
        $statusPermitidos = ['EM_ATENDIMENTO', 'CONCLUIDO', 'CANCELADO'];

        if (
            $agendamentoId === null
            || !in_array($novoStatus, $statusPermitidos, true)
        ) {
            $mensagem = 'Não foi possível atualizar esse agendamento.';
            $tipoMensagem = 'error';
        } else {
            // Atualiza o status e registra quem e quando fez a alteração
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

            // Redireciona de volta à agenda do dia, indicando sucesso na atualização
            header(
                'Location: agenda.php?data='
                . urlencode($dataSelecionada)
                . '&atualizado=1'
            );
            exit;
        }
    }

    // Ação de exclusão definitiva de um agendamento
    if ($acao === 'excluir_agendamento') {
        $agendamentoId = inteiroOuNull($_POST['agendamento_id'] ?? null);

        if ($agendamentoId === null) {
            $mensagem = 'Agendamento inválido para exclusão.';
            $tipoMensagem = 'error';
        } else {
            try {
                // Transação para excluir o agendamento e seus itens de forma consistente
                $pdo->beginTransaction();

                // Remove primeiro os itens de serviço vinculados (dependência de chave estrangeira)
                $pdo->prepare(
                    'DELETE FROM agendamento_itens
                     WHERE agendamento_id = :id'
                )->execute([':id' => $agendamentoId]);

                // Em seguida remove o agendamento propriamente dito
                $pdo->prepare(
                    'DELETE FROM agendamentos
                     WHERE id = :id'
                )->execute([':id' => $agendamentoId]);

                $pdo->commit();

                // Redireciona indicando sucesso na exclusão
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

// Bloco que traduz os parâmetros de sucesso/erro vindos via GET (após redirecionamentos)
// em mensagens amigáveis exibidas ao usuário
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
        // Mensagem exibida quando um usuário não-administrador tenta acessar agenda-padrao.php
        $mensagem = 'Somente administradores podem configurar a agenda-padrão.';
        $tipoMensagem = 'error';
    }
}

// Lista de pets ativos (com seus tutores/clientes ativos) para popular o <select> do formulário
$pets = $pdo->query(
    'SELECT p.id, p.nome, c.nome AS tutor
     FROM pets p
     JOIN clientes c
       ON c.id = p.cliente_id
     WHERE p.ativo = TRUE
       AND c.ativo = TRUE
     ORDER BY p.nome, c.nome'
)->fetchAll();

// Lista de serviços ativos (exceto categoria "loja"), agrupados por categoria,
// usada para popular o <select> de serviço no formulário
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

// Conta quantas solicitações de agendamento feitas pelo app mobile ainda estão pendentes
// (exibido como badge no botão "Solicitações")
$totalSolicitacoes = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM agendamentos
     WHERE origem = 'MOBILE'
       AND status = 'SOLICITADO'"
)->fetchColumn();

// Busca a agenda-padrão de banho e tosa vigente para a data selecionada
$padraoBanho = buscarPadraoBanhoDaData($pdo, $dataSelecionada);

// Busca os horários (blocos de 30 min) e capacidades definidos nessa agenda-padrão
$horariosPadraoBanho = $padraoBanho
    ? buscarHorariosPadraoBanho($pdo, (int) $padraoBanho['id'])
    : [];

// Calcula quantas vagas já estão ocupadas em cada horário, considerando os agendamentos
// já existentes na data (ignorando o próprio agendamento em edição, se houver)
$ocupacaoBanho = buscarOcupacaoBanhoTosa(
    $pdo,
    $dataSelecionada,
    $horariosPadraoBanho,
    $editarId
);

// Monta a estrutura de slots (horário, capacidade, ocupadas, restantes) que será
// enviada ao JavaScript para montar dinamicamente o <select> de horários de banho e tosa
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

// Consulta principal: busca todos os agendamentos ativos (confirmado/em atendimento/concluído)
// que ocorrem na data selecionada, incluindo dados do pet, tutor, endereço, serviços e categorias.
// A condição de data cobre tanto agendamentos de dia único quanto os que têm intervalo
// (ex.: hospedagem no hotel, que pode abranger vários dias)
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

// Estrutura que agrupará os agendamentos por tipo de serviço, para exibição
// em seções separadas (creche, hotel, banho e tosa, veterinário, taxidog)
$grupos = [
    'creche'      => [],
    'hotel'       => [],
    'banho_tosa'  => [],
    'veterinario' => [],
    'taxidog'     => [],
];

// Classifica cada agendamento em um dos grupos acima, com base no texto combinado
// de categorias + nomes de serviços (normalizado para comparação simples por substring)
foreach ($agendamentos as $agendamento) {
    $identificacao = normalizarCategoriaAgenda(
        ($agendamento['categorias'] ?? '')
        . ' '
        . ($agendamento['servicos'] ?? '')
    );

    // Agendamentos de "loja" não fazem parte da agenda operacional, são ignorados aqui
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

// Calcula a data do dia anterior, usada no botão de navegação "<"
$dataAnterior = (new DateTime($dataSelecionada))
    ->modify('-1 day')
    ->format('Y-m-d');

// Calcula a data do dia seguinte, usada no botão de navegação ">"
$dataSeguinte = (new DateTime($dataSelecionada))
    ->modify('+1 day')
    ->format('Y-m-d');

// Determina se o formulário de agendamento deve ser exibido:
// quando o usuário clicou em "Novo agendamento", está editando um existente,
// ou quando houve um erro de validação (para o usuário corrigir os dados)
$mostrarFormulario = isset($_GET['novo'])
    || $editarId !== null
    || ($mensagem !== '' && $tipoMensagem === 'error');

// Fonte dos dados para preencher o formulário quando em modo de edição
$dadosFormulario = $agendamentoEdicao ?? [];

// Serviço selecionado no formulário: prioriza o valor reenviado via POST (erro de validação),
// senão usa o valor vindo do agendamento em edição
$servicoSelecionado = (string) (
    $_POST['servico_id']
    ?? $dadosFormulario['servico_id']
    ?? ''
);

// Pet selecionado no formulário, com a mesma lógica de prioridade acima
$petSelecionado = (string) (
    $_POST['pet_id']
    ?? $dadosFormulario['pet_id']
    ?? ''
);

// Horário manual (para serviços que não são banho e tosa), extraído do timestamp de início
// quando vindo de um agendamento existente em edição
$horaManualSelecionada = (string) (
    $_POST['hora']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
            ? substr((string) $dadosFormulario['data_hora_inicio'], 11, 5)
            : ''
    )
);

// Horário de banho e tosa selecionado, seguindo a mesma lógica do campo manual acima
$horaBanhoSelecionada = (string) (
    $_POST['hora_banho']
    ?? (
        !empty($dadosFormulario['data_hora_inicio'])
            ? substr((string) $dadosFormulario['data_hora_inicio'], 11, 5)
            : ''
    )
);

// Duração (em minutos) do serviço de banho e tosa: calculada a partir da diferença
// entre início e fim do agendamento em edição, ou 60 minutos como padrão
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

// Data de término (para serviços manuais com intervalo), extraída do agendamento em edição
$dataFimSelecionada = (string) (
    $_POST['data_fim']
    ?? (
        !empty($dadosFormulario['data_hora_fim'])
            ? substr((string) $dadosFormulario['data_hora_fim'], 0, 10)
            : ''
    )
);

// Horário de término (para serviços manuais com intervalo), extraído do agendamento em edição
$horaFimSelecionada = (string) (
    $_POST['hora_fim']
    ?? (
        !empty($dadosFormulario['data_hora_fim'])
            ? substr((string) $dadosFormulario['data_hora_fim'], 11, 5)
            : ''
    )
);

// Texto de observações do agendamento, vindo do POST ou do registro em edição
$observacoesSelecionadas = (string) (
    $_POST['observacoes']
    ?? $dadosFormulario['observacoes_internas']
    ?? ''
);

// Metadados da página usados pelo template de topo (título da aba, item de menu ativo)
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
                <!-- Link para a página de agenda-padrão, visível apenas para administradores -->
                <a class="secondary-action-button" href="agenda-padrao.php">
                    <i class="fa-solid fa-clock"></i>
                    Agenda-padrão
                </a>
            <?php endif; ?>

            <!-- Link para a lista de solicitações de agendamento (feitas pelo app mobile),
                 exibindo um contador quando há solicitações pendentes -->
            <a class="secondary-action-button" href="solicitacoes-agendamento.php">
                <i class="fa-solid fa-calendar-check"></i>
                Solicitações
                <?php if ($totalSolicitacoes > 0): ?>
                    <span class="request-count"><?= $totalSolicitacoes ?></span>
                <?php endif; ?>
            </a>

            <!-- Botão que abre o formulário de novo agendamento, mantendo a data atual -->
            <a
                class="primary-action-button"
                href="agenda.php?data=<?= escapar($dataSelecionada) ?>&novo=1">
                <i class="fa-solid fa-plus"></i>
                Novo agendamento
            </a>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <!-- Exibe a mensagem de feedback (sucesso ou erro) processada anteriormente -->
        <div class="message <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- Barra de navegação de datas: dia anterior, seletor de data, dia seguinte -->
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
        <!-- Formulário de criação/edição de agendamento, exibido condicionalmente -->
        <form
            class="agenda-form-card"
            method="post"
            action="agenda.php?data=<?= escapar($dataSelecionada) ?>">
            <input
                type="hidden"
                name="acao"
                value="<?= $editarId !== null ? 'editar_agendamento' : 'criar_agendamento' ?>">

            <?php if ($editarId !== null): ?>
                <!-- ID do agendamento, presente apenas em modo de edição -->
                <input type="hidden" name="agendamento_id" value="<?= $editarId ?>">
            <?php endif; ?>

            <div class="agenda-card-title">
                <div>
                    <h2><?= $editarId !== null ? 'Editar agendamento' : 'Novo agendamento' ?></h2>
                    <small>
                        Banho e tosa usa vagas de 30 em 30 minutos.
                    </small>
                </div>

                <!-- Fecha o formulário voltando para a agenda sem o parâmetro ?novo=1/?editar=ID -->
                <a href="agenda.php?data=<?= escapar($dataSelecionada) ?>" aria-label="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            </div>

            <div class="form-grid">
                <!-- Seleção do pet, listando nome do pet e nome do tutor -->
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

                <!-- Seleção do serviço, agrupado por categoria via <optgroup>.
                     Cada <option> carrega data-banho-tosa, usado pelo JS para
                     alternar entre os campos de horário manual x banho e tosa -->
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

                <!-- Data do agendamento: somente leitura, pois é definida pela agenda do dia atual -->
                <div class="field span-3">
                    <label>Data</label>
                    <input type="date" value="<?= escapar($dataSelecionada) ?>" readonly>
                </div>

                <!-- Campo de duração, visível apenas quando o serviço é banho e tosa
                     (controlado via JS, classe .bath-schedule-field) -->
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

                <!-- Campo de horário disponível para banho e tosa: as <option>s são
                     geradas dinamicamente pelo JS (função atualizarHorariosBanho),
                     com base nos slots calculados no PHP ($slotsBanhoJs) -->
                <div class="field span-6 bath-schedule-field" id="horaBanhoBox">
                    <label for="horaBanho">Horário disponível</label>
                    <select
                        id="horaBanho"
                        name="hora_banho"
                        data-selected="<?= escapar($horaBanhoSelecionada) ?>">
                        <option value="">Selecione a duração primeiro</option>
                    </select>

                    <?php if (!$horariosPadraoBanho): ?>
                        <!-- Aviso quando não existe agenda-padrão cadastrada para a data selecionada -->
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

                <!-- Campo de horário manual, visível apenas para serviços que NÃO são
                     banho e tosa (classe .manual-schedule-field, controlada via JS) -->
                <div class="field span-3 manual-schedule-field" id="horaManualBox">
                    <label for="horaInicio">Horário</label>
                    <input
                        id="horaInicio"
                        name="hora"
                        type="time"
                        value="<?= escapar($horaManualSelecionada) ?>">
                </div>

                <!-- Data de término opcional, usada em serviços manuais com intervalo (ex.: hotel) -->
                <div class="field span-3 manual-schedule-field">
                    <label for="dataFim">Término — data</label>
                    <input
                        id="dataFim"
                        name="data_fim"
                        type="date"
                        value="<?= escapar($dataFimSelecionada) ?>">
                </div>

                <!-- Horário de término opcional, correspondente à data de término acima -->
                <div class="field span-3 manual-schedule-field">
                    <label for="horaFim">Término — horário</label>
                    <input
                        id="horaFim"
                        name="hora_fim"
                        type="time"
                        value="<?= escapar($horaFimSelecionada) ?>">
                </div>

                <!-- Campo livre de observações internas sobre o agendamento -->
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
    // Configuração das seções "compactas" (listagem em cards), usadas para
    // Creche e Hotel — grupos com layout mais simples que as tabelas abaixo
    $compactos = [
        [
            'titulo' => 'Creche',
            'icone'  => 'fa-solid fa-house',
            'itens'  => $grupos['creche'],
            'vazio'  => 'Nenhum pet na creche nesta data.',
            'entrada'=> 'Entrada',
            'saida'  => 'Saída',
            'dataCompleta' => false, // exibe apenas hora, não data completa
        ],
        [
            'titulo' => 'Hotel',
            'icone'  => 'fa-solid fa-hotel',
            'itens'  => $grupos['hotel'],
            'vazio'  => 'Nenhuma hospedagem nesta data.',
            'entrada'=> 'Check-in',
            'saida'  => 'Check-out',
            'dataCompleta' => true, // exibe data e hora completas (hospedagem pode passar de um dia)
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
                                    <!-- Ícone de fallback quando o pet não tem foto cadastrada -->
                                    <span class="agenda-pet-fallback">
                                        <i class="fa-solid fa-paw"></i>
                                    </span>
                                <?php endif; ?>

                                <div>
                                    <strong><?= escapar($item['pet']) ?></strong>
                                    <small>Tutor: <?= escapar($item['tutor']) ?></small>
                                </div>
                            </div>

                            <!-- Exibe entrada/saída (ou check-in/check-out) conforme o tipo de grupo,
                                 formatando como data+hora ou apenas hora dependendo de 'dataCompleta' -->
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
    // Configuração das seções em formato de tabela: banho e tosa, consulta veterinária
    // e taxidog — grupos com mais informações por linha (horário, duração, endereço etc.)
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
                                <!-- Coluna de tutor mostra também o endereço quando o grupo é Taxidog -->
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
                                // Calcula a duração em minutos do item, quando início e fim
                                // estão disponíveis (usado para exibir "até HH:MM • Xh")
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
                                            <!-- Endereço abreviado, exibido apenas para agendamentos de Taxidog -->
                                            <small class="agenda-address">
                                                <i class="fa-solid fa-location-dot"></i>
                                                <?= escapar(enderecoAbreviadoAgenda($item)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <strong><?= escapar(formatarHoraAgenda($item['data_hora_inicio'])) ?></strong>

                                        <?php if ($grupo['tipo'] === 'banho' && $duracaoItem !== null): ?>
                                            <!-- Para banho e tosa, mostra também o horário de término e a duração -->
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
    // Slots de horário de banho e tosa (hora, capacidade, ocupadas, restantes),
    // calculados no PHP e usados para montar o <select> de horário dinamicamente
    const slots = <?= json_encode(
        $slotsBanhoJs,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    // Referências aos elementos do formulário manipulados pelo script
    const servico = document.querySelector('#servicoAgenda');
    const duracao = document.querySelector('#duracaoBanho');
    const horaBanho = document.querySelector('#horaBanho');
    const bathFields = document.querySelectorAll('.bath-schedule-field');
    const manualFields = document.querySelectorAll('.manual-schedule-field');
    const horaManual = document.querySelector('#horaInicio');

    // Se o campo de serviço não existir na página (formulário não está visível), encerra o script
    if (!servico) {
        return;
    }

    // Converte uma hora "HH:MM" em minutos desde 00:00, para facilitar comparações
    const minutos = (hora) => {
        const [h, m] = hora.split(':').map(Number);
        return (h * 60) + m;
    };

    // Formata uma duração em minutos como texto amigável (ex.: "1h30", "45 min", "2h")
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

    // Recalcula e repopula o <select> de horários disponíveis para banho e tosa,
    // considerando a duração selecionada (que pode exigir vários blocos consecutivos de 30 min)
    const atualizarHorariosBanho = () => {
        if (!duracao || !horaBanho) {
            return;
        }

        // Preserva a seleção atual (ou a inicial vinda do PHP, em modo de edição)
        // para tentar mantê-la selecionada após a grade ser reconstruída
        const selecionadoAnterior =
            horaBanho.value || horaBanho.dataset.selected || '';

        const duracaoMinutos = Number(duracao.value || 30);
        // Quantidade de blocos de 30 minutos necessários para cobrir a duração escolhida
        const blocos = Math.ceil(duracaoMinutos / 30);
        const opcoes = [];

        // Para cada slot de horário, verifica se há blocos consecutivos suficientes
        // e com vagas disponíveis para acomodar toda a duração do serviço
        slots.forEach((slot, indice) => {
            let valido = true;
            let menorRestante = Infinity;
            const inicio = minutos(slot.hora);

            for (let bloco = 0; bloco < blocos; bloco++) {
                const atual = slots[indice + bloco];
                const esperado = inicio + (bloco * 30);

                // Inválido se o bloco não existir, não for consecutivo (esperado)
                // ou não tiver vagas restantes
                if (
                    !atual
                    || minutos(atual.hora) !== esperado
                    || Number(atual.restantes) <= 0
                ) {
                    valido = false;
                    break;
                }

                // A vaga disponível para o horário como um todo é limitada
                // pelo bloco com menor quantidade de vagas restantes
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

        // Limpa e reconstrói as opções do select de horário
        horaBanho.innerHTML = '';

        // Opção inicial/placeholder, cujo texto varia conforme há ou não horários disponíveis
        const primeira = document.createElement('option');
        primeira.value = '';
        primeira.textContent = opcoes.length
            ? 'Selecione o horário'
            : 'Nenhum horário disponível';
        horaBanho.appendChild(primeira);

        // Adiciona uma <option> para cada horário válido, exibindo a quantidade de vagas
        opcoes.forEach((opcao) => {
            const elemento = document.createElement('option');
            elemento.value = opcao.hora;
            elemento.textContent =
                `${opcao.hora} — ${opcao.vagas} vaga(s) para ${formatarDuracao(duracaoMinutos)}`;

            // Restaura a seleção anterior, se o horário ainda estiver disponível na lista
            if (opcao.hora === selecionadoAnterior) {
                elemento.selected = true;
            }

            horaBanho.appendChild(elemento);
        });
    };

    // Alterna a exibição dos campos do formulário conforme o serviço selecionado seja
    // (ou não) do tipo banho e tosa, e ajusta quais campos são obrigatórios
    const atualizarTipoServico = () => {
        const option = servico.options[servico.selectedIndex];
        const banhoTosa = option?.dataset?.banhoTosa === '1';

        // Mostra os campos de banho e tosa (duração/horário com vagas) apenas quando aplicável
        bathFields.forEach((campo) => {
            campo.hidden = !banhoTosa;
        });

        // Mostra os campos manuais (horário livre, término) apenas quando NÃO é banho e tosa
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

        // Se o serviço for banho e tosa, já recalcula os horários disponíveis
        if (banhoTosa) {
            atualizarHorariosBanho();
        }
    };

    // Sempre que o serviço selecionado mudar, reavalia qual conjunto de campos exibir
    servico.addEventListener('change', atualizarTipoServico);

    if (duracao) {
        // Ao trocar a duração, limpa a seleção prévia de horário (pois pode não ser
        // mais válida para a nova duração) e recalcula as opções disponíveis
        duracao.addEventListener('change', () => {
            if (horaBanho) {
                horaBanho.dataset.selected = '';
            }

            atualizarHorariosBanho();
        });
    }

    // Execução inicial ao carregar a página, garantindo que os campos corretos
    // já estejam visíveis/ocultos conforme o serviço pré-selecionado (edição)
    atualizarTipoServico();
})();
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
