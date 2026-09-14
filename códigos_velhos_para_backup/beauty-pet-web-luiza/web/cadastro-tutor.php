<?php
/**
 * ARQUIVO: cadastro-tutor.php
 * RESPONSABILIDADE: Cadastro e edição de tutores.
 * Valida CPF e dados de contato/endereço, impede duplicidades e grava ou atualiza o cadastro do tutor.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';

/*
 * Proteção extra: evita o erro de função indefinida caso um
 * funcoes.php antigo seja mantido por engano.
 */
if (!function_exists('cpfValido')) {
    /**
     * Valida matematicamente os dígitos verificadores de um CPF.
     * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
     */
    function cpfValido(?string $cpf): bool
    {
        // Guarda apenas os números do CPF para validar e pesquisar sem influência da máscara visual.
        $cpf = preg_replace('/\D+/', '', $cpf ?? '');

        // Verifica a condição abaixo antes de executar este bloco.
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return false;
        }

        // Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
        for ($posicao = 9; $posicao <= 10; $posicao++) {
            // Armazena em "soma" o valor necessário para as próximas etapas deste fluxo.
            $soma = 0;

            // Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
            for ($indice = 0; $indice < $posicao; $indice++) {
                $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice);
            }

            // Armazena em "digito" o valor necessário para as próximas etapas deste fluxo.
            $digito = (10 * $soma) % 11;
            // Armazena em "digito" o valor necessário para as próximas etapas deste fluxo.
            $digito = $digito === 10 ? 0 : $digito;

            // Verifica a condição abaixo antes de executar este bloco.
            if ((int) $cpf[$posicao] !== $digito) {
                // Devolve o resultado calculado para o ponto que chamou esta função.
                return false;
            }
        }

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return true;
    }
}

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
// Guarda a mensagem de erro específica encontrada durante a validação do CPF.
$erroCpf = false;
// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? $_POST['tutor_id'] ?? null);
// Indica se o formulário está alterando um registro existente em vez de criar um novo.
$modoEdicao = $editarId !== null;
// Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
$dados = $_POST;

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($modoEdicao && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id AND ativo = TRUE LIMIT 1');
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $editarId]);
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $dados = $stmt->fetch() ?: [];

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$dados) {
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: fichas-tutores.php');
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit;
    }
}

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guarda o nome informado no formulário após a normalização necessária.
    $nome = textoOuNull($_POST['nome'] ?? null);
    // Guarda apenas os números do CPF para validar e pesquisar sem influência da máscara visual.
    $cpf = somenteNumeros($_POST['cpf'] ?? null);
    // Armazena em "telefonePrincipal" o valor necessário para as próximas etapas deste fluxo.
    $telefonePrincipal = somenteNumeros($_POST['telefone_principal'] ?? null);
    // Armazena em "telefoneReserva" o valor necessário para as próximas etapas deste fluxo.
    $telefoneReserva = somenteNumeros($_POST['telefone_reserva'] ?? null);
    // Guarda o e-mail informado para validação, autenticação ou cadastro.
    $email = textoOuNull($_POST['email'] ?? null);
    // Armazena em "cep" o valor necessário para as próximas etapas deste fluxo.
    $cep = somenteNumeros($_POST['cep'] ?? null);

    // Verifica a condição abaixo antes de executar este bloco.
    if ($nome === null || $telefonePrincipal === null) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Preencha o nome e o telefone principal.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($cpf !== null && !cpfValido($cpf)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'CPF inválido. Confira os números digitados.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
        // Guarda a mensagem de erro específica encontrada durante a validação do CPF.
        $erroCpf = true;
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($cep !== null && strlen($cep) !== 8) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'O CEP deve possuir 8 números.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else {
        // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
        try {
            // Verifica a condição abaixo antes de executar este bloco.
            if ($cpf !== null) {
                // Monta a consulta SQL que será preparada e executada logo abaixo.
                $sqlCpf = 'SELECT id FROM clientes WHERE cpf = :cpf';
                // Armazena em "paramsCpf" o valor necessário para as próximas etapas deste fluxo.
                $paramsCpf = [':cpf' => $cpf];
                // Verifica a condição abaixo antes de executar este bloco.
                if ($modoEdicao) {
                    $sqlCpf .= ' AND id <> :id';
                    $paramsCpf[':id'] = $editarId;
                }
                // Prepara a atualização do registro correspondente em "clientes" com os novos dados.
                $stmt = $pdo->prepare($sqlCpf . ' LIMIT 1');
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($paramsCpf);
                // Verifica a condição abaixo antes de executar este bloco.
                if ($stmt->fetch()) {
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('Já existe um tutor cadastrado com esse CPF.');
                }
            }

            // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
            $parametros = [
                ':nome'               => $nome,
                ':cpf'                => $cpf,
                ':email'              => $email,
                ':telefone_principal' => $telefonePrincipal,
                ':telefone_reserva'   => $telefoneReserva,
                ':cep'                => $cep,
                ':logradouro'         => textoOuNull($_POST['logradouro'] ?? null),
                ':numero'             => textoOuNull($_POST['numero'] ?? null),
                ':complemento'        => textoOuNull($_POST['complemento'] ?? null),
                ':bairro'             => textoOuNull($_POST['bairro'] ?? null),
                ':cidade'             => textoOuNull($_POST['cidade'] ?? null),
                ':estado'             => textoOuNull($_POST['estado'] ?? null),
                ':referencia'         => textoOuNull($_POST['referencia'] ?? null),
                ':observacoes'        => textoOuNull($_POST['observacoes'] ?? null),
            ];

            // Verifica a condição abaixo antes de executar este bloco.
            if ($modoEdicao) {
                // Prepara a inclusão de um novo registro em "clientes" usando os valores já validados.
                $sql = 'UPDATE clientes SET
                    nome = :nome,
                    cpf = :cpf,
                    email = :email,
                    telefone_principal = :telefone_principal,
                    telefone_reserva = :telefone_reserva,
                    cep = :cep,
                    logradouro = :logradouro,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cidade = :cidade,
                    estado = :estado,
                    referencia = :referencia,
                    observacoes = :observacoes
                    WHERE id = :id';
                $parametros[':id'] = $editarId;
                // Prepara a inclusão de um novo registro em "clientes" usando os valores já validados.
                $stmt = $pdo->prepare($sql);
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($parametros);

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: ficha-tutor.php?id=' . $editarId . '&editado=1');
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            }

            // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
            $sql = 'INSERT INTO clientes (
                nome, cpf, email, telefone_principal, telefone_reserva,
                cep, logradouro, numero, complemento, bairro, cidade,
                estado, referencia, observacoes
            ) VALUES (
                :nome, :cpf, :email, :telefone_principal, :telefone_reserva,
                :cep, :logradouro, :numero, :complemento, :bairro, :cidade,
                :estado, :referencia, :observacoes
            )';

            // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare($sql);
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute($parametros);

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header('Location: cadastro-tutor.php?salvo=1');
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit;
        // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
        } catch (Throwable $erro) {
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = $erro instanceof RuntimeException
                ? $erro->getMessage()
                : 'Não foi possível salvar o tutor.';
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        }
    }
}

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvo'])) {
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Tutor cadastrado com sucesso!';
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success';
    // Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
    $dados = [];
}

