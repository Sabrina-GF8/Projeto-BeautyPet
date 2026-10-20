<?php // Executa esta instrução como parte da regra de negócio desta página.
/**
 * ARQUIVO: cadastro-tutor.php
 * RESPONSABILIDADE: Cadastro e edição de tutores.
 * Valida CPF e dados de contato/endereço, impede duplicidades e grava ou atualiza o cadastro do tutor.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php'; // Carrega a configuração ou função compartilhada necessária nesta página.

/*
 * Proteção extra: evita o erro de função indefinida caso um
 * funcoes.php antigo seja mantido por engano.
 */
if (!function_exists('cpfValido')) { // Testa esta condição antes de executar o bloco correspondente.
    /**
     * Valida matematicamente os dígitos verificadores de um CPF.
     * Mantém esta regra isolada para evitar repetição e facilitar manutenção/testes.
     */
    function cpfValido(?string $cpf): bool // Define uma função reutilizável que concentra esta regra do sistema.
    { // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        // Guarda apenas os números do CPF para validar e pesquisar sem influência da máscara visual.
        $cpf = preg_replace('/\D+/', '', $cpf ?? ''); // Armazena em cpf o valor usado nas próximas etapas deste fluxo.

        // Verifica a condição abaixo antes de executar este bloco.
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) { // Testa esta condição antes de executar o bloco correspondente.
            // Devolve o resultado calculado para o ponto que chamou esta função.
            return false; // Devolve este resultado para quem chamou a função.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
        for ($posicao = 9; $posicao <= 10; $posicao++) { // Repete este bloco enquanto o controle definido permitir.
            // Armazena em "soma" o valor necessário para as próximas etapas deste fluxo.
            $soma = 0; // Armazena em soma o valor usado nas próximas etapas deste fluxo.

            // Repete o bloco abaixo enquanto o contador estiver dentro do intervalo definido.
            for ($indice = 0; $indice < $posicao; $indice++) { // Repete este bloco enquanto o controle definido permitir.
                $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice); // Executa esta instrução como parte da regra de negócio desta página.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Armazena em "digito" o valor necessário para as próximas etapas deste fluxo.
            $digito = (10 * $soma) % 11; // Armazena em digito o valor usado nas próximas etapas deste fluxo.
            // Armazena em "digito" o valor necessário para as próximas etapas deste fluxo.
            $digito = $digito === 10 ? 0 : $digito; // Armazena em digito o valor usado nas próximas etapas deste fluxo.

            // Verifica a condição abaixo antes de executar este bloco.
            if ((int) $cpf[$posicao] !== $digito) { // Testa esta condição antes de executar o bloco correspondente.
                // Devolve o resultado calculado para o ponto que chamou esta função.
                return false; // Devolve este resultado para quem chamou a função.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

        // Devolve o resultado calculado para o ponto que chamou esta função.
        return true; // Devolve este resultado para quem chamou a função.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = ''; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = ''; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
// Guarda a mensagem de erro específica encontrada durante a validação do CPF.
$erroCpf = false; // Armazena em erroCpf o valor usado nas próximas etapas deste fluxo.
// Lê o identificador enviado pela URL para saber se a página está em modo de edição.
$editarId = inteiroOuNull($_GET['editar'] ?? $_POST['tutor_id'] ?? null); // Armazena em editarId o valor usado nas próximas etapas deste fluxo.
// Indica se o formulário está alterando um registro existente em vez de criar um novo.
$modoEdicao = $editarId !== null; // Armazena em modoEdicao o valor usado nas próximas etapas deste fluxo.
// Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
$dados = $_POST; // Armazena em dados o valor usado nas próximas etapas deste fluxo.

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($modoEdicao && $_SERVER['REQUEST_METHOD'] === 'GET') { // Testa esta condição antes de executar o bloco correspondente.
    // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id AND ativo = TRUE LIMIT 1'); // Prepara a consulta SQL com parâmetros separados para segurança.
    // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
    $stmt->execute([':id' => $editarId]); // Executa a consulta preparada enviando os valores necessários.
    // Obtém a próxima linha retornada pela consulta como um registro associativo.
    $dados = $stmt->fetch() ?: []; // Obtém uma linha retornada pelo banco.

    // Verifica a condição abaixo antes de executar este bloco.
    if (!$dados) { // Testa esta condição antes de executar o bloco correspondente.
        // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
        header('Location: fichas-tutores.php'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
        // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
        exit; // Encerra a execução desta requisição.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Testa esta condição antes de executar o bloco correspondente.
    // Guarda o nome informado no formulário após a normalização necessária.
    $nome = textoOuNull($_POST['nome'] ?? null); // Armazena em nome o valor usado nas próximas etapas deste fluxo.
    // Guarda apenas os números do CPF para validar e pesquisar sem influência da máscara visual.
    $cpf = somenteNumeros($_POST['cpf'] ?? null); // Armazena em cpf o valor usado nas próximas etapas deste fluxo.
    // Armazena em "telefonePrincipal" o valor necessário para as próximas etapas deste fluxo.
    $telefonePrincipal = somenteNumeros($_POST['telefone_principal'] ?? null); // Armazena em telefonePrincipal o valor usado nas próximas etapas deste fluxo.
    // Armazena em "telefoneReserva" o valor necessário para as próximas etapas deste fluxo.
    $telefoneReserva = somenteNumeros($_POST['telefone_reserva'] ?? null); // Armazena em telefoneReserva o valor usado nas próximas etapas deste fluxo.
    // Guarda o e-mail informado para validação, autenticação ou cadastro.
    $email = textoOuNull($_POST['email'] ?? null); // Armazena em email o valor usado nas próximas etapas deste fluxo.
    // Armazena em "cep" o valor necessário para as próximas etapas deste fluxo.
    $cep = somenteNumeros($_POST['cep'] ?? null); // Armazena em cep o valor usado nas próximas etapas deste fluxo.

    // Verifica a condição abaixo antes de executar este bloco.
    if ($nome === null || $telefonePrincipal === null) { // Testa esta condição antes de executar o bloco correspondente.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Preencha o nome e o telefone principal.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($cpf !== null && !cpfValido($cpf)) { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'CPF inválido. Confira os números digitados.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
        // Guarda a mensagem de erro específica encontrada durante a validação do CPF.
        $erroCpf = true; // Armazena em erroCpf o valor usado nas próximas etapas deste fluxo.
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($cep !== null && strlen($cep) !== 8) { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'O CEP deve possuir 8 números.'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else { // Executa esta instrução como parte da regra de negócio desta página.
        // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
        try { // Executa esta instrução como parte da regra de negócio desta página.
            // Verifica a condição abaixo antes de executar este bloco.
            if ($cpf !== null) { // Testa esta condição antes de executar o bloco correspondente.
                // Monta a consulta SQL que será preparada e executada logo abaixo.
                $sqlCpf = 'SELECT id FROM clientes WHERE cpf = :cpf'; // Armazena em sqlCpf o valor usado nas próximas etapas deste fluxo.
                // Armazena em "paramsCpf" o valor necessário para as próximas etapas deste fluxo.
                $paramsCpf = [':cpf' => $cpf]; // Armazena em paramsCpf o valor usado nas próximas etapas deste fluxo.
                // Verifica a condição abaixo antes de executar este bloco.
                if ($modoEdicao) { // Testa esta condição antes de executar o bloco correspondente.
                    $sqlCpf .= ' AND id <> :id'; // Executa esta instrução como parte da regra de negócio desta página.
                    $paramsCpf[':id'] = $editarId; // Executa esta instrução como parte da regra de negócio desta página.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
                // Prepara a atualização do registro correspondente em "clientes" com os novos dados.
                $stmt = $pdo->prepare($sqlCpf . ' LIMIT 1'); // Prepara a consulta SQL com parâmetros separados para segurança.
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($paramsCpf); // Executa a consulta preparada enviando os valores necessários.
                // Verifica a condição abaixo antes de executar este bloco.
                if ($stmt->fetch()) { // Testa esta condição antes de executar o bloco correspondente.
                    // Interrompe este fluxo com uma exceção controlada porque a regra necessária não foi atendida.
                    throw new RuntimeException('Já existe um tutor cadastrado com esse CPF.'); // Lança uma exceção para interromper a operação com uma mensagem controlada.
                } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Agrupa os parâmetros enviados à consulta preparada, mantendo os dados separados do comando SQL.
            $parametros = [ // Armazena em parametros o valor usado nas próximas etapas deste fluxo.
                ':nome'               => $nome, // Executa esta instrução como parte da regra de negócio desta página.
                ':cpf'                => $cpf, // Executa esta instrução como parte da regra de negócio desta página.
                ':email'              => $email, // Executa esta instrução como parte da regra de negócio desta página.
                ':telefone_principal' => $telefonePrincipal, // Executa esta instrução como parte da regra de negócio desta página.
                ':telefone_reserva'   => $telefoneReserva, // Executa esta instrução como parte da regra de negócio desta página.
                ':cep'                => $cep, // Executa esta instrução como parte da regra de negócio desta página.
                ':logradouro'         => textoOuNull($_POST['logradouro'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':numero'             => textoOuNull($_POST['numero'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':complemento'        => textoOuNull($_POST['complemento'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':bairro'             => textoOuNull($_POST['bairro'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':cidade'             => textoOuNull($_POST['cidade'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':estado'             => textoOuNull($_POST['estado'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':referencia'         => textoOuNull($_POST['referencia'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
                ':observacoes'        => textoOuNull($_POST['observacoes'] ?? null), // Executa esta instrução como parte da regra de negócio desta página.
            ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Verifica a condição abaixo antes de executar este bloco.
            if ($modoEdicao) { // Testa esta condição antes de executar o bloco correspondente.
                // Prepara a inclusão de um novo registro em "clientes" usando os valores já validados.
                $sql = 'UPDATE clientes SET -- Continua a instrução SQL iniciada nas linhas relacionadas.
                    nome = :nome, -- Define a coluna ou expressão nome desta estrutura.
                    cpf = :cpf, -- Define a coluna ou expressão cpf desta estrutura.
                    email = :email, -- Define a coluna ou expressão email desta estrutura.
                    telefone_principal = :telefone_principal, -- Define a coluna ou expressão telefone_principal desta estrutura.
                    telefone_reserva = :telefone_reserva, -- Define a coluna ou expressão telefone_reserva desta estrutura.
                    cep = :cep, -- Define a coluna ou expressão cep desta estrutura.
                    logradouro = :logradouro, -- Define a coluna ou expressão logradouro desta estrutura.
                    numero = :numero, -- Define a coluna ou expressão numero desta estrutura.
                    complemento = :complemento, -- Define a coluna ou expressão complemento desta estrutura.
                    bairro = :bairro, -- Define a coluna ou expressão bairro desta estrutura.
                    cidade = :cidade, -- Define a coluna ou expressão cidade desta estrutura.
                    estado = :estado, -- Define a coluna ou expressão estado desta estrutura.
                    referencia = :referencia, -- Define a coluna ou expressão referencia desta estrutura.
                    observacoes = :observacoes -- Define a coluna ou expressão observacoes desta estrutura.
                    WHERE id = :id'; // Executa esta instrução como parte da regra de negócio desta página.
                $parametros[':id'] = $editarId; // Executa esta instrução como parte da regra de negócio desta página.
                // Prepara a inclusão de um novo registro em "clientes" usando os valores já validados.
                $stmt = $pdo->prepare($sql); // Prepara a consulta SQL com parâmetros separados para segurança.
                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute($parametros); // Executa a consulta preparada enviando os valores necessários.

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: ficha-tutor.php?id=' . $editarId . '&editado=1'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit; // Encerra a execução desta requisição.
            } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

            // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
            $sql = 'INSERT INTO clientes ( -- Continua a instrução SQL iniciada nas linhas relacionadas.
                nome, cpf, email, telefone_principal, telefone_reserva, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                cep, logradouro, numero, complemento, bairro, cidade, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                estado, referencia, observacoes -- Continua a instrução SQL iniciada nas linhas relacionadas.
            ) VALUES ( -- Continua a instrução SQL iniciada nas linhas relacionadas.
                :nome, :cpf, :email, :telefone_principal, :telefone_reserva, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                :cep, :logradouro, :numero, :complemento, :bairro, :cidade, -- Continua a instrução SQL iniciada nas linhas relacionadas.
                :estado, :referencia, :observacoes -- Continua a instrução SQL iniciada nas linhas relacionadas.
            )'; // Executa esta instrução como parte da regra de negócio desta página.

            // Prepara uma consulta em "clientes" para carregar os dados necessários a esta etapa.
            $stmt = $pdo->prepare($sql); // Prepara a consulta SQL com parâmetros separados para segurança.
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $stmt->execute($parametros); // Executa a consulta preparada enviando os valores necessários.

            // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
            header('Location: cadastro-tutor.php?salvo=1'); // Envia um cabeçalho HTTP, geralmente para redirecionar o navegador.
            // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
            exit; // Encerra a execução desta requisição.
        // Captura uma falha ocorrida no bloco anterior para desfazer operações e exibir uma mensagem controlada.
        } catch (Throwable $erro) { // Executa esta instrução como parte da regra de negócio desta página.
            // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
            $mensagem = $erro instanceof RuntimeException // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
                ? $erro->getMessage() // Executa esta instrução como parte da regra de negócio desta página.
                : 'Não foi possível salvar o tutor.'; // Executa esta instrução como parte da regra de negócio desta página.
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
        } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
    } // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// Trata os parâmetros da URL usados principalmente para exibir mensagens após redirecionamentos.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvo'])) { // Testa esta condição antes de executar o bloco correspondente.
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Tutor cadastrado com sucesso!'; // Armazena em mensagem o valor usado nas próximas etapas deste fluxo.
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success'; // Armazena em tipoMensagem o valor usado nas próximas etapas deste fluxo.
    // Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
    $dados = []; // Armazena em dados o valor usado nas próximas etapas deste fluxo.
} // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.

// Conta quantos tutores ativos existem para exibir o total na interface.
$totalTutores = (int) $pdo // Armazena em totalTutores o valor usado nas próximas etapas deste fluxo.
    ->query('SELECT COUNT(*) FROM clientes WHERE ativo = TRUE') // Executa uma consulta direta ao PostgreSQL.
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn(); // Obtém a primeira coluna do resultado, normalmente um ID, total ou contagem.
/* [BP-ALTERAÇÃO #9] O comentário "//" ficava depois do fechamento do PHP e aparecia como texto na tela. */ ?>

<?php // Executa esta instrução como parte da regra de negócio desta página.
// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = $modoEdicao ? 'Editar tutor' : 'Cadastro de tutor'; // Armazena em tituloPagina o valor usado nas próximas etapas deste fluxo.
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'cadastro-tutor'; // Armazena em paginaAtiva o valor usado nas próximas etapas deste fluxo.

// Define o(s) CSS desta página em assets/css/ (cadastro.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'cadastro';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php'; // Carrega a configuração ou função compartilhada necessária nesta página.
/* [BP-ALTERAÇÃO #9] O comentário "//" ficava depois do fechamento do PHP e aparecia como texto na tela. */ ?>

<div class="page registration-page"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                <!-- Bloco visual "page-heading" que organiza esta parte da interface. -->
                <div class="page-heading"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                    <div> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                        <h1><?= $modoEdicao ? 'Editar tutor' : 'Cadastro de tutores' ?></h1> <?php /* Exibe o título principal. */ ?>
                        <small>Beauty Pet</small> <?php /* Cria ou abre o elemento HTML small. */ ?>
                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                    <span class="counter"><strong><?= $totalTutores ?></strong> tutores cadastrados</span> <?php /* Cria um trecho em linha para texto ou ícone. */ ?>
                </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                <?php if ($mensagem !== ''): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                    <div class="message <?= escapar($tipoMensagem) ?>"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                        <?= escapar($mensagem) ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>

                <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                <form class="form-card" method="post" action="cadastro-tutor.php<?= $modoEdicao ? '?editar=' . (int) $editarId : '' ?>"> <?php /* Abre um formulário para envio de dados. */ ?>
                    <!-- Campo "tutor_id" utilizado para enviar esta informação no formulário. -->
                    <?php if ($modoEdicao): ?><input type="hidden" name="tutor_id" value="<?= (int) $editarId ?>"><?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                    <h2><?= $modoEdicao ? 'Editar dados do tutor' : 'Novo cadastro de tutor' ?></h2> <?php /* Exibe um título de seção. */ ?>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section"> <?php /* Abre uma seção de conteúdo da página. */ ?>
                        <div class="form-grid"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                            <div class="field span-12"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Nome completo", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="nome">Nome completo</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <input <?php /* Cria um campo para entrada de dados. */ ?>
                                    id="nome" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    name="nome" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    type="text" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    value="<?= escapar($dados['nome'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Nome do tutor" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    required> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "CPF", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cpf">CPF</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <input <?php /* Cria um campo para entrada de dados. */ ?>
                                    data-mascara="cpf" <?php /* [BP-ALTERAÇÃO #6] Máscara 000.000.000-00 (assets/js/mascaras.js). */ ?>
                                    id="cpf" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    name="cpf" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    type="text" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    inputmode="numeric" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    maxlength="14" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    class="<?= $erroCpf ? 'input-error' : '' ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    aria-invalid="<?= $erroCpf ? 'true' : 'false' ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    value="<?= escapar($dados['cpf'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="000.000.000-00"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>

                                <?php if ($erroCpf): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    <small class="field-error"> <?php /* Cria ou abre o elemento HTML small. */ ?>
                                        O CPF não passou na validação dos dígitos verificadores. <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    </small> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Telefone", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="telefone">Telefone</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <input <?php /* Cria um campo para entrada de dados. */ ?>
                                    data-mascara="telefone" <?php /* [BP-ALTERAÇÃO #6] Máscara (00) 00000-0000. */ ?>
                                    id="telefone" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    name="telefone_principal" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    type="tel" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    value="<?= escapar($dados['telefone_principal'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="(11) 99999-9999" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    required> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "WhatsApp / telefone reserva", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="telefoneReserva">WhatsApp / telefone reserva</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <input <?php /* Cria um campo para entrada de dados. */ ?>
                                    data-mascara="telefone" <?php /* [BP-ALTERAÇÃO #6] Máscara (00) 00000-0000. */ ?>
                                    id="telefoneReserva" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    name="telefone_reserva" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    type="tel" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    value="<?= escapar($dados['telefone_reserva'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="(11) 99999-9999"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-12"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "E-mail", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="email">E-mail</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <input <?php /* Cria um campo para entrada de dados. */ ?>
                                    id="email" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    name="email" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    type="email" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    value="<?= escapar($dados['email'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="email@exemplo.com"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                        </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                    </section> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                    <!-- Inicia uma seção visual que agrupa informações relacionadas desta página. -->
                    <section class="form-section"> <?php /* Abre uma seção de conteúdo da página. */ ?>
                        <h3>Endereço</h3> <?php /* Exibe um subtítulo. */ ?>

                        <div class="form-grid"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                            <div class="field span-9"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Rua", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="logradouro">Rua</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "logradouro" utilizado para enviar esta informação no formulário. -->
                                <input id="logradouro" name="logradouro" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['logradouro'] ?? '') ?>" placeholder="Rua"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Número", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="numero">Número</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "numero" utilizado para enviar esta informação no formulário. -->
                                <input id="numero" name="numero" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['numero'] ?? '') ?>" placeholder="Número"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "CEP", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cep">CEP</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "cep" utilizado para enviar esta informação no formulário. -->
                                <input id="cep" name="cep" type="text" inputmode="numeric" maxlength="9" data-mascara="cep" <?php /* [BP-ALTERAÇÃO #6] Máscara 00000-000 + preenche o endereço pelo CEP. */ ?>
                                    <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['cep'] ?? '') ?>" placeholder="00000-000"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-3"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Bairro", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="bairro">Bairro</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "bairro" utilizado para enviar esta informação no formulário. -->
                                <input id="bairro" name="bairro" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['bairro'] ?? '') ?>" placeholder="Bairro"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-4"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Cidade", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="cidade">Cidade</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "cidade" utilizado para enviar esta informação no formulário. -->
                                <input id="cidade" name="cidade" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['cidade'] ?? '') ?>" placeholder="Cidade"> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-2"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Estado", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="estado">Estado</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Lista de seleção "estado" com as opções válidas para este dado. -->
                                <select id="estado" name="estado"> <?php /* Cria uma lista de opções. */ ?>
                                    <option value="">UF</option> <?php /* Define uma opção selecionável. */ ?>
                                    <?php // Executa esta instrução como parte da regra de negócio desta página.
                                    // Armazena a coleção "estados" carregada para ser percorrida e exibida/processada a seguir.
                                    $estados = [ // Armazena em estados o valor usado nas próximas etapas deste fluxo.
                                        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS', // Executa esta instrução como parte da regra de negócio desta página.
                                        'MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC', // Executa esta instrução como parte da regra de negócio desta página.
                                        'SP','SE','TO' // Executa esta instrução como parte da regra de negócio desta página.
                                    ]; // Abre, fecha ou encerra a estrutura iniciada nas linhas relacionadas.
                                    // Percorre cada item da coleção para processar os registros individualmente.
                                    foreach ($estados as $estado): // Percorre a coleção e processa cada registro individualmente.
                                    /* [BP-ALTERAÇÃO #9] O comentário "//" ficava depois do fechamento do PHP e aparecia como texto na tela. */ ?>
                                        <option value="<?= $estado ?>" <?php /* Define uma opção selecionável. */ ?>
                                            <?= (($dados['estado'] ?? '') === $estado) ? 'selected' : '' ?>> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                            <?= $estado ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                        </option> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                                    <?php endforeach; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                </select> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-6"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Complemento", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="complemento">Complemento</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "complemento" utilizado para enviar esta informação no formulário. -->
                                <input id="complemento" name="complemento" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['complemento'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Apartamento, bloco, casa..."> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-6"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Ponto de referência", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="referencia">Ponto de referência</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Campo "referencia" utilizado para enviar esta informação no formulário. -->
                                <input id="referencia" name="referencia" type="text" <?php /* Cria um campo para entrada de dados. */ ?>
                                    value="<?= escapar($dados['referencia'] ?? '') ?>" <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                                    placeholder="Próximo a..."> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                            <div class="field span-12"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                                <!-- Rótulo do campo "Observações", indicando ao usuário qual informação deve ser preenchida. -->
                                <label for="observacoes">Observações</label> <?php /* Exibe o rótulo de um campo. */ ?>
                                <!-- Área de texto "observacoes" para registrar observações livres sobre o registro. -->
                                <textarea id="observacoes" name="observacoes" <?php /* Cria ou abre o elemento HTML textarea. */ ?>
                                    placeholder="Observações sobre o tutor"><?= escapar($dados['observacoes'] ?? '') ?></textarea> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                        </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                    </section> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

                    <div class="form-actions"> <?php /* Organiza esta parte da interface em um contêiner. */ ?>
                        <?php if ($modoEdicao): ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                            <a class="secondary-action-button" href="ficha-tutor.php?id=<?= (int) $editarId ?>">Cancelar</a> <?php /* Cria um link de navegação. */ ?>
                        <?php endif; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
                        <button class="save-button" type="submit"><?= $modoEdicao ? 'Salvar alterações' : 'Salvar' ?></button> <?php /* Cria um botão de ação. */ ?>
                    </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
                </form> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>
            </div> <?php /* Fecha o elemento HTML aberto anteriormente. */ ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?> <?php /* Compõe o conteúdo visual ou textual exibido nesta parte da página. */ ?>
