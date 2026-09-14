<?php
require __DIR__ . '/config.php';

$mensagem = '';
$tipoMensagem = '';
$dados = $_POST;
$erroEmail = false;

// Conta quantos usuários já existem no sistema
$totalUsuarios = (int) $pdo
    ->query('SELECT COUNT(*) FROM usuarios')
    ->fetchColumn();

// Verifica se este é o primeiro usuário do sistema
$primeiroUsuario = $totalUsuarios === 0;

// Obtém o usuário atualmente logado
$usuarioAtualPagina = obterUsuarioAtual($pdo);

// Permite criar usuário se for o primeiro cadastro
// ou se o usuário atual for administrador
$podeCriar = $primeiroUsuario
    || (($usuarioAtualPagina['perfil'] ?? null) === 'ADMINISTRADOR');

// Processa o formulário somente quando enviado via POST
// e quando o usuário possui permissão para criar contas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $podeCriar) {
    $nome = textoOuNull($_POST['nome'] ?? null);
    $email = textoOuNull($_POST['email'] ?? null);

    // Normaliza o e-mail para evitar diferenças de maiúsculas e espaços
    $emailNormalizado = $email === null
        ? null
        : (function_exists('mb_strtolower')
            ? mb_strtolower(trim($email), 'UTF-8')
            : strtolower(trim($email)));

    $senha = (string) ($_POST['senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');

    // O primeiro usuário será sempre administrador
    // Nos demais casos, o perfil vem do formulário
    $perfil = $primeiroUsuario
        ? 'ADMINISTRADOR'
        : (string) ($_POST['perfil'] ?? '');

    // O primeiro usuário fica ativo automaticamente
    // Nos demais, depende do checkbox "ativo"
    $ativo = $primeiroUsuario || isset($_POST['ativo']) ? 1 : 0;

    // Verifica se os campos obrigatórios foram preenchidos
    if ($nome === null || $email === null || $senha === '' || $confirmacao === '') {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipoMensagem = 'error';

    // Verifica se o e-mail possui formato válido
    } elseif (!filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
        $tipoMensagem = 'error';

    // Verifica o tamanho mínimo da senha
    } elseif (strlen($senha) < 8) {
        $mensagem = 'A senha deve possuir pelo menos 8 caracteres.';
        $tipoMensagem = 'error';

    // Confirma se as senhas digitadas são iguais
    } elseif ($senha !== $confirmacao) {
        $mensagem = 'A confirmação da senha não corresponde.';
        $tipoMensagem = 'error';

    // Verifica se o perfil selecionado é permitido
    } elseif (!in_array($perfil, ['ADMINISTRADOR', 'FUNCIONARIO'], true)) {
        $mensagem = 'Selecione um perfil válido.';
        $tipoMensagem = 'error';

    } else {
        try {
            // Verifica se já existe outro usuário com o mesmo e-mail
            $verificarEmail = $pdo->prepare(
                'SELECT id
                   FROM usuarios
                  WHERE LOWER(TRIM(email)) = :email
                  LIMIT 1'
            );
            $verificarEmail->execute([':email' => $emailNormalizado]);

            if ($verificarEmail->fetchColumn() !== false) {
                // Impede o cadastro de e-mails duplicados
                $mensagem = 'Já existe um usuário cadastrado com esse e-mail.';
                $tipoMensagem = 'error';
                $erroEmail = true;
            } else {
                // Insere o novo usuário no banco de dados
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

                $stmt->execute([
                    ':nome'       => $nome,
                    ':email'      => $emailNormalizado,

                    // Armazena somente o hash da senha por segurança
                    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),

                    ':perfil'     => $perfil,
                    ':ativo'      => $ativo,
                ]);

                // Recupera o ID do usuário recém-criado
                $novoId = (int) $pdo->lastInsertId();

                // Se for o primeiro usuário, inicia a sessão com ele
                if ($primeiroUsuario) {
                    $_SESSION['usuario_id'] = $novoId;
                }

                // Redireciona após salvar para evitar reenvio do formulário
                header('Location: novo-usuario.php?salvo=1');
                exit;
            }
        } catch (PDOException $erro) {
            // Trata especificamente o erro de e-mail duplicado
            if ((int) ($erro->errorInfo[1] ?? 0) === 1062) {
                $mensagem = 'Já existe um usuário cadastrado com esse e-mail.';
                $erroEmail = true;
            } else {
                // Trata outros erros relacionados ao banco
                $mensagem = 'Não foi possível criar o usuário.';
            }
            $tipoMensagem = 'error';
        }
    }
}

// Exibe a mensagem após o cadastro realizado com sucesso
if (isset($_GET['salvo'])) {
    $mensagem = 'Usuário criado com sucesso!';
    $tipoMensagem = 'success';
    $dados = [];
}

// Atualiza a quantidade de usuários exibida na página
$totalUsuarios = (int) $pdo
    ->query('SELECT COUNT(*) FROM usuarios')
    ->fetchColumn();

