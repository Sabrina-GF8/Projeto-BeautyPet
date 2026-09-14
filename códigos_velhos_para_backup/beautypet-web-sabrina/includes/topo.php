<?php

// Define o título da página.
// Caso nenhuma página tenha definido um título, utiliza "Beauty Pet".
$tituloPagina = $tituloPagina ?? 'Beauty Pet';

// Define qual item do menu deve aparecer como ativo.
$paginaAtiva = $paginaAtiva ?? '';


// Inicia a sessão do usuário.
// A sessão é utilizada para armazenar informações do usuário conectado.
iniciarSessao();


// Busca os dados do usuário que está atualmente conectado.
$usuarioAtual = obterUsuarioAtual($pdo);

// Busca todos os usuários que estão ativos no sistema.
$usuariosAtivos = listarUsuariosAtivos($pdo);

// Recupera uma mensagem que possa ter sido gerada durante a troca de usuário.
$mensagemUsuario = consumirMensagemUsuario();

// Verifica se existe alguma mensagem para determinar se o menu de perfil
// deverá ser aberto automaticamente.
$abrirPerfil = $mensagemUsuario !== null;


// Define qual usuário ficará selecionado no campo de troca de usuário.
// Caso não exista uma seleção anterior, utiliza o usuário atualmente conectado.
$usuarioTrocaSelecionado = (int) (
    $_SESSION['usuario_troca_id']
    ?? ($usuarioAtual['id'] ?? 0)
);

// Remove da sessão o usuário que estava temporariamente selecionado.
unset($_SESSION['usuario_troca_id']);


// Define o nome que será exibido no perfil.
// Caso não exista usuário conectado, mostra "Selecionar usuário".
$nomeUsuario = $usuarioAtual['nome'] ?? 'Selecionar usuário';

// Define o perfil que será mostrado abaixo do nome do usuário.
$perfilUsuario = $usuarioAtual
    ? nomePerfilUsuario($usuarioAtual['perfil'])
    : 'Nenhuma conta ativa';

// Obtém as iniciais do nome do usuário para exibir no avatar.
$iniciaisUsuario = $usuarioAtual
    ? iniciaisNome($usuarioAtual['nome'])
    : '?';


// Obtém o endereço da página atual.
$uriAtual = $_SERVER['REQUEST_URI'] ?? 'index.php';

// Define uma página de retorno segura para ser utilizada após
// a troca de usuário.
$retornoAtual = paginaRetornoSegura($uriAtual);


// Verifica se o usuário atual pode criar novos usuários.
// Administradores podem criar usuários.
// Também permite a criação quando ainda não existe nenhum usuário ativo.
$podeCriarUsuario = !$usuariosAtivos
    || (($usuarioAtual['perfil'] ?? null) === 'ADMINISTRADOR');
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <!-- Define a codificação utilizada pela página. -->
    <meta charset="UTF-8">

    <!-- Faz a página se adaptar a diferentes tamanhos de tela. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define o título exibido na aba do navegador. -->
    <title><?= escapar($tituloPagina) ?> | Beauty Pet</title>

    <!-- Arquivo principal de estilos do sistema. -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=6">

    <!-- Biblioteca Font Awesome utilizada para os ícones do sistema. -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

</head>

<body>

