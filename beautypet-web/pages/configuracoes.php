<?php
/**
 * ARQUIVO: pages/configuracoes.php
 * [BP-ALTERAÇÃO #4 e #5] Página nova (o menu já tinha o link, mas o arquivo não existia e dava erro 404).
 *
 * RESPONSABILIDADE: reunir as configurações do sistema em um só lugar.
 *   1. Conta          -> usuário atual, trocar de conta, alterar a própria senha, criar usuário (só administradora).
 *   2. Aparência      -> tema claro/escuro/seguir o sistema e tamanho da letra.
 *   3. Acessibilidade -> alto contraste, reduzir animações, foco reforçado, alerta visual e tradutor de Libras (VLibras).
 *   4. Sobre          -> versão e onde ficam as preferências.
 *
 * ONDE FICA CADA COISA:
 *   - Senha: tabela "usuarios" (coluna senha_hash), processada aqui mesmo no POST "alterar_senha".
 *   - Troca de conta: usa o modal que já existe no includes/topo.php (POST "trocar_usuario" em config/config.php).
 *   - Aparência e acessibilidade: guardadas NO NAVEGADOR pelo assets/js/preferencias.js (não mexe no banco).
 *     O visual de cada opção está em assets/css/preferencias.css.
 */
require __DIR__ . '/../config/config.php';

// Usuário logado nesta sessão (null quando ninguém entrou ainda).
$usuarioAtual = obterUsuarioAtual($pdo);
$ehAdministradora = ($usuarioAtual['perfil'] ?? null) === 'ADMINISTRADOR';

$mensagem = '';
$tipoMensagem = '';

// ---------------------------------------------------------------------------
// Alterar a própria senha (mesma regra do novo-usuario.php: mínimo de 8 caracteres).
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alterar_senha') {
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $senhaNova = (string) ($_POST['senha_nova'] ?? '');
    $confirmacao = (string) ($_POST['confirmar_senha'] ?? '');

    if (!$usuarioAtual) {
        $mensagem = 'Entre em uma conta antes de alterar a senha.';
        $tipoMensagem = 'error';
    } elseif ($senhaAtual === '' || $senhaNova === '' || $confirmacao === '') {
        $mensagem = 'Preencha a senha atual, a nova senha e a confirmação.';
        $tipoMensagem = 'error';
    } elseif (strlen($senhaNova) < 8) {
        $mensagem = 'A nova senha deve possuir pelo menos 8 caracteres.';
        $tipoMensagem = 'error';
    } elseif ($senhaNova !== $confirmacao) {
        $mensagem = 'A confirmação da nova senha não corresponde.';
        $tipoMensagem = 'error';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id AND ativo = TRUE LIMIT 1');
            $stmt->execute([':id' => (int) $usuarioAtual['id']]);
            $hashAtual = (string) $stmt->fetchColumn();

            if ($hashAtual === '' || !password_verify($senhaAtual, $hashAtual)) {
                $mensagem = 'A senha atual está incorreta.';
                $tipoMensagem = 'error';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios
                     SET senha_hash = :senha_hash,
                         atualizado_em = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':senha_hash' => password_hash($senhaNova, PASSWORD_DEFAULT),
                    ':id'         => (int) $usuarioAtual['id'],
                ]);

                // Redireciona para não reenviar o formulário ao atualizar a página.
                header('Location: configuracoes.php?senha=alterada#conta');
                exit;
            }
        } catch (Throwable $erro) {
            $mensagem = 'Não foi possível alterar a senha agora.';
            $tipoMensagem = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['senha'] ?? '') === 'alterada') {
    $mensagem = 'Senha alterada com sucesso!';
    $tipoMensagem = 'success';
}

$tituloPagina = 'Configurações';
$paginaAtiva = 'configuracoes';
// cadastro.css: reaproveita o visual dos formulários (form-card, field, save-button, message).
// configuracoes.css: só o que é próprio desta página (opções de tema, chaves liga/desliga).
$cssPagina = ['cadastro', 'configuracoes'];
require __DIR__ . '/../includes/topo.php';
?>

