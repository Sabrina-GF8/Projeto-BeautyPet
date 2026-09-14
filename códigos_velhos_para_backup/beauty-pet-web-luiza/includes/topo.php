<?php
/**
 * ARQUIVO: includes/topo.php
 * RESPONSABILIDADE: Cabeçalho e menu lateral compartilhados.
 * Monta a estrutura visual comum das páginas, identifica o item ativo, mostra o usuário logado e disponibiliza busca e ações de conta.
 *
 * Os comentários abaixo descrevem o motivo de cada etapa, os dados manipulados
 * e a relação entre interface, regras de negócio e banco de dados.
 */
$tituloPagina = $tituloPagina ?? 'Beauty Pet';
// Informa ao menu lateral qual item deve aparecer destacado nesta página.
$paginaAtiva = $paginaAtiva ?? '';

iniciarSessao();
// Recupera os dados do usuário autenticado para saber quem está operando o sistema.
$usuarioAtual = obterUsuarioAtual($pdo);
// Armazena a coleção "usuariosAtivos" carregada para ser percorrida e exibida/processada a seguir.
$usuariosAtivos = listarUsuariosAtivos($pdo);
// Armazena em "mensagemUsuario" o valor necessário para as próximas etapas deste fluxo.
$mensagemUsuario = consumirMensagemUsuario();
// Armazena em "abrirPerfil" o valor necessário para as próximas etapas deste fluxo.
$abrirPerfil = ($mensagemUsuario['tipo'] ?? '') === 'error';
// Armazena em "emailTrocaPreenchido" o valor necessário para as próximas etapas deste fluxo.
$emailTrocaPreenchido = (string) ($_SESSION['usuario_troca_email'] ?? '');
unset($_SESSION['usuario_troca_email']);

// Armazena em "nomeUsuario" o valor necessário para as próximas etapas deste fluxo.
$nomeUsuario = $usuarioAtual['nome'] ?? 'Selecionar usuário';
// Armazena em "perfilUsuario" o valor necessário para as próximas etapas deste fluxo.
$perfilUsuario = $usuarioAtual
    ? nomePerfilUsuario($usuarioAtual['perfil'])
    : 'Nenhuma conta ativa';
// Armazena em "iniciaisUsuario" o valor necessário para as próximas etapas deste fluxo.
$iniciaisUsuario = $usuarioAtual
    ? iniciaisNome($usuarioAtual['nome'])
    : '?';

