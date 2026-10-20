<?php
/**
 * ARQUIVO: novo-usuario.php
 * RESPONSABILIDADE: Criação de usuários do sistema web.
 * Valida permissões, nome, e-mail, senha e perfil; cria o primeiro administrador e permite que administradores cadastrem outros usuários.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
require __DIR__ . '/../config/config.php';
// Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
$mensagem = '';
// Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
$tipoMensagem = '';
// Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
$dados = $_POST;
// Guarda a mensagem de erro específica relacionada à validação ou duplicidade de e-mail.
$erroEmail = false;

// Conta os usuários cadastrados para decidir regras como a criação do primeiro administrador.
$totalUsuarios = (int) $pdo
    ->query('SELECT COUNT(*) FROM usuarios')
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn();

// Armazena em "primeiroUsuario" o valor necessário para as próximas etapas deste fluxo.
$primeiroUsuario = $totalUsuarios === 0;
// Armazena em "usuarioAtualPagina" o valor necessário para as próximas etapas deste fluxo.
$usuarioAtualPagina = obterUsuarioAtual($pdo);
// Guarda uma condição booleana em "podeCriar" para controlar o fluxo ou a exibição da página.
$podeCriar = $primeiroUsuario
    || (($usuarioAtualPagina['perfil'] ?? null) === 'ADMINISTRADOR');

// Executa o processamento abaixo somente quando algum formulário desta página foi enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $podeCriar) {
    // Guarda o nome informado no formulário após a normalização necessária.
    $nome = textoOuNull($_POST['nome'] ?? null);
    // Guarda o e-mail informado para validação, autenticação ou cadastro.
    $email = textoOuNull($_POST['email'] ?? null);
    // Padroniza o e-mail antes de comparar ou salvar, evitando diferenças por espaços ou letras maiúsculas.
    $emailNormalizado = $email === null
        ? null
        : (function_exists('mb_strtolower')
            ? mb_strtolower(trim($email), 'UTF-8')
            : strtolower(trim($email)));
    // Armazena em "senha" o valor necessário para as próximas etapas deste fluxo.
    $senha = (string) ($_POST['senha'] ?? '');
    // Armazena em "confirmacao" o valor necessário para as próximas etapas deste fluxo.
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');
    // Armazena em "perfil" o valor necessário para as próximas etapas deste fluxo.
    $perfil = $primeiroUsuario
        ? 'ADMINISTRADOR'
        : (string) ($_POST['perfil'] ?? '');
    // Armazena em "ativo" o valor necessário para as próximas etapas deste fluxo.
    $ativo = $primeiroUsuario || isset($_POST['ativo']) ? 1 : 0;

    // Verifica a condição abaixo antes de executar este bloco.
    if ($nome === null || $email === null || $senha === '' || $confirmacao === '') {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Preencha todos os campos obrigatórios.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (!filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Informe um e-mail válido.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (strlen($senha) < 8) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'A senha deve possuir pelo menos 8 caracteres.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif ($senha !== $confirmacao) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'A confirmação da senha não corresponde.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Caso a condição anterior não tenha sido atendida, testa esta alternativa.
    } elseif (!in_array($perfil, ['ADMINISTRADOR', 'FUNCIONARIO'], true)) {
        // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
        $mensagem = 'Selecione um perfil válido.';
        // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
        $tipoMensagem = 'error';
    // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
    } else {
        // Inicia o bloco protegido para que falhas de banco/regra sejam tratadas sem interromper a página.
        try {
            // Armazena em "verificarEmail" o valor necessário para as próximas etapas deste fluxo.
            $verificarEmail = $pdo->prepare(
                'SELECT id
                   FROM usuarios
                  WHERE LOWER(TRIM(email)) = :email
                  LIMIT 1'
            );
            // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
            $verificarEmail->execute([':email' => $emailNormalizado]);

            // Verifica a condição abaixo antes de executar este bloco.
            if ($verificarEmail->fetchColumn() !== false) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Já existe um usuário cadastrado com esse e-mail.';
                // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
                $tipoMensagem = 'error';
                // Guarda a mensagem de erro específica relacionada à validação ou duplicidade de e-mail.
                $erroEmail = true;
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Prepara a inclusão de um novo registro em "usuarios" usando os valores já validados.
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (
                        nome,
                        email,
                        senha_hash,
                        perfil,
                        ativo
                    ) VALUES (
                        :nome,
                        :email,
                        :senha_hash,
                        :perfil,
                        :ativo
                    )'
                );

                // Executa a instrução preparada enviando os parâmetros de forma separada e segura.
                $stmt->execute([
                    ':nome'       => $nome,
                    ':email'      => $emailNormalizado,
                    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    ':perfil'     => $perfil,
                    ':ativo'      => $ativo,
                ]);

                // Armazena o identificador "novoId" utilizado para relacionar ou localizar o registro no banco.
                $novoId = (int) $pdo->lastInsertId();

                // Verifica a condição abaixo antes de executar este bloco.
                if ($primeiroUsuario) {
                    $_SESSION['usuario_id'] = $novoId;
                }

                // Redireciona o navegador após a operação para evitar reenvio do formulário e atualizar a tela.
                header('Location: novo-usuario.php?salvo=1');
                // Encerra a execução desta requisição após o redirecionamento ou resposta enviada.
                exit;
            }        } catch (PDOException $erro) {
            // Verifica a condição abaixo antes de executar este bloco.
            if ((int) ($erro->errorInfo[1] ?? 0) === 1062) {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Já existe um usuário cadastrado com esse e-mail.';
                // Guarda a mensagem de erro específica relacionada à validação ou duplicidade de e-mail.
                $erroEmail = true;
            // Quando nenhuma das condições anteriores for atendida, executa este fluxo alternativo.
            } else {
                // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
                $mensagem = 'Não foi possível criar o usuário.';
            }
            // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
            $tipoMensagem = 'error';
        }
    }
}

// Verifica a condição abaixo antes de executar este bloco.
if (isset($_GET['salvo'])) {
    // Inicializa a mensagem de retorno que será mostrada ao usuário após validações ou operações.
    $mensagem = 'Usuário criado com sucesso!';
    // Define a classe visual da mensagem de retorno, normalmente sucesso ou erro.
    $tipoMensagem = 'success';
    // Guarda os dados usados para preencher novamente o formulário, inclusive após erros de validação.
    $dados = [];
}

// Conta os usuários cadastrados para decidir regras como a criação do primeiro administrador.
$totalUsuarios = (int) $pdo
    ->query('SELECT COUNT(*) FROM usuarios')
    // Obtém somente a primeira coluna do resultado, útil para contagens, IDs e totais simples.
    ->fetchColumn();

// Define o título exibido na aba/navegador pelo template compartilhado.
$tituloPagina = 'Criar usuário';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = 'novo-usuario';

// Define o(s) CSS desta página em assets/css/ (novo-usuario.css); o includes/topo.php monta os <link> correspondentes.
$cssPagina = 'novo-usuario';
// Carrega o arquivo compartilhado necessário para usar conexão, funções, layout ou outras dependências desta página.
require __DIR__ . '/../includes/topo.php';
?>

<!-- Bloco visual "account-page-modal is-open" que organiza esta parte da interface. -->
<div class="account-page-modal is-open">
    <div class="account-dialog create-user-dialog" role="dialog" aria-modal="true" aria-labelledby="createUserTitle">
        <?php if (!$primeiroUsuario): ?>
            <a class="account-modal-close" href="index.php" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </a>
        <?php endif; ?>

        <div class="account-dialog-icon create">
            <i class="fa-solid fa-user-plus"></i>
        </div>

        <h2 id="createUserTitle">Criar usuário</h2>
        <p class="account-dialog-subtitle">
            Cadastre uma nova conta para acessar o Beauty Pet.
        </p>

        <?php if (!$podeCriar): ?>
            <div class="account-message error">
                Somente um usuário administrador pode criar novas contas.
            </div>

            <a class="account-primary-button account-button-link" href="index.php">
                Voltar ao sistema
            </a>
        <?php else: ?>
            <?php if ($mensagem !== ''): ?>
                <div class="account-message <?= escapar($tipoMensagem) ?>">
                    <?= escapar($mensagem) ?>
                </div>
            <?php endif; ?>

            <?php if ($primeiroUsuario): ?>
                <div class="account-first-access">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Primeiro acesso</strong>
                        <small>Esta conta será administradora e ficará ativa automaticamente.</small>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
            <form class="account-create-form" method="post" autocomplete="off">
                <div class="account-field">
                    <!-- Rótulo do campo "Nome completo", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="nome">Nome completo</label>
                    <div class="account-input-icon">
                        <i class="fa-regular fa-user"></i>
                        <input
                            id="nome"
                            name="nome"
                            type="text"
                            value="<?= escapar($dados['nome'] ?? '') ?>"
                            placeholder="Nome do usuário"
                            autocomplete="name"
                            required>
                    </div>
                </div>

                <div class="account-field">
                    <!-- Rótulo do campo "E-mail", indicando ao usuário qual informação deve ser preenchida. -->
                    <label for="email">E-mail</label>
                    <div class="account-input-icon <?= $erroEmail ? 'has-error' : '' ?>">
                        <i class="fa-regular fa-envelope"></i>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="<?= escapar($dados['email'] ?? '') ?>"
                            placeholder="usuario@exemplo.com"
                            autocomplete="email"
                            required>
                    </div>
                    <?php if ($erroEmail): ?>
                        <small class="account-field-error">Use outro e-mail para este usuário.</small>
                    <?php endif; ?>
                </div>

                <div class="account-form-row">
                    <div class="account-field">
                        <!-- Rótulo do campo "Senha", indicando ao usuário qual informação deve ser preenchida. -->
                        <label for="senha">Senha</label>
                        <div class="password-field account-password-field">
                            <input
                                id="senha"
                                name="senha"
                                type="password"
                                minlength="8"
                                placeholder="Mínimo de 8 caracteres"
                                autocomplete="new-password"
                                required>
                            <button class="toggle-password" type="button"
                                data-password-target="senha" title="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="account-field">
                        <!-- Rótulo do campo "Confirmar senha", indicando ao usuário qual informação deve ser preenchida. -->
                        <label for="confirmarSenha">Confirmar senha</label>
                        <div class="password-field account-password-field">
                            <input
                                id="confirmarSenha"
                                name="confirmar_senha"
                                type="password"
                                minlength="8"
                                placeholder="Repita a senha"
                                autocomplete="new-password"
                                required>
                            <button class="toggle-password" type="button"
                                data-password-target="confirmarSenha" title="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="account-form-row">
                    <div class="account-field">
                        <!-- Rótulo do campo "Tipo de usuário", indicando ao usuário qual informação deve ser preenchida. -->
                        <label for="perfil">Tipo de usuário</label>
                        <?php if ($primeiroUsuario): ?>
                            <!-- Campo "perfil" utilizado para enviar esta informação no formulário. -->
                            <input type="hidden" name="perfil" value="ADMINISTRADOR">
                            <div class="account-readonly-field">
                                <i class="fa-solid fa-shield-halved"></i>
                                Administrador
                            </div>
                        <?php else: ?>
                            <!-- Lista de seleção "perfil" com as opções válidas para este dado. -->
                            <select id="perfil" name="perfil" required>
                                <option value="">Selecione</option>
                                <option value="ADMINISTRADOR"
                                    <?= (($dados['perfil'] ?? '') === 'ADMINISTRADOR') ? 'selected' : '' ?>>
                                    Administrador
                                </option>
                                <option value="FUNCIONARIO"
                                    <?= (($dados['perfil'] ?? '') === 'FUNCIONARIO') ? 'selected' : '' ?>>
                                    Funcionário
                                </option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="account-field">
                        <span class="account-label">Situação da conta</span>
                        <!-- Rótulo do campo "ativo", indicando ao usuário qual informação deve ser preenchida. -->
                        <label class="account-active-option" for="ativo">
                            <input
                                id="ativo"
                                name="ativo"
                                type="checkbox"
                                value="1"
                                <?= ($primeiroUsuario || isset($dados['ativo']) || !$dados) ? 'checked' : '' ?>
                                <?= $primeiroUsuario ? 'disabled' : '' ?>>
                            <span>
                                <strong>Usuário ativo</strong>
                                <small>Permitir acesso ao sistema</small>
                            </span>
                        </label>
                        <?php if ($primeiroUsuario): ?>
                            <!-- Campo "ativo" utilizado para enviar esta informação no formulário. -->
                            <input type="hidden" name="ativo" value="1">
                        <?php endif; ?>
                    </div>
                </div>

                <button class="account-primary-button" type="submit">
                    <i class="fa-solid fa-user-plus"></i>
                    Criar usuário
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