// Conta quantos tutores ativos existem para exibir o total na interface.
$totalTutores = (int) $pdo
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE')
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn();
?>

<?php
// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = $modoEdicao ? 'Editar tutor' : 'Cadastro de tutor';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'cadastro-tutor';

// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<div class="page registration-page">
                <!-- Bloco visual "page-heading" que organiza esta parte da interface. -->
                <div class="page-heading">
                    <div>
                        <h1><?= $modoEdicao ? 'Editar tutor' : 'Cadastro de tutores' ?></h1>
                        <small>Beauty Pet</small>
                    </div>
                    <span class="counter"><strong><?= $totalTutores ?></strong> tutores cadastrados</span>
                </div>

                <?php if ($mensagem !== ''): ?>
                    <div class="message <?= escapar($tipoMensagem) ?>">
                        <?= escapar($mensagem) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                <form class="form-card" method="post" action="cadastro-tutor.php<?= $modoEdicao ? '?editar=' . (int) $editarId : '' ?>">
                    <!-- Campo "tutor_id" utilizado para enviar esta informação no formulário. -->
                    <?php if ($modoEdicao): ?><input type="hidden" name="tutor_id" value="<?= (int) $editarId ?>"><?php endif; ?>
                    <h2><?= $modoEdicao ? 'Editar dados do tutor' : 'Novo cadastro de tutor' ?></h2>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section">
                        <div class="form-grid">
                            <div class="field span-12">
                                <!-- Rótulo do campo "Nome completo", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="nome">Nome completo</label>
                                <input
                                    id="nome"
                                    name="nome"
                                    type="text"
                                    value="<?= escapar($dados['nome'] ?? '') ?>"
                                    placeholder="Nome do tutor"
                                    required>
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "CPF", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cpf">CPF</label>
                                <input
                                    id="cpf"
                                    name="cpf"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="14"
                                    class="<?= $erroCpf ? 'input-error' : '' ?>"
                                    aria-invalid="<?= $erroCpf ? 'true' : 'false' ?>"
                                    value="<?= escapar($dados['cpf'] ?? '') ?>"
                                    placeholder="000.000.000-00">

                                <?php if ($erroCpf): ?>
                                    <small class="field-error">
                                        O CPF não passou na validação dos dígitos verificadores.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "Telefone", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="telefone">Telefone</label>
                                <input
                                    id="telefone"
                                    name="telefone_principal"
                                    type="tel"
                                    value="<?= escapar($dados['telefone_principal'] ?? '') ?>"
                                    placeholder="(11) 99999-9999"
                                    required>
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "WhatsApp / telefone reserva", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="telefoneReserva">WhatsApp / telefone reserva</label>
                                <input
                                    id="telefoneReserva"
                                    name="telefone_reserva"
                                    type="tel"
                                    value="<?= escapar($dados['telefone_reserva'] ?? '') ?>"
                                    placeholder="(11) 99999-9999">
                            </div>

                            <div class="field span-12">
                                <!-- Rótulo do campo "E-mail", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="email">E-mail</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="<?= escapar($dados['email'] ?? '') ?>"
                                    placeholder="email@exemplo.com">
                            </div>
                        </div>
                    </section>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section">
                        <h3>Endereço</h3>

                        <div class="form-grid">
                            <div class="field span-9">
                                <!-- Rótulo do campo "Rua", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="logradouro">Rua</label>
                                <!-- Campo "logradouro" utilizado para enviar esta informação no formulário. -->
                                <input id="logradouro" name="logradouro" type="text"
                                    value="<?= escapar($dados['logradouro'] ?? '') ?>" placeholder="Rua">
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Número", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="numero">Número</label>
                                <!-- Campo "numero" utilizado para enviar esta informação no formulário. -->
                                <input id="numero" name="numero" type="text"
                                    value="<?= escapar($dados['numero'] ?? '') ?>" placeholder="Número">
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "CEP", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cep">CEP</label>
                                <!-- Campo "cep" utilizado para enviar esta informação no formulário. -->
                                <input id="cep" name="cep" type="text" inputmode="numeric" maxlength="9"
                                    value="<?= escapar($dados['cep'] ?? '') ?>" placeholder="00000-000">
                            </div>

                            <div class="field span-3">
                                <!-- Rótulo do campo "Bairro", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="bairro">Bairro</label>
                                <!-- Campo "bairro" utilizado para enviar esta informação no formulário. -->
                                <input id="bairro" name="bairro" type="text"
                                    value="<?= escapar($dados['bairro'] ?? '') ?>" placeholder="Bairro">
                            </div>

                            <div class="field span-4">
                                <!-- Rótulo do campo "Cidade", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cidade">Cidade</label>
                                <!-- Campo "cidade" utilizado para enviar esta informação no formulário. -->
                                <input id="cidade" name="cidade" type="text"
                                    value="<?= escapar($dados['cidade'] ?? '') ?>" placeholder="Cidade">
                            </div>

                            <div class="field span-2">
                                <!-- Rótulo do campo "Estado", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="estado">Estado</label>
                                <!-- Lista de seleção "estado" com as opções válidas para este dado. -->
                                <select id="estado" name="estado">
                                    <option value="">UF</option>
                                    <?php
                                    // Armazena a coleção "estados" carregada para ser percorrida e exibida/processada a seguir.
                                    $estados = [
                                        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS',
                                        'MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC',
                                        'SP','SE','TO'
                                    ];
                                    // Percorre cada item da coleção para processar os registros individualmente.
                                    foreach ($estados as $estado):
                                    ?>
                                        <option value="<?= $estado ?>"
                                            <?= (($dados['estado'] ?? '') === $estado) ? 'selected' : '' ?>>
                                            <?= $estado ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field span-6">
                                <!-- Rótulo do campo "Complemento", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="complemento">Complemento</label>
                                <!-- Campo "complemento" utilizado para enviar esta informação no formulário. -->
                                <input id="complemento" name="complemento" type="text"
                                    value="<?= escapar($dados['complemento'] ?? '') ?>"
                                    placeholder="Apartamento, bloco, casa...">
                            </div>

                            <div class="field span-6">
                                <!-- Rótulo do campo "Ponto de referência", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="referencia">Ponto de referência</label>
                                <!-- Campo "referencia" utilizado para enviar esta informação no formulário. -->
                                <input id="referencia" name="referencia" type="text"
                                    value="<?= escapar($dados['referencia'] ?? '') ?>"
                                    placeholder="Próximo a...">
                            </div>

                            <div class="field span-12">
                                <!-- Rótulo do campo "Observações", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="observacoes">Observações</label>
                                <!-- Área de texto "observacoes" para registrar observações livres sobre o registro. -->
                                <textarea id="observacoes" name="observacoes"
                                    placeholder="Observações sobre o tutor"><?= escapar($dados['observacoes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <?php if ($modoEdicao): ?>
                            <a class="secondary-action-button" href="ficha-tutor.php?id=<?= (int) $editarId ?>">Cancelar</a>
                        <?php endif; ?>
                        <button class="save-button" type="submit"><?= $modoEdicao ? 'Salvar alterações' : 'Salvar' ?></button>
                    </div>
                </form>
            </div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