<div class="page settings-page">
    <div class="page-heading">
        <div>
            <h1><i class="fa-solid fa-gear" aria-hidden="true"></i> Configurações</h1>
            <small>Conta, aparência e acessibilidade</small>
        </div>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="message <?= escapar($tipoMensagem) ?>" role="<?= $tipoMensagem === 'error' ? 'alert' : 'status' ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- ===================== 1. CONTA ===================== -->
    <section class="form-card settings-card" id="conta" aria-labelledby="tituloConta">
        <h2 id="tituloConta"><i class="fa-regular fa-user" aria-hidden="true"></i> Conta</h2>

        <div class="form-section">
            <div class="settings-account">
                <div class="avatar" aria-hidden="true"><?= escapar($iniciaisUsuario) ?></div>
                <div>
                    <strong><?= escapar($nomeUsuario) ?></strong>
                    <small><?= escapar($usuarioAtual['email'] ?? 'Nenhuma conta ativa') ?> · <?= escapar($perfilUsuario) ?></small>
                </div>
                <div class="settings-account-actions">
                    <!-- Abre o mesmo modal do botão de perfil no topo (includes/topo.php). -->
                    <button type="button" class="settings-button" onclick="abrirTrocaUsuario()">
                        <i class="fa-solid fa-right-left" aria-hidden="true"></i> Trocar de conta
                    </button>
                    <?php if ($ehAdministradora || !$usuariosAtivos): ?>
                        <a class="settings-button" href="novo-usuario.php">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Criar novo usuário
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($usuarioAtual): ?>
            <form class="form-section" method="post" action="configuracoes.php#conta" autocomplete="off">
                <input type="hidden" name="acao" value="alterar_senha">
                <h3>Alterar minha senha</h3>
                <div class="form-grid">
                    <div class="field span-4">
                        <label for="senhaAtual">Senha atual</label>
                        <div class="password-field">
                            <input id="senhaAtual" name="senha_atual" type="password" autocomplete="current-password" required>
                            <button class="toggle-password" type="button" data-password-target="senhaAtual" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="field span-4">
                        <label for="senhaNova">Nova senha <small>(mínimo 8 caracteres)</small></label>
                        <div class="password-field">
                            <input id="senhaNova" name="senha_nova" type="password" minlength="8" autocomplete="new-password" required>
                            <button class="toggle-password" type="button" data-password-target="senhaNova" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="field span-4">
                        <label for="confirmarSenha">Confirmar nova senha</label>
                        <div class="password-field">
                            <input id="confirmarSenha" name="confirmar_senha" type="password" minlength="8" autocomplete="new-password" required>
                            <button class="toggle-password" type="button" data-password-target="confirmarSenha" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="settings-actions">
                    <button class="save-button" type="submit">Salvar nova senha</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <!-- ===================== 2. APARÊNCIA ===================== -->
    <!-- As opções abaixo são salvas na hora (sem botão), pelo script no fim desta página. -->
    <section class="form-card settings-card" aria-labelledby="tituloAparencia">
        <h2 id="tituloAparencia"><i class="fa-solid fa-palette" aria-hidden="true"></i> Aparência</h2>

        <div class="form-section">
            <h3 id="rotuloTema">Tema</h3>
            <div class="settings-choices" role="radiogroup" aria-labelledby="rotuloTema">
                <label class="settings-choice">
                    <input type="radio" name="tema" value="claro" data-preferencia="tema">
                    <i class="fa-regular fa-sun" aria-hidden="true"></i>
                    <span>Claro</span>
                </label>
                <label class="settings-choice">
                    <input type="radio" name="tema" value="escuro" data-preferencia="tema">
                    <i class="fa-regular fa-moon" aria-hidden="true"></i>
                    <span>Escuro</span>
                </label>
                <label class="settings-choice">
                    <input type="radio" name="tema" value="sistema" data-preferencia="tema">
                    <i class="fa-solid fa-desktop" aria-hidden="true"></i>
                    <span>Seguir o computador</span>
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3 id="rotuloFonte">Tamanho da letra</h3>
            <div class="settings-choices" role="radiogroup" aria-labelledby="rotuloFonte">
                <label class="settings-choice">
                    <input type="radio" name="fonte" value="normal" data-preferencia="fonte">
                    <span class="settings-font-sample" style="font-size:15px">Aa</span>
                    <span>Normal</span>
                </label>
                <label class="settings-choice">
                    <input type="radio" name="fonte" value="grande" data-preferencia="fonte">
                    <span class="settings-font-sample" style="font-size:19px">Aa</span>
                    <span>Grande</span>
                </label>
                <label class="settings-choice">
                    <input type="radio" name="fonte" value="muito-grande" data-preferencia="fonte">
                    <span class="settings-font-sample" style="font-size:23px">Aa</span>
                    <span>Muito grande</span>
                </label>
            </div>
        </div>
    </section>

    <!-- ===================== 3. ACESSIBILIDADE ===================== -->
    <section class="form-card settings-card" aria-labelledby="tituloAcessibilidade">
        <h2 id="tituloAcessibilidade"><i class="fa-solid fa-universal-access" aria-hidden="true"></i> Acessibilidade</h2>

        <div class="form-section settings-toggles">
            <h3>Visão</h3>
            <label class="settings-toggle">
                <input type="checkbox" data-preferencia="contraste">
                <span>
                    <strong>Alto contraste</strong>
                    <small>Textos e bordas mais escuros, mais fáceis de enxergar.</small>
                </span>
            </label>
            <label class="settings-toggle">
                <input type="checkbox" data-preferencia="focoReforcado">
                <span>
                    <strong>Destacar o campo selecionado</strong>
                    <small>Mostra uma borda azul forte em volta do botão ou campo ao navegar com a tecla Tab.</small>
                </span>
            </label>
            <label class="settings-toggle">
                <input type="checkbox" data-preferencia="reduzirAnimacoes">
                <span>
                    <strong>Reduzir animações</strong>
                    <small>Tira movimentos e efeitos (bom para quem sente tontura ou se distrai com movimento).</small>
                </span>
            </label>
            <p class="settings-note">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Leitores de tela (como o NVDA) e o link "Pular para o conteúdo" (aperte Tab ao abrir a página)
                funcionam sempre, sem precisar ligar nada.
            </p>
        </div>

        <div class="form-section settings-toggles">
            <h3>Audição</h3>
            <label class="settings-toggle">
                <input type="checkbox" data-preferencia="alertaVisual">
                <span>
                    <strong>Alerta visual de solicitações</strong>
                    <small>Quando chega um pedido de agendamento pelo app, aparece um aviso vermelho piscando na tela, além do sino.</small>
                </span>
            </label>
            <label class="settings-toggle">
                <input type="checkbox" data-preferencia="vlibras">
                <span>
                    <strong>Tradutor de Libras (VLibras)</strong>
                    <small>Mostra o botão azul do VLibras (governo federal, gratuito). Selecione um texto e o avatar traduz para Libras. Precisa de internet.</small>
                </span>
            </label>
        </div>

        <div class="settings-actions">
            <span class="settings-saved" id="preferenciasSalvas" role="status" aria-live="polite"></span>
            <button type="button" class="settings-button" id="restaurarPreferencias">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Voltar ao padrão
            </button>
        </div>
    </section>

    <!-- ===================== 4. SOBRE ===================== -->
    <section class="form-card settings-card" aria-labelledby="tituloSobre">
        <h2 id="tituloSobre"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Sobre</h2>
        <div class="form-section">
            <p>Beauty Pet · Sistema de Gestão</p>
            <p class="settings-note">
                As opções de Aparência e Acessibilidade ficam salvas <strong>neste computador/navegador</strong>.
                Em outro computador, é preciso escolher de novo.
            </p>
        </div>
    </section>
