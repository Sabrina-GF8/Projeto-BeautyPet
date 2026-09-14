<?php
/**
 * ARQUIVO: cadastro-pet.php
 * RESPONSABILIDADE: Cadastro e edição de pets.
 * Valida os dados do pet, vincula o animal ao tutor, processa a foto enviada e grava ou atualiza o cadastro no banco.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? $_POST['pet_id'] ?? null);
// Indica se o formulário está alterando um registro existente em vez de criar um novo.
$modoEdicao = $editarId !== null;
// Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
$dados = $_POST;
// Guarda o caminho final da foto que será persistido no cadastro do pet.
$fotoSalva = null;
// Guarda o caminho da foto já cadastrada para mantê-la quando nenhuma nova imagem for enviada.
$fotoAtual = null;

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($modoEdicao && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Prepara uma consulta em "pets" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare(
        'SELECT p.*, r.nome AS raca
         FROM pets p
         LEFT JOIN racas r ON r.id = p.raca_id
         WHERE p.id = :id
           AND p.ativo = TRUE
         LIMIT 1'
    );
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $editarId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $dados = $stmt->fetch() ?: [];

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$dados) {
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: fichas-pets.php');
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }

    // Guarda o caminho da foto já cadastrada para mantê-la quando nenhuma nova imagem for enviada.
    $fotoAtual = urlFotoPet($dados['foto_url'] ?? null);
}

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guarda o nome informado no formulário após a normalização necessária.
    $nome = textoOuNull($_POST['nome'] ?? null);
    // Guarda o ID do tutor responsável vinculado ao pet ou à movimentação financeira.
    $clienteId = inteiroOuNull($_POST['cliente_id'] ?? null);
    // Guarda o ID da espécie selecionada para relacionar o pet ao cadastro de espécies.
    $especieId = inteiroOuNull($_POST['especie_id'] ?? null);
    // Armazena em "raca" o valor necessário para as próximas etapas deste fluxo.
    $raca = textoOuNull($_POST['raca'] ?? null);
    // Armazena em "sexo" o valor necessário para as próximas etapas deste fluxo.
    $sexo = $_POST['sexo'] ?? 'NAO_INFORMADO';
    // Armazena em "peso" o valor necessário para as próximas etapas deste fluxo.
    $peso = textoOuNull($_POST['peso'] ?? null);
    // Armazena em "porte" o valor necessário para as próximas etapas deste fluxo.
    $porte = textoOuNull($_POST['porte'] ?? null);
    // Armazena em "castrado" o valor necessário para as próximas etapas deste fluxo.
    $castrado = inteiroOuNull($_POST['castrado'] ?? null);
    // Guarda o caminho da foto já cadastrada para mantê-la quando nenhuma nova imagem for enviada.
    $fotoAtual = textoOuNull($_POST['foto_atual'] ?? null);

    // Verifica a condição abaixo antes de executar este bloco.
    if ($nome === null || $clienteId === null || $especieId === null) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Preencha o nome, a espécie e o tutor responsável.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (!in_array($sexo, ['MACHO', 'FEMEA', 'NAO_INFORMADO'], true)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Selecione um sexo válido.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($porte !== null && !in_array($porte, ['PEQUENO', 'MEDIO', 'GRANDE'], true)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Selecione um porte válido.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($peso !== null && (!is_numeric($peso) || (float) $peso < 0)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Informe um peso válido.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else {
        // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
        try {
            // Inicia uma transação para garantir que todas as alterações deste processo sejam salvas em conjunto.
            $pdo->beginTransaction();

            // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = ? AND ativo = TRUE');
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([$clienteId]);
            // Verifica a condição abaixo antes de executar este bloco.
            if (!$stmt->fetch()) {
                // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                throw new RuntimeException('O tutor selecionado não foi encontrado.');
            }

            // Prepara uma consulta em "especies" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare('SELECT id FROM especies WHERE id = ? AND ativo = TRUE');
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute([$especieId]);
            // Verifica a condição abaixo antes de executar este bloco.
            if (!$stmt->fetch()) {
                // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                throw new RuntimeException('A espécie selecionada não foi encontrada.');
            }

            // Guarda o identificador da raça quando o projeto utiliza uma tabela de raças relacionada.
            $racaId = null;
            // Verifica a condição abaixo antes de executar este bloco.
            if ($raca !== null) {
                // Prepara a inclusão de um novo registro em "racas" usando os valores já validados.
                $stmt = $pdo->prepare(
                    'INSERT INTO racas (especie_id, nome)
                     VALUES (:especie_id, :nome)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), ativo = TRUE'
                );
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute([':especie_id' => $especieId, ':nome' => $raca]);
                // Guarda o identificador da raça quando o projeto utiliza uma tabela de raças relacionada.
                $racaId = (int) $pdo->lastInsertId();
            }

            // Guarda o caminho final da foto que será persistido no cadastro do pet.
            $fotoSalva = salvarFotoPet($_FILES['foto'] ?? []);
            // Armazena em "fotoFinal" o valor necessário para as próximas etapas deste fluxo.
            $fotoFinal = $fotoSalva ?? $fotoAtual;

            // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
            $parametros = [
                ':cliente_id'                  => $clienteId,
                ':especie_id'                  => $especieId,
                ':raca_id'                     => $racaId,
                ':nome'                        => $nome,
                ':foto_url'                    => $fotoFinal,
                ':sexo'                        => $sexo,
                ':cor'                         => textoOuNull($_POST['cor'] ?? null),
                ':peso'                        => $peso !== null ? (float) $peso : null,
                ':porte'                       => $porte,
                ':data_nascimento'             => textoOuNull($_POST['data_nascimento'] ?? null),
                ':nascimento_aproximado'       => isset($_POST['nascimento_aproximado']) ? 1 : 0,
                ':castrado'                    => $castrado,
                ':vacinacao_em_dia'            => isset($_POST['vacinacao_em_dia']) ? 1 : 0,
                ':medicamentos'                => textoOuNull($_POST['medicamentos'] ?? null),
                ':observacoes_comportamentais' => textoOuNull($_POST['observacoes_comportamentais'] ?? null),
                ':observacoes_medicas'         => textoOuNull($_POST['observacoes_medicas'] ?? null),
            ];

            // Verifica a condição abaixo antes de executar este bloco.
            if ($modoEdicao) {
                // Prepara a inclusão de um novo registro em "pets" usando os valores já validados.
                $sql = 'UPDATE pets SET
                    cliente_id = :cliente_id,
                    especie_id = :especie_id,
                    raca_id = :raca_id,
                    nome = :nome,
                    foto_url = :foto_url,
                    sexo = :sexo,
                    cor = :cor,
                    peso = :peso,
                    porte = :porte,
                    data_nascimento = :data_nascimento,
                    nascimento_aproximado = :nascimento_aproximado,
                    castrado = :castrado,
                    vacinacao_em_dia = :vacinacao_em_dia,
                    medicamentos = :medicamentos,
                    observacoes_comportamentais = :observacoes_comportamentais,
                    observacoes_medicas = :observacoes_medicas
                    WHERE id = :id';
                $parametros[':id'] = $editarId;
                // Prepara a inclusão de um novo registro em "pets" usando os valores já validados.
                $stmt = $pdo->prepare($sql);
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($parametros);
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Prepara a inclusão de um novo registro em "pets" usando os valores já validados.
                $sql = 'INSERT INTO pets (
                    cliente_id, especie_id, raca_id, nome, foto_url, sexo, cor, peso,
                    porte, data_nascimento, nascimento_aproximado, castrado,
                    vacinacao_em_dia, medicamentos, observacoes_comportamentais,
                    observacoes_medicas
                ) VALUES (
                    :cliente_id, :especie_id, :raca_id, :nome, :foto_url, :sexo, :cor, :peso,
                    :porte, :data_nascimento, :nascimento_aproximado, :castrado,
                    :vacinacao_em_dia, :medicamentos, :observacoes_comportamentais,
                    :observacoes_medicas
                )';
                // Prepara a consulta SQL que será executada com parâmetros separados para manter segurança e clareza.
                $stmt = $pdo->prepare($sql);
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($parametros);
                // Lê o identificador enviado pela URL para saber se a página está em modo de edição.
                $editarId = (int) $pdo->lastInsertId();
            }

            // Confirma a transação e grava definitivamente todas as alterações realizadas no bloco.
            $pdo->commit();

            // Verifica a condição abaixo antes de executar este bloco.
            if ($fotoSalva !== null && $fotoAtual !== null && $fotoAtual !== $fotoSalva) {
                // Armazena em "arquivoAntigo" o valor necessário para as próximas etapas deste fluxo.
                $arquivoAntigo = __DIR__ . '/' . ltrim($fotoAtual, '/');
                // Verifica a condição abaixo antes de executar este bloco.
                if (is_file($arquivoAntigo)) {
                    @unlink($arquivoAntigo);
                }
            }

            // Verifica a condição abaixo antes de executar este bloco.
            if ($modoEdicao) {
                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: ficha-pet.php?id=' . $editarId . '&editado=1');
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: cadastro-pet.php?salvo=1');
            }
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
        } catch (Throwable $erro) {
            // Confere se existe uma transação aberta antes de tentar desfazê-la.
            if ($pdo->inTransaction()) {
                // Desfaz as alterações da transação atual porque alguma etapa falhou.
                $pdo->rollBack();
            }

            // Verifica a condição abaixo antes de executar este bloco.
            if ($fotoSalva !== null) {
                // Armazena em "arquivo" o valor necessário para as próximas etapas deste fluxo.
                $arquivo = __DIR__ . '/' . ltrim($fotoSalva, '/');
                // Verifica a condição abaixo antes de executar este bloco.
                if (is_file($arquivo)) {
                    @unlink($arquivo);
                }
            }

            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = $erro instanceof RuntimeException
                ? $erro->getMessage()
                : 'Não foi possível salvar o pet.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        }
    }
}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvo'])) {
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Pet cadastrado com sucesso!';
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success';
    // Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
    $dados = [];
}

// Preserva o valor selecionado de "tutorSelecionadoId" para preencher o formulário corretamente.
$tutorSelecionadoId = inteiroOuNull($dados['cliente_id'] ?? null);
// Preserva o valor selecionado de "tutorSelecionado" para preencher o formulário corretamente.
$tutorSelecionado = buscarTutorParaCampo($pdo, $tutorSelecionadoId);
// Preserva o valor selecionado de "rotuloTutorSelecionado" para preencher o formulário corretamente.
$rotuloTutorSelecionado = $tutorSelecionado
    ? (string) $tutorSelecionado['nome']
    : '';

// Armazena a coleção "especies" carregada para ser percorrida e exibida/processada a seguir.
$especies = $pdo
    ->query('SELECT id, nome FROM especies WHERE ativo = TRUE ORDER BY nome')
    // Transforma todas as linhas retornadas pela consulta em uma lista para uso na página.
    ->fetchAll();

// Conta quantos pets ativos existem para exibir o total na interface.
$totalPets = (int) $pdo
    ->query('SELECT COUNT(*) FROM pets WHERE ativo = TRUE')
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn();
?>

<?php
// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = $modoEdicao ? 'Editar pet' : 'Cadastro de pet';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'cadastro-pet';

// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page registration-page">
                <!-- Bloco visual "page-heading" que organiza esta parte da interface. -->
                <div class="page-heading">
                    <div>
                        <h1><?= $modoEdicao ? 'Editar pet' : 'Cadastro de pets' ?></h1>
                        <small>Beauty Pet</small>
                    </div>
                    <span class="counter"><strong><?= $totalPets ?></strong> pets cadastrados</span>
                </div>

                <?php if ($mensagem !== ''): ?>
                    <div class="message <?= escapar($tipoMensagem) ?>">
                        <?= escapar($mensagem) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                <form class="form-card" method="post" enctype="multipart/form-data" action="cadastro-pet.php<?= $modoEdicao ? '?editar=' . (int) $editarId : '' ?>">
                    <?php if ($modoEdicao): ?>
                        <!-- Campo "pet_id" utilizado para enviar esta informação no formulário. -->
                        <input type="hidden" name="pet_id" value="<?= (int) $editarId ?>">
                        <!-- Campo "foto_atual" utilizado para enviar esta informação no formulário. -->
                        <input type="hidden" name="foto_atual" value="<?= escapar($fotoAtual) ?>">
                    <?php endif; ?>
                    <h2><?= $modoEdicao ? 'Editar dados do pet' : 'Novo cadastro de pet' ?></h2>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section">
                        <div class="form-grid">
                            <div class="photo-field">
                                <!-- Rótulo do campo "foto", indicando ao usuário qual informação deve ser preenchida. -->
                                <label class="photo-preview" id="photoPreview" for="foto" title="Adicionar ou trocar foto">
                                    <?php if (!empty($fotoAtual)): ?>
                                        <img src="<?= escapar($fotoAtual) ?>" alt="Foto atual do pet">
                                    <?php else: ?>
                                        <i class="fa-solid fa-paw"></i>
                                    <?php endif; ?>
                                </label>

                                <div class="photo-info">
                                    <strong>Foto do pet</strong>
                                    <small>Clique no quadrado ou selecione uma imagem JPG, PNG ou WEBP.</small>
                                    <!-- Campo "foto" utilizado para enviar esta informação no formulário. -->
                                    <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                                </div>
                            </div>

                            <div class="field span-6">
                                <!-- Rótulo do campo "Nome do pet", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="nome">Nome do pet</label>
                                <!-- Campo "nome" utilizado para enviar esta informação no formulário. -->
                                <input id="nome" name="nome" type="text"
                                    value="<?= escapar($dados['nome'] ?? '') ?>"
                                    placeholder="Nome do pet" required>
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Espécie", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="especie">Espécie</label>
                                <!-- Lista de seleção "especie_id" com as opções válidas para este dado. -->
                                <select id="especie" name="especie_id" required>
                                    <option value="">Selecione</option>

                                    <?php foreach ($especies as $especie): ?>
                                        <option value="<?= (int) $especie['id'] ?>"
                                            <?= ((string) ($dados['especie_id'] ?? '') === (string) $especie['id']) ? 'selected' : '' ?>>
                                            <?= escapar($especie['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Raça", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="raca">Raça</label>
                                <!-- Campo "raca" utilizado para enviar esta informação no formulário. -->
                                <input id="raca" name="raca" type="text"
                                    value="<?= escapar($dados['raca'] ?? '') ?>"
                                    placeholder="Raça do pet">
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Cor", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cor">Cor</label>
                                <!-- Campo "cor" utilizado para enviar esta informação no formulário. -->
                                <input id="cor" name="cor" type="text"
                                    value="<?= escapar($dados['cor'] ?? '') ?>"
                                    placeholder="Cor">
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Sexo", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="sexo">Sexo</label>
                                <!-- Lista de seleção "sexo" com as opções válidas para este dado. -->
                                <select id="sexo" name="sexo" required>
                                    <option value="NAO_INFORMADO"
                                        <?= (($dados['sexo'] ?? 'NAO_INFORMADO') === 'NAO_INFORMADO') ? 'selected' : '' ?>>
                                        Não informado
                                    </option>
                                    <option value="MACHO"
                                        <?= (($dados['sexo'] ?? '') === 'MACHO') ? 'selected' : '' ?>>
                                        Macho
                                    </option>
                                    <option value="FEMEA"
                                        <?= (($dados['sexo'] ?? '') === 'FEMEA') ? 'selected' : '' ?>>
                                        Fêmea
                                    </option>
                                </select>
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Peso (kg)", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="peso">Peso (kg)</label>
                                <!-- Campo "peso" utilizado para enviar esta informação no formulário. -->
                                <input id="peso" name="peso" type="number" min="0" step="0.01"
                                    value="<?= escapar($dados['peso'] ?? '') ?>"
                                    placeholder="Ex.: 10,5">
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Porte", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="porte">Porte</label>
                                <!-- Lista de seleção "porte" com as opções válidas para este dado. -->
                                <select id="porte" name="porte">
                                    <option value="">Selecione</option>
                                    <option value="PEQUENO" <?= (($dados['porte'] ?? '') === 'PEQUENO') ? 'selected' : '' ?>>Pequeno</option>
                                    <option value="MEDIO" <?= (($dados['porte'] ?? '') === 'MEDIO') ? 'selected' : '' ?>>Médio</option>
                                    <option value="GRANDE" <?= (($dados['porte'] ?? '') === 'GRANDE') ? 'selected' : '' ?>>Grande</option>
                                </select>
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "Data de nascimento", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="nascimento">Data de nascimento</label>
                                <!-- Campo "data_nascimento" utilizado para enviar esta informação no formulário. -->
                                <input id="nascimento" name="data_nascimento" type="date"
                                    value="<?= escapar($dados['data_nascimento'] ?? '') ?>">
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "Tutor responsável", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="tutorBusca">Tutor responsável</label>
                                <div
                                    class="search-picker"
                                    data-search-picker="tutor"
                                    data-required="1">
                                    <!-- Bloco visual "search-picker-input-wrap" que organiza esta parte da interface. -->
                                    <div class="search-picker-input-wrap">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <input
                                            id="tutorBusca"
                                            data-search-input
                                            type="search"
                                            autocomplete="off"
                                            value="<?= escapar($rotuloTutorSelecionado) ?>"
                                            data-selected-label="<?= escapar($rotuloTutorSelecionado) ?>"
                                            placeholder="Digite o nome, CPF, telefone ou e-mail do tutor"
                                            required>
                                        <input
                                            data-search-value
                                            type="hidden"
                                            name="cliente_id"
                                            value="<?= $tutorSelecionadoId !== null ? (int) $tutorSelecionadoId : '' ?>">
                                    </div>
                                    <!-- Bloco visual "search-picker-results" que organiza esta parte da interface. -->
                                    <div class="search-picker-results" data-search-results hidden></div>
                                </div>
                                <small class="field-help">Digite para pesquisar. O sistema não carrega milhares de tutores de uma vez.</small>
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "Castrado(a)", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="castrado">Castrado(a)</label>
                                <!-- Lista de seleção "castrado" com as opções válidas para este dado. -->
                                <select id="castrado" name="castrado">
                                    <option value="">Não informado</option>
                                    <option value="1" <?= (($dados['castrado'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= (($dados['castrado'] ?? '') === '0') ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>

                            <div class="checks-row">
                                <div class="checkbox-field">
                                    <!-- Campo "nascimento_aproximado" utilizado para enviar esta informação no formulário. -->
                                    <input id="aproximado" name="nascimento_aproximado" type="checkbox" value="1"
                                        <?= isset($dados['nascimento_aproximado']) ? 'checked' : '' ?>>
                                    <!-- Rótulo do campo "Data de nascimento aproximada", indicando ao usuário qual informação deve ser preenchida. -->
                                    <label for="aproximado">Data de nascimento aproximada</label>
                                </div>

                                <div class="checkbox-field">
                                    <!-- Campo "vacinacao_em_dia" utilizado para enviar esta informação no formulário. -->
                                    <input id="vacinado" name="vacinacao_em_dia" type="checkbox" value="1"
                                        <?= isset($dados['vacinacao_em_dia']) ? 'checked' : '' ?>>
                                    <!-- Rótulo do campo "Vacinação em dia", indicando ao usuário qual informação deve ser preenchida. -->
                                    <label for="vacinado">Vacinação em dia</label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section">
                        <h3>Anamnese</h3>

                        <div class="form-grid">
                            <div class="field span-6">
                                <!-- Rótulo do campo "Comportamento", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="comportamento">Comportamento</label>
                                <!-- Área de texto "observacoes_comportamentais" para registrar observações livres sobre o registro. -->
                                <textarea id="comportamento" name="observacoes_comportamentais"
                                    placeholder="Bravo, sociável, agitado, medroso..."><?= escapar($dados['observacoes_comportamentais'] ?? '') ?></textarea>
                            </div>

                            <div class="field span-6">
                                <!-- Rótulo do campo "Medicamentos", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="medicamentos">Medicamentos</label>
                                <!-- Área de texto "medicamentos" para registrar observações livres sobre o registro. -->
                                <textarea id="medicamentos" name="medicamentos"
                                    placeholder="Medicamentos de uso contínuo ou recente"><?= escapar($dados['medicamentos'] ?? '') ?></textarea>
                            </div>

                            <div class="field span-12">
                                <!-- Rótulo do campo "Observações médicas", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="observacoesMedicas">Observações médicas</label>
                                <!-- Área de texto "observacoes_medicas" para registrar observações livres sobre o registro. -->
                                <textarea id="observacoesMedicas" name="observacoes_medicas"
                                    placeholder="Alergias, doenças, restrições e outras observações"><?= escapar($dados['observacoes_medicas'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <?php if ($modoEdicao): ?>
                            <a class="secondary-action-button" href="ficha-pet.php?id=<?= (int) $editarId ?>">Cancelar</a>
                        <?php endif; ?>
                        <button class="save-button" type="submit"><?= $modoEdicao ? 'Salvar alterações' : 'Salvar' ?></button>
                    </div>
                </form>
            </div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
