<?php
$tituloPagina = $tituloPagina ?? 'Beauty Pet';
$paginaAtiva = $paginaAtiva ?? '';

iniciarSessao();
$usuarioAtual = obterUsuarioAtual($pdo);
$usuariosAtivos = listarUsuariosAtivos($pdo);
$mensagemUsuario = consumirMensagemUsuario();
$abrirPerfil = $mensagemUsuario !== null;
$usuarioTrocaSelecionado = (int) (
    $_SESSION['usuario_troca_id']
    ?? ($usuarioAtual['id'] ?? 0)
);
unset($_SESSION['usuario_troca_id']);

$nomeUsuario = $usuarioAtual['nome'] ?? 'Selecionar usuário';
$perfilUsuario = $usuarioAtual
    ? nomePerfilUsuario($usuarioAtual['perfil'])
    : 'Nenhuma conta ativa';
$iniciaisUsuario = $usuarioAtual
    ? iniciaisNome($usuarioAtual['nome'])
    : '?';

$uriAtual = $_SERVER['REQUEST_URI'] ?? 'index.php';
$retornoAtual = paginaRetornoSegura($uriAtual);
$podeCriarUsuario = !$usuariosAtivos
    || (($usuarioAtual['perfil'] ?? null) === 'ADMINISTRADOR');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($tituloPagina) ?> | Beauty Pet</title>
    <link rel="stylesheet" href="style.css?v=6">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="Logo Beauty Pet">
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

            <details>
                <summary class="menu-item">
                    <div class="menu-left">
                        <i class="fa-solid fa-file-circle-check"></i>
                        <span>Fechamentos</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="#" class="submenu">
                    <i class="fa-solid fa-calendar-day"></i>
                    <span>Diário</span>
                </a>

                <a href="#" class="submenu">
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
            <div class="search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Buscar pets, tutores, agendamentos...">
            </div>

            <div class="header-right">
                <i class="fa-solid fa-bell notification"></i>

                <div class="profile-container <?= $abrirPerfil ? 'open' : '' ?>">
                    <button class="profile" id="profileButton" type="button">
                        <div class="avatar"><?= escapar($iniciaisUsuario) ?></div>
                        <div class="user">
                            <strong><?= escapar($nomeUsuario) ?></strong>
                            <small><?= escapar($perfilUsuario) ?></small>
                        </div>
                        <i class="fa-solid fa-chevron-down profile-arrow"></i>
                    </button>

                    <div class="profile-menu" id="profileMenu">
                        <div class="profile-menu-title">
                            <i class="fa-solid fa-right-left"></i>
                            <div>
                                <strong>Trocar usuário</strong>
                                <small>Selecione a conta e informe a senha.</small>
                            </div>
                        </div>

                        <?php if ($mensagemUsuario): ?>
                            <div
                                class="profile-message <?= escapar($mensagemUsuario['tipo']) ?>"
                                role="alert"
                                aria-live="polite">
                                <?= escapar($mensagemUsuario['texto']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($usuariosAtivos): ?>
                            <form class="switch-user-form" method="post">
                                <input type="hidden" name="acao" value="trocar_usuario">
                                <input type="hidden" name="retorno" value="<?= escapar($retornoAtual) ?>">

                                <label for="usuarioTroca">Usuário</label>
                                <select id="usuarioTroca" name="usuario_id" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($usuariosAtivos as $usuario): ?>
                                        <option value="<?= (int) $usuario['id'] ?>"
                                            <?= ($usuarioTrocaSelecionado === (int) $usuario['id']) ? 'selected' : '' ?>>
                                            <?= escapar($usuario['nome']) ?> — <?= escapar(nomePerfilUsuario($usuario['perfil'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="senhaTroca">Senha</label>
                                <div class="password-inline">
                                    <input id="senhaTroca" name="senha" type="password"
                                        autocomplete="current-password" placeholder="Digite a senha" required>
                                    <button class="toggle-password" type="button"
                                        data-password-target="senhaTroca" title="Mostrar ou ocultar senha">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>

                                <button class="switch-user-button" type="submit">
                                    Trocar usuário
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="profile-empty">
                                Nenhum usuário foi criado ainda.
                            </div>
                        <?php endif; ?>

                        <?php if ($podeCriarUsuario): ?>
                            <a class="create-user-link" href="novo-usuario.php">
                                <i class="fa-solid fa-user-plus"></i>
                                Criar novo usuário
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <main>