$tituloPagina = 'Criar usuário';
$paginaAtiva = 'novo-usuario';

// Carrega o cabeçalho padrão do sistema
require __DIR__ . '/includes/topo.php';
?>

<div class="page registration-page">
    <div class="page-heading">
        <div>
            <h1>Criar usuário</h1>
            <small>Cadastre administradores e funcionários do sistema.</small>
        </div>

        // Exibe a quantidade total de usuários cadastrados
        <span class="counter">
            <strong><?= $totalUsuarios ?></strong>
            <?= $totalUsuarios === 1 ? 'usuário cadastrado' : 'usuários cadastrados' ?>
        </span>
    </div>

    // Bloqueia o formulário caso o usuário não tenha permissão
    <?php if (!$podeCriar): ?>
        <div class="message error">
            Somente um usuário administrador pode criar novas contas.
        </div>
    <?php else: ?>

        // Exibe mensagens de erro ou sucesso
        <?php if ($mensagem !== ''): ?>
            <div class="message <?= escapar($tipoMensagem) ?>">
                <?= escapar($mensagem) ?>
            </div>
        <?php endif; ?>

        // Formulário de cadastro do usuário
        <form class="form-card user-registration-card" method="post" autocomplete="off">
            <h2>Novo usuário</h2>

            <section class="form-section">
                <?php if ($primeiroUsuario): ?>

                    // Aviso exibido somente durante o primeiro cadastro
                    <div class="first-user-notice">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Primeiro acesso</strong>
                            <span>O primeiro usuário será administrador e ficará ativo automaticamente.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-grid">

                    // Campo para o nome completo
                    <div class="field span-6">
                        <label for="nome">Nome completo</label>
                        <input
                            id="nome"
                            name="nome"
                            type="text"
                            value="<?= escapar($dados['nome'] ?? '') ?>"
                            placeholder="Nome do usuário"
                            autocomplete="name"
                            required>
                    </div>

                    // Campo para o e-mail de acesso
                    <div class="field span-6">
                        <label for="email">E-mail de acesso</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="<?= $erroEmail ? 'input-error' : '' ?>"
                            aria-invalid="<?= $erroEmail ? 'true' : 'false' ?>"
                            value="<?= escapar($dados['email'] ?? '') ?>"
                            placeholder="usuario@exemplo.com"
                            autocomplete="email"
                            required>

                        <?php if ($erroEmail): ?>
                            // Mensagem exibida quando o e-mail já está cadastrado
                            <small class="field-error">Use outro e-mail para este usuário.</small>
                        <?php endif; ?>
                    </div>

                    // Campo para criação da senha
                    <div class="field span-6">
                        <label for="senha">Senha</label>
                        <div class="password-field">
                            <input
                                id="senha"
                                name="senha"
                                type="password"
                                minlength="8"
                                placeholder="Mínimo de 8 caracteres"
                                autocomplete="new-password"
                                required>

                            // Botão para mostrar ou ocultar a senha
                            <button class="toggle-password" type="button"
                                data-password-target="senha" title="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    // Campo para confirmar a senha
                    <div class="field span-6">
                        <label for="confirmarSenha">Confirmar senha</label>
                        <div class="password-field">
                            <input
                                id="confirmarSenha"
                                name="confirmar_senha"
                                type="password"
                                minlength="8"
                                placeholder="Digite a senha novamente"
                                autocomplete="new-password"
                                required>

                            // Botão para mostrar ou ocultar a confirmação da senha
                            <button class="toggle-password" type="button"
                                data-password-target="confirmarSenha" title="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    // Seleção do perfil do usuário
                    <div class="field span-6">
                        <label for="perfil">Tipo de usuário</label>

                        <?php if ($primeiroUsuario): ?>

                            // O primeiro usuário é obrigatoriamente administrador
                            <input type="hidden" name="perfil" value="ADMINISTRADOR">
                            <input type="text" value="Administrador" disabled>

                        <?php else: ?>

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

                    // Controle para definir se a conta ficará ativa
                    <div class="field span-6 user-status-field">
                        <span class="field-label">Situação da conta</span>

                        <label class="account-status-option" for="ativo">
                            <input
                                id="ativo"
                                name="ativo"
                                type="checkbox"
                                value="1"
                                <?= ($primeiroUsuario || isset($dados['ativo']) || !$dados) ? 'checked' : '' ?>
                                <?= $primeiroUsuario ? 'disabled' : '' ?>>

                            <span>
                                <strong>Usuário ativo</strong>
                                <small>Pode aparecer no cartão de troca de usuário.</small>
                            </span>
                        </label>

                        <?php if ($primeiroUsuario): ?>

                            // Como o checkbox está desabilitado, envia o valor através de um campo hidden
                            <input type="hidden" name="ativo" value="1">

                        <?php endif; ?>
                    </div>
                </div>
            </section>

            // Botão responsável pelo envio do cadastro
            <div class="form-actions">
                <button class="save-button" type="submit">
                    <i class="fa-solid fa-user-plus"></i>
                    Criar usuário
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