// Armazena em "uriAtual" o valor necessário para as próximas etapas deste fluxo.
$uriAtual = $_SERVER['REQUEST_URI'] ?? 'index.php';
// Armazena em "retornoAtual" o valor necessário para as próximas etapas deste fluxo.
$retornoAtual = paginaRetornoSegura($uriAtual);
// Guarda uma condição booleana em "podeCriarUsuario" para controlar o fluxo ou a exibição da página.
$podeCriarUsuario = !$usuariosAtivos
    || (($usuarioAtual['perfil'] ?? null) === 'ADMINISTRADOR');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($tituloPagina) ?> | Beauty Pet</title>
    <link rel="stylesheet" href="../style.css?v=fechamento-mensal-v1-20260907">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="Logo Beauty Pet">
            <div class="logo-text">
                <h2>Beauty Pet</h2>
                <small>Sistema de Gestão</small>
            </div>
        </div>

        <nav>
            <a href="index.php"
                class="menu-item <?= $paginaAtiva === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-table-columns"></i>
                <span>Dashboard</span>
            </a>

            <a href="agenda.php"
                class="menu-item <?= in_array($paginaAtiva, ['agenda', 'solicitacoes-agendamento'], true) ? 'active' : '' ?>">
                <i class="fa-regular fa-calendar"></i>
                <span>Agenda</span>
            </a>

            <details <?= in_array($paginaAtiva, ['fechamento-diario', 'fechamento-mensal'], true) ? 'open' : '' ?>>
                <summary class="menu-item <?= in_array($paginaAtiva, ['fechamento-diario', 'fechamento-mensal'], true) ? 'active' : '' ?>">
                    <div class="menu-left">
                        <i class="fa-solid fa-file-circle-check"></i>
                        <span>Fechamentos</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="fechamento-diario.php" class="submenu <?= $paginaAtiva === 'fechamento-diario' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-day"></i>
                    <span>Diário</span>
                </a>

                <a href="fechamento-mensal.php" class="submenu <?= $paginaAtiva === 'fechamento-mensal' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-week"></i>
                    <span>Mensal</span>
                </a>
            </details>

            <details <?= in_array($paginaAtiva, ['cadastro-tutor', 'fichas-tutores'], true) ? 'open' : '' ?>>
                <summary class="menu-item <?= in_array($paginaAtiva, ['cadastro-tutor', 'fichas-tutores'], true) ? 'active' : '' ?>">
                    <div class="menu-left">
                        <i class="fa-solid fa-users"></i>
                        <span>Tutores</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="cadastro-tutor.php"
                    class="submenu <?= $paginaAtiva === 'cadastro-tutor' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Cadastro</span>
                </a>

                <a href="fichas-tutores.php"
                    class="submenu <?= $paginaAtiva === 'fichas-tutores' ? 'active' : '' ?>">
                    <i class="fa-regular fa-address-card"></i>
                    <span>Fichas</span>
                </a>
            </details>

            <details <?= in_array($paginaAtiva, ['cadastro-pet', 'fichas-pets'], true) ? 'open' : '' ?>>
                <summary class="menu-item <?= in_array($paginaAtiva, ['cadastro-pet', 'fichas-pets'], true) ? 'active' : '' ?>">
                    <div class="menu-left">
                        <i class="fa-solid fa-paw"></i>
                        <span>Pets</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="cadastro-pet.php"
                    class="submenu <?= $paginaAtiva === 'cadastro-pet' ? 'active' : '' ?>">
                    <i class="fa-solid fa-dog"></i>
                    <span>Cadastro</span>
                </a>

                <a href="fichas-pets.php"
                    class="submenu <?= $paginaAtiva === 'fichas-pets' ? 'active' : '' ?>">
                    <i class="fa-solid fa-notes-medical"></i>
                    <span>Fichas</span>
                </a>
            </details>

            <a href="#" class="menu-item">
                <i class="fa-solid fa-dollar-sign"></i>
                <span>Financeiro</span>
            </a>

            <a href="#" class="menu-item">
                <i class="fa-solid fa-gear"></i>
                <span>Configurações</span>
            </a>
        </nav>
    </aside>

    <div class="content">
        <header>
            <div class="search global-search" data-global-search>
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    id="globalSearchInput"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar pet ou tutor..."
                    aria-label="Buscar pet ou tutor">
                <div class="global-search-results" id="globalSearchResults" hidden></div>
            </div>

            <div class="header-right">
                <i class="fa-solid fa-bell notification"></i>

                <div class="profile-container">
                    <button class="profile" id="profileButton" type="button" aria-haspopup="dialog">
                        <div class="avatar"><?= escapar($iniciaisUsuario) ?></div>
                        <div class="user">
                            <strong><?= escapar($nomeUsuario) ?></strong>
                            <small><?= escapar($perfilUsuario) ?></small>
                        </div>
                        <i class="fa-solid fa-right-left profile-arrow"></i>
                    </button>
                </div>
            </div>
        </header>

        <div
            class="account-modal <?= $abrirPerfil ? 'is-open' : '' ?>"
            id="switchUserModal"
            aria-hidden="<?= $abrirPerfil ? 'false' : 'true' ?>">
            <div class="account-dialog switch-user-dialog" role="dialog" aria-modal="true" aria-labelledby="switchUserTitle">
                <button class="account-modal-close" id="switchUserClose" type="button" aria-label="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <div class="account-dialog-icon">
                    <i class="fa-solid fa-user"></i>
                </div>

                <h2 id="switchUserTitle">Trocar usuário</h2>
                <p class="account-dialog-subtitle">Entre com o e-mail e a senha da conta que deseja usar.</p>

                <?php if ($mensagemUsuario): ?>
                    <div class="account-message <?= escapar($mensagemUsuario['tipo']) ?>" role="alert" aria-live="polite">
                        <?= escapar($mensagemUsuario['texto']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($usuariosAtivos): ?>
                    <!-- Formulário responsável por enviar os dados desta ação para processamento no PHP. -->
                    <form class="account-login-form" method="post" autocomplete="off">
                        <!-- Campo "acao" utilizado para enviar esta informação no formulário. -->
                        <input type="hidden" name="acao" value="trocar_usuario">
                        <!-- Campo "retorno" utilizado para enviar esta informação no formulário. -->
                        <input type="hidden" name="retorno" value="<?= escapar($retornoAtual) ?>">

                        <div class="account-field">
                            <!-- Rótulo do campo "E-mail", indicando ao usuário qual informação deve ser preenchida. -->
                            <label for="emailTroca">E-mail</label>
                            <div class="account-input-icon">
                                <i class="fa-regular fa-envelope"></i>
                                <input
                                    id="emailTroca"
                                    name="email_usuario"
                                    type="email"
                                    value="<?= escapar($emailTrocaPreenchido) ?>"
                                    placeholder="seuemail@exemplo.com"
                                    autocomplete="username"
                                    required>
                            </div>
                        </div>

                        <div class="account-field">
                            <!-- Rótulo do campo "Senha", indicando ao usuário qual informação deve ser preenchida. -->
                            <label for="senhaTroca">Senha</label>
                            <div class="password-field account-password-field">
                                <input
                                    id="senhaTroca"
                                    name="senha"
                                    type="password"
                                    placeholder="Digite sua senha"
                                    autocomplete="current-password"
                                    required>
                                <button class="toggle-password" type="button"
                                    data-password-target="senhaTroca" title="Mostrar ou ocultar senha">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button class="account-primary-button" type="submit">
                            Entrar
                        </button>
                    </form>
                <?php else: ?>
                    <div class="account-message info">Nenhum usuário foi criado ainda.</div>
                <?php endif; ?>

                <?php if ($podeCriarUsuario): ?>
                    <a class="account-secondary-link" href="novo-usuario.php">
                        <i class="fa-solid fa-user-plus"></i>
                        Criar novo usuário
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Área principal onde o conteúdo específico da página é exibido. -->
        <main>