<!-- Container principal que organiza a barra lateral e o conteúdo. -->
<div class="container">

    <!-- Barra lateral do sistema. -->
    <aside class="sidebar">

        <!-- Área que apresenta o logo do Beauty Pet. -->
        <div class="logo">

            <!-- Imagem do logo. -->
            <img src="logo.png" alt="Logo Beauty Pet">

            <!-- Nome e descrição do sistema. -->
            <div class="logo-text">
                <h2>Beauty Pet</h2>
                <small>Sistema de Gestão</small>
            </div>

        </div>


        <!-- Menu principal de navegação. -->
        <nav>

            <!-- Link para a página inicial/dashboard. -->
            <a href="<?= BASE_URL ?>/index.php"
                class="menu-item <?= $paginaAtiva === 'dashboard' ? 'active' : '' ?>">

                <!-- Ícone do Dashboard. -->
                <i class="fa-solid fa-table-columns"></i>

                <!-- Nome da opção. -->
                <span>Dashboard</span>

            </a>


            <!-- Link para a agenda. -->
            <!-- A opção fica ativa quando estamos na agenda ou nas solicitações. -->
            <a href="<?= BASE_URL ?>/pages/agenda.php"
                class="menu-item <?= in_array($paginaAtiva, ['agenda', 'solicitacoes-agendamento'], true) ? 'active' : '' ?>">

                <i class="fa-regular fa-calendar"></i>

                <span>Agenda</span>

            </a>


            <!-- Menu expansível de fechamentos. -->
            <details>

                <!-- Título do menu Fechamentos. -->
                <summary class="menu-item">

                    <div class="menu-left">

                        <!-- Ícone de fechamento. -->
                        <i class="fa-solid fa-file-circle-check"></i>

                        <span>Fechamentos</span>

                    </div>

                    <!-- Seta que indica que o menu pode ser expandido. -->
                    <i class="fa-solid fa-chevron-down arrow"></i>

                </summary>


                <!-- Opção de fechamento diário. -->
                <a href="#" class="submenu">
                    <i class="fa-solid fa-calendar-day"></i>
                    <span>Diário</span>
                </a>


                <!-- Opção de fechamento mensal. -->
                <a href="#" class="submenu">
                    <i class="fa-solid fa-calendar-week"></i>
                    <span>Mensal</span>
                </a>

            </details>


            <!-- Menu de Tutores.
                 Permanece aberto quando estamos no cadastro ou nas fichas. -->
            <details <?= in_array($paginaAtiva, ['cadastro-tutor', 'fichas-tutores'], true) ? 'open' : '' ?>>

                <!-- Título do menu Tutores. -->
                <summary class="menu-item <?= in_array($paginaAtiva, ['cadastro-tutor', 'fichas-tutores'], true) ? 'active' : '' ?>">

                    <div class="menu-left">

                        <!-- Ícone de usuários. -->
                        <i class="fa-solid fa-users"></i>

                        <span>Tutores</span>

                    </div>

                    <!-- Seta do menu expansível. -->
                    <i class="fa-solid fa-chevron-down arrow"></i>

                </summary>


                <!-- Acesso ao cadastro de tutores. -->
                <a href="<?= BASE_URL ?>/pages/cadastro-tutor.php"
                    class="submenu <?= $paginaAtiva === 'cadastro-tutor' ? 'active' : '' ?>">

                    <i class="fa-solid fa-user-plus"></i>

                    <span>Cadastro</span>

                </a>


                <!-- Acesso às fichas dos tutores. -->
                <a href="<?= BASE_URL ?>/pages/fichas-tutores.php"
                    class="submenu <?= $paginaAtiva === 'fichas-tutores' ? 'active' : '' ?>">

                    <i class="fa-regular fa-address-card"></i>

                    <span>Fichas</span>

                </a>

            </details>


            <!-- Menu de Pets.
                 Permanece aberto quando estamos no cadastro ou nas fichas. -->
            <details <?= in_array($paginaAtiva, ['cadastro-pet', 'fichas-pets'], true) ? 'open' : '' ?>>

                <!-- Título do menu Pets. -->
                <summary class="menu-item <?= in_array($paginaAtiva, ['cadastro-pet', 'fichas-pets'], true) ? 'active' : '' ?>">

                    <div class="menu-left">

                        <!-- Ícone de pata. -->
                        <i class="fa-solid fa-paw"></i>

                        <span>Pets</span>

                    </div>

                    <!-- Seta do menu expansível. -->
                    <i class="fa-solid fa-chevron-down arrow"></i>

                </summary>


                <!-- Acesso ao cadastro de pets. -->
                <a href="<?= BASE_URL ?>/pages/cadastro-pet.php"
                    class="submenu <?= $paginaAtiva === 'cadastro-pet' ? 'active' : '' ?>">

                    <i class="fa-solid fa-dog"></i>

                    <span>Cadastro</span>

                </a>


                <!-- Acesso às fichas dos pets. -->
                <a href="<?= BASE_URL ?>/pages/fichas-pets.php"
                    class="submenu <?= $paginaAtiva === 'fichas-pets' ? 'active' : '' ?>">

                    <i class="fa-solid fa-notes-medical"></i>

                    <span>Fichas</span>

                </a>

            </details>


            <!-- Acesso ao módulo financeiro. -->
            <a href="#" class="menu-item">

                <i class="fa-solid fa-dollar-sign"></i>

                <span>Financeiro</span>

            </a>


            <!-- Acesso às configurações do sistema. -->
            <a href="#" class="menu-item">

                <i class="fa-solid fa-gear"></i>

                <span>Configurações</span>

            </a>

        </nav>

    </aside>


    <!-- Área que contém o conteúdo principal do sistema. -->
    <div class="content">

        <!-- Cabeçalho da página. -->
        <header>

            <!-- Campo de pesquisa geral do sistema. -->
            <div class="search">

                <!-- Ícone de pesquisa. -->
                <i class="fa-solid fa-magnifying-glass"></i>

                <!-- Campo para pesquisar pets, tutores e agendamentos. -->
                <input
                    type="text"
                    placeholder="Buscar pets, tutores, agendamentos...">

            </div>


            <!-- Área direita do cabeçalho. -->
            <div class="header-right">

                <!-- Ícone de notificações. -->
                <i class="fa-solid fa-bell notification"></i>


                <!-- Área do perfil do usuário. -->
                <div class="profile-container <?= $abrirPerfil ? 'open' : '' ?>">

                    <!-- Botão que abre o menu do perfil. -->
                    <button class="profile" id="profileButton" type="button">

                        <!-- Avatar com as iniciais do usuário. -->
                        <div class="avatar">
                            <?= escapar($iniciaisUsuario) ?>
                        </div>

                        <!-- Nome e perfil do usuário conectado. -->
                        <div class="user">

                            <strong>
                                <?= escapar($nomeUsuario) ?>
                            </strong>

                            <small>
                                <?= escapar($perfilUsuario) ?>
                            </small>

                        </div>

                        <!-- Seta do menu do perfil. -->
                        <i class="fa-solid fa-chevron-down profile-arrow"></i>

                    </button>


                    <!-- Menu exibido ao clicar no perfil. -->
                    <div class="profile-menu" id="profileMenu">

                        <!-- Título da área de troca de usuário. -->
                        <div class="profile-menu-title">

                            <i class="fa-solid fa-right-left"></i>

                            <div>

                                <strong>Trocar usuário</strong>

                                <small>
                                    Selecione a conta e informe a senha.
                                </small>

                            </div>

                        </div>


                        <!-- Exibe uma mensagem caso exista algum aviso para o usuário. -->
                        <?php if ($mensagemUsuario): ?>

                            <div
                                class="profile-message <?= escapar($mensagemUsuario['tipo']) ?>"
                                role="alert"
                                aria-live="polite">

                                <?= escapar($mensagemUsuario['texto']) ?>

                            </div>

                        <?php endif; ?>


                        <!-- Verifica se existem usuários ativos para realizar a troca. -->
                        <?php if ($usuariosAtivos): ?>

                            <!-- Formulário responsável pela troca de usuário. -->
                            <form class="switch-user-form" method="post">

                                <!-- Identifica a ação que será executada pelo formulário. -->
                                <input
                                    type="hidden"
                                    name="acao"
                                    value="trocar_usuario">

                                <!-- Guarda a página para onde o usuário deverá retornar. -->
                                <input
                                    type="hidden"
                                    name="retorno"
                                    value="<?= escapar($retornoAtual) ?>">


                                <!-- Campo para selecionar o usuário. -->
                                <label for="usuarioTroca">
                                    Usuário
                                </label>

                                <select
                                    id="usuarioTroca"
                                    name="usuario_id"
                                    required>

                                    <option value="">
                                        Selecione
                                    </option>


                                    <!-- Percorre todos os usuários ativos. -->
                                    <?php foreach ($usuariosAtivos as $usuario): ?>

                                        <option
                                            value="<?= (int) $usuario['id'] ?>"
                                            <?= ($usuarioTrocaSelecionado === (int) $usuario['id']) ? 'selected' : '' ?>>

                                            <?= escapar($usuario['nome']) ?>
                                            —
                                            <?= escapar(nomePerfilUsuario($usuario['perfil'])) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>


                                <!-- Campo para informar a senha do usuário selecionado. -->
                                <label for="senhaTroca">
                                    Senha
                                </label>

                                <div class="password-inline">

                                    <input
                                        id="senhaTroca"
                                        name="senha"
                                        type="password"
                                        autocomplete="current-password"
                                        placeholder="Digite a senha"
                                        required>

                                    <!-- Botão para mostrar ou esconder a senha. -->
                                    <button
                                        class="toggle-password"
                                        type="button"
                                        data-password-target="senhaTroca"
                                        title="Mostrar ou ocultar senha">

                                        <i class="fa-regular fa-eye"></i>

                                    </button>

                                </div>


                                <!-- Botão que envia o formulário para trocar o usuário. -->
                                <button
                                    class="switch-user-button"
                                    type="submit">

                                    Trocar usuário

                                </button>

                            </form>

                        <?php else: ?>

                            <!-- Mensagem exibida quando ainda não existem usuários ativos. -->
                            <div class="profile-empty">
                                Nenhum usuário foi criado ainda.
                            </div>

                        <?php endif; ?>


                        <!--
                            Exibe o link para criação de usuário somente quando
                            o usuário possui permissão ou ainda não existem usuários.
                        -->
                        <?php if ($podeCriarUsuario): ?>

                            <a
                                class="create-user-link"
                                href="<?= BASE_URL ?>/pages/novo-usuario.php"

                                <i class="fa-solid fa-user-plus"></i>

                                Criar novo usuário

                            </a>

                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </header>


        <!--
            Área onde o conteúdo específico de cada página
            será inserido.
        -->
        <main>
            