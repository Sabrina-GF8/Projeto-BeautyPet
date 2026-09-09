<?php
require __DIR__ . '/config.php';
$mensagem = '';
$tipoMensagem = '';
$dados = $_POST;
$erroEmail = false;

$totalUsuarios = (int) $pdo
    ->query('SELECT COUNT(*) FROM usuarios')
    ->fetchColumn();

$primeiroUsuario = $totalUsuarios === 0;
$usuarioAtualPagina = obterUsuarioAtual($pdo);
$podeCriar = $primeiroUsuario
    || (($usuarioAtualPagina['perfil'] ?? null) === 'ADMINISTRADOR');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $podeCriar) {
    $nome = textoOuNull($_POST['nome'] ?? null);
    $email = textoOuNull($_POST['email'] ?? null);
    $emailNormalizado = $email === null
        ? null
        : (function_exists('mb_strtolower')
            ? mb_strtolower(trim($email), 'UTF-8')
            : strtolower(trim($email)));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');
    $perfil = $primeiroUsuario
        ? 'ADMINISTRADOR'
        : (string) ($_POST['perfil'] ?? '');
    $ativo = $primeiroUsuario || isset($_POST['ativo']) ? 1 : 0;

    if ($nome === null || $email === null || $senha === '' || $confirmacao === '') {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipoMensagem = 'error';
    } elseif (!filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
        $tipoMensagem = 'error';
    } elseif (strlen($senha) < 8) {
        $mensagem = 'A senha deve possuir pelo menos 8 caracteres.';
        $tipoMensagem = 'error';
    } elseif ($senha !== $confirmacao) {
        $mensagem = 'A confirmação da senha não corresponde.';
        $tipoMensagem = 'error';
    } elseif (!in_array($perfil, ['ADMINISTRADOR', 'FUNCIONARIO'], true)) {
        $mensagem = 'Selecione um perfil válido.';
        $tipoMensagem = 'error';
    } else {
        try {
            $verificarEmail = $pdo->prepare(
                'SELECT id
                   FROM usuarios
                  WHERE LOWER(TRIM(email)) = :email
                  LIMIT 1'
            );
            $verificarEmail->execute([':email' => $emailNormalizado]);

            if ($verificarEmail->fetchColumn() !== false) {
                $mensagem = 'Já existe um usuário cadastrado com esse e-mail.';
                $tipoMensagem = 'error';
                $erroEmail = true;
            } else {
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
                    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    ':perfil'     => $perfil,
                    ':ativo'      => $ativo,
                ]);

                $novoId = (int) $pdo->lastInsertId();

                if ($primeiroUsuario) {
                    $_SESSION['usuario_id'] = $novoId;
                }

                header('Location: novo-usuario.php?salvo=1');
                exit;
            }        } catch (PDOException $erro) {
            if ((int) ($erro->errorInfo[1] ?? 0) === 1062) {
                $mensagem = 'Já existe um usuário cadastrado com esse e-mail.';
                $erroEmail = true;
            } else {
                $mensagem = 'Não foi possível criar o usuário.';
            }
            $tipoMensagem = 'error';
        }
    }
}

if (isset($_GET['salvo'])) {
    $mensagem = 'Usuário criado com sucesso!';
    $tipoMensagem = 'success';
    $dados = [];
}

$totalUsuarios = (int) $pdo
    ->query('SELECT COUNT(*) FROM usuarios')
    ->fetchColumn();

$tituloPagina = 'Criar usuário';
$paginaAtiva = 'novo-usuario';

require __DIR__ . '/includes/topo.php';
?>

<div class="page registration-page">
    <div class="page-heading">
        <div>
            <h1>Criar usuário</h1>
            <small>Cadastre administradores e funcionários do sistema.</small>
        </div>

        <span class="counter">
            <strong><?= $totalUsuarios ?></strong>
            <?= $totalUsuarios === 1 ? 'usuário cadastrado' : 'usuários cadastrados' ?>
        </span>
    </div>

    <?php if (!$podeCriar): ?>
        <div class="message error">
            Somente um usuário administrador pode criar novas contas.
        </div>
    <?php else: ?>
        <?php if ($mensagem !== ''): ?>
            <div class="message <?= escapar($tipoMensagem) ?>">
                <?= escapar($mensagem) ?>
            </div>
        <?php endif; ?>

        <form class="form-card user-registration-card" method="post" autocomplete="off">
            <h2>Novo usuário</h2>

            <section class="form-section">
                <?php if ($primeiroUsuario): ?>
                    <div class="first-user-notice">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Primeiro acesso</strong>
                            <span>O primeiro usuário será administrador e ficará ativo automaticamente.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-grid">
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
                            <small class="field-error">Use outro e-mail para este usuário.</small>
                        <?php endif; ?>
                    </div>

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
                            <button class="toggle-password" type="button"
                                data-password-target="senha" title="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

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
                            <button class="toggle-password" type="button"
                                data-password-target="confirmarSenha" title="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="field span-6">
                        <label for="perfil">Tipo de usuário</label>

                        <?php if ($primeiroUsuario): ?>
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
                            <input type="hidden" name="ativo" value="1">
                        <?php endif; ?>
                    </div>
                </div>
            </section>

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