</div>

<script>
// Liga os campos desta página ao assets/js/preferencias.js (carregado no topo de todas as páginas).
(function () {
    const preferencias = window.BeautyPetPreferencias;
    if (!preferencias) return;

    const campos = document.querySelectorAll('[data-preferencia]');
    const aviso = document.querySelector('#preferenciasSalvas');
    let temporizadorAviso;

    // Marca na tela o que já está salvo.
    const preencher = () => {
        const atuais = preferencias.ler();
        campos.forEach((campo) => {
            const valor = atuais[campo.dataset.preferencia];
            if (campo.type === 'checkbox') campo.checked = Boolean(valor);
            else campo.checked = campo.value === valor;
        });
    };

    // Cada mudança é salva e aplicada na hora.
    campos.forEach((campo) => campo.addEventListener('change', () => {
        const atuais = preferencias.ler();
        atuais[campo.dataset.preferencia] = campo.type === 'checkbox' ? campo.checked : campo.value;
        preferencias.salvar(atuais);

        aviso.textContent = 'Preferência salva.';
        clearTimeout(temporizadorAviso);
        temporizadorAviso = setTimeout(() => { aviso.textContent = ''; }, 2500);
    }));

    document.querySelector('#restaurarPreferencias')?.addEventListener('click', () => {
        const vlibrasEstavaLigado = preferencias.ler().vlibras;
        preferencias.salvar({ ...preferencias.PADRAO });
        preencher();
        // O VLibras só some ao recarregar a página.
        if (vlibrasEstavaLigado) window.location.reload();
        else aviso.textContent = 'Preferências voltaram ao padrão.';
    });

    // Desligar o VLibras precisa recarregar a página para o botão sumir.
    document.querySelector('[data-preferencia="vlibras"]')?.addEventListener('change', (evento) => {
        if (!evento.target.checked) window.location.reload();
    });

    preencher();
})();
</script>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
