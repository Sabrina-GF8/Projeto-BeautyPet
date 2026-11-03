<?php
/**
 * ARQUIVO: includes/topo.php
 * RESPONSABILIDADE: Cabeçalho e menu lateral compartilhados.
 * Monta a estrutura visual comum das páginas, identifica o item ativo, mostra o usuário logado e disponibiliza busca e ações de conta.
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

// Conta as solicitações pendentes vindas do aplicativo mobile.
// O sino usa esse total para acender imediatamente quando a página é carregada.
$totalSolicitacoesPendentesTopo = 0;

try {
    $stmtNotificacaoTopo = $pdo->query(
        "SELECT COUNT(*)
         FROM agendamentos
         WHERE origem = 'MOBILE'
           AND status = 'SOLICITADO'"
    );

    $totalSolicitacoesPendentesTopo = (int) $stmtNotificacaoTopo->fetchColumn();
} catch (Throwable $erroNotificacaoTopo) {
    // O cabeçalho não deve impedir o restante do sistema de abrir
    // se houver uma falha momentânea na consulta das notificações.
    $totalSolicitacoesPendentesTopo = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($tituloPagina) ?> | Beauty Pet</title>
    
    <!-- Utilização da constante BASE_URL unificada para garantir caminhos absolutos -->
    <!-- CSS compartilhado por TODAS as páginas: layout base (topo/rodapé) e busca de pets/tutores. -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/topo-rodape.css?v=css-dividido-v1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/buscar.css?v=css-dividido-v1">

    <?php
    // CSS específico de cada página: a própria página declara $cssPagina antes de incluir este topo.
    // Aceita um nome ('agenda') ou uma lista (['dashboard-base', 'ia']). Só nomes simples são aceitos.
    foreach ((array) ($cssPagina ?? []) as $arquivoCssPagina):
        if (!preg_match('/^[a-z0-9-]+$/', (string) $arquivoCssPagina)) {
            continue;
        }
    ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $arquivoCssPagina ?>.css?v=css-dividido-v1">
    <?php endforeach; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <!-- [BP-ALTERAÇÃO #4] Preferências de aparência/acessibilidade (escolhidas em pages/configuracoes.php).
         Ficam no <head> para o tema escuro ser aplicado antes de a página aparecer. -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/preferencias.css?v=bp-config-v1">
    <script src="<?= BASE_URL ?>/assets/js/preferencias.js?v=bp-config-v1"></script>

    <!-- [BP-CARREGANDO] Heurística 1 de Nielsen (visibilidade do status do sistema): bolinha de carregamento nos botões,
         bloqueio de envio duplicado e aviso de internet. Vale para TODOS os formulários sem alterar as páginas. Veja o topo do carregando.js. -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/carregamento-global.css?v=bp-carregando-v1">
    <script src="<?= BASE_URL ?>/assets/js/carregamento-global.js?v=bp-carregando-v1"></script>

    <!-- [BP-TOAST] Mensagens de sucesso e de erro viram toast: flutuam na parte de baixo da tela. Sucesso some em 7 segundos com fade-out;
         erro fica até a pessoa fechar e leva ao campo que precisa ser corrigido (Heurística 9).
         As páginas continuam usando <div class="message success|error"> normalmente. Veja o topo do toast-mensagens.js. -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/toast-mensagens.css?v=bp-toast-v1">
    <script src="<?= BASE_URL ?>/assets/js/toast-mensagens.js?v=bp-toast-v1"></script>
</head>
<body>
<!-- [BP-ALTERAÇÃO #4] Acessibilidade: quem usa teclado ou leitor de tela pula direto o menu. Só aparece ao receber foco (Tab). -->
<a class="bp-pular-conteudo" href="#conteudo-principal">Pular para o conteúdo</a>
<div class="container">
    <aside class="sidebar">
        <div class="logo">
            <img src="<?= BASE_URL ?>/assets/logo.png" alt="Logo Beauty Pet">
            <div class="logo-text">
                <h2>Beauty Pet</h2>
                <small>Sistema de Gestão</small>
            </div>
        </div>

        <nav>
            <?php /* [BP-ATALHOS] Cada item do menu ganhou 2 atributos:
                     data-atalho="X"          -> a letra usada com Alt (lida pelo script no rodape.php, que faz a navegação e mostra a dica).
                     aria-keyshortcuts="Alt+X" -> avisa leitores de tela qual é o atalho do item.
                     Para trocar uma letra, mude os DOIS atributos do item. */ ?>
            <?php /* [BP-ALTERAÇÃO #4] aria-label: com o menu recolhido o texto fica invisível, e o leitor de tela (cegos) não lia o nome do item. */ ?>
            <a href="<?= BASE_URL ?>/index.php" aria-label="Dashboard" data-atalho="I" aria-keyshortcuts="Alt+I"
                class="menu-item <?= $paginaAtiva === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-table-columns"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/agenda.php" aria-label="Agenda" data-atalho="A" aria-keyshortcuts="Alt+A"
                class="menu-item <?= in_array($paginaAtiva, ['agenda', 'solicitacoes-agendamento'], true) ? 'active' : '' ?>">
                <i class="fa-regular fa-calendar"></i>
                <span>Agenda</span>
            </a>

            <details <?= in_array($paginaAtiva, ['fechamento-diario', 'fechamento-mensal'], true) ? 'open' : '' ?>>
                <summary aria-label="Fechamentos" class="menu-item <?= in_array($paginaAtiva, ['fechamento-diario', 'fechamento-mensal'], true) ? 'active' : '' ?>">
                    <div class="menu-left">
                        <i class="fa-solid fa-file-circle-check"></i>
                        <span>Fechamentos</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="<?= BASE_URL ?>/pages/fechamento-diario.php" data-atalho="R" aria-keyshortcuts="Alt+R" class="submenu <?= $paginaAtiva === 'fechamento-diario' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-day"></i>
                    <span>Diário</span>
                </a>

                <a href="<?= BASE_URL ?>/pages/fechamento-mensal.php" data-atalho="M" aria-keyshortcuts="Alt+M" class="submenu <?= $paginaAtiva === 'fechamento-mensal' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-week"></i>
                    <span>Mensal</span>
                </a>
            </details>

            <details <?= in_array($paginaAtiva, ['cadastro-tutor', 'fichas-tutores'], true) ? 'open' : '' ?>>
                <summary aria-label="Tutores" class="menu-item <?= in_array($paginaAtiva, ['cadastro-tutor', 'fichas-tutores'], true) ? 'active' : '' ?>">
                    <div class="menu-left">
                        <i class="fa-solid fa-users"></i>
                        <span>Tutores</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="<?= BASE_URL ?>/pages/cadastro-tutor.php" data-atalho="U" aria-keyshortcuts="Alt+U"
                    class="submenu <?= $paginaAtiva === 'cadastro-tutor' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Cadastro</span>
                </a>

                <a href="<?= BASE_URL ?>/pages/fichas-tutores.php" data-atalho="O" aria-keyshortcuts="Alt+O"
                    class="submenu <?= $paginaAtiva === 'fichas-tutores' ? 'active' : '' ?>">
                    <i class="fa-regular fa-address-card"></i>
                    <span>Fichas</span>
                </a>
            </details>

            <details <?= in_array($paginaAtiva, ['cadastro-pet', 'fichas-pets'], true) ? 'open' : '' ?>>
                <summary aria-label="Pets" class="menu-item <?= in_array($paginaAtiva, ['cadastro-pet', 'fichas-pets'], true) ? 'active' : '' ?>">
                    <div class="menu-left">
                        <i class="fa-solid fa-paw"></i>
                        <span>Pets</span>
                    </div>
                    <i class="fa-solid fa-chevron-down arrow"></i>
                </summary>

                <a href="<?= BASE_URL ?>/pages/cadastro-pet.php" data-atalho="P" aria-keyshortcuts="Alt+P"
                    class="submenu <?= $paginaAtiva === 'cadastro-pet' ? 'active' : '' ?>">
                    <i class="fa-solid fa-dog"></i>
                    <span>Cadastro</span>
                </a>

                <a href="<?= BASE_URL ?>/pages/fichas-pets.php" data-atalho="X" aria-keyshortcuts="Alt+X"
                    class="submenu <?= $paginaAtiva === 'fichas-pets' ? 'active' : '' ?>">
                    <i class="fa-solid fa-notes-medical"></i>
                    <span>Fichas</span>
                </a>
            </details>

            <?php /* [BP-ALTERAÇÃO #5] Financeiro e Configurações: as páginas foram criadas e o item fica destacado quando aberto. */ ?>
            <a href="<?= BASE_URL ?>/pages/financeiro.php" aria-label="Financeiro" data-atalho="N" aria-keyshortcuts="Alt+N"
                class="menu-item <?= $paginaAtiva === 'financeiro' ? 'active' : '' ?>">
                <i class="fa-solid fa-dollar-sign"></i>
                <span>Financeiro</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/configuracoes.php" aria-label="Configurações" data-atalho="G" aria-keyshortcuts="Alt+G"
                class="menu-item <?= $paginaAtiva === 'configuracoes' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Configurações</span>
            </a>
        </nav>
    </aside>

    <div class="content">
        <header>
            <!-- Preservada a barra de pesquisa dinâmica do Topo 1 -->
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
                <a id="requestNotificationBell"
                    class="notification notification-link <?= $totalSolicitacoesPendentesTopo > 0 ? 'has-notifications' : '' ?>"
                    href="<?= BASE_URL ?>/pages/solicitacoes-agendamento.php?aba=pendentes&origem=sino#solicitacoes-pendentes"
                    data-initial-count="<?= $totalSolicitacoesPendentesTopo ?>"
                    aria-label="<?= $totalSolicitacoesPendentesTopo > 0
                        ? escapar($totalSolicitacoesPendentesTopo . ' solicitação(ões) de agendamento pendente(s)')
                        : 'Nenhuma solicitação de agendamento pendente' ?>"
                    title="Solicitações de agendamento">
                    <i class="fa-solid fa-bell"></i>
                    <span id="requestNotificationBadge" class="notification-badge"
                        <?= $totalSolicitacoesPendentesTopo > 0 ? '' : 'hidden' ?>>
                        <?= $totalSolicitacoesPendentesTopo > 99 ? '99+' : $totalSolicitacoesPendentesTopo ?>
                    </span>
                </a>

                <!-- [BP-ALTERAÇÃO #4] Engrenagem de Configurações ao lado do sino (lugar onde as pessoas costumam procurar). -->
                <a class="settings-link <?= $paginaAtiva === 'configuracoes' ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>/pages/configuracoes.php"
                    aria-label="Configurações" title="Configurações">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                </a>

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

        <!-- Preservada a interface e segurança do modal de troca de usuário (Topo 1) -->
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
                    <form class="account-login-form" method="post" autocomplete="off">
                        <input type="hidden" name="acao" value="trocar_usuario">
                        <input type="hidden" name="retorno" value="<?= escapar($retornoAtual) ?>">

                        <div class="account-field">
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
                    <!-- Caminho atualizado com BASE_URL e tag corrigida -->
                    <a class="account-secondary-link" href="<?= BASE_URL ?>/pages/novo-usuario.php">
                        <i class="fa-solid fa-user-plus"></i>
                        Criar novo usuário
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($mensagemUsuario['tipo'] ?? '') === 'success'): ?>
            <!-- [BP-ALTERAÇÃO #4] A mensagem "Usuário alterado para..." era gravada mas nunca aparecia
                 (o modal só abre sozinho quando dá erro). Agora aparece um aviso no canto da tela. -->
            <!-- [BP-TOAST] Passa a usar o mesmo toast das outras mensagens de sucesso (antes era um aviso verde à parte). -->
            <div class="message success" role="status"><?= escapar($mensagemUsuario['texto']) ?></div>
        <?php endif; ?>

        <!-- Área principal onde o conteúdo específico da página é exibido. -->
        <main id="conteudo-principal" tabindex="-1"><?php /* [BP-ALTERAÇÃO #4] id usado pelo link "Pular para o conteúdo". */ ?>