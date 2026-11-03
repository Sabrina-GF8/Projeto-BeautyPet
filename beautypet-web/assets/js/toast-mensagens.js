/*
 * ARQUIVO: assets/js/toast-mensagens.js
 * [BP-TOAST] Arquivo novo. Carregado no <head> de todas as páginas pelo includes/topo.php.
 *
 * HEURÍSTICA 1 (visibilidade do status) + HEURÍSTICA 9 (ajudar a reconhecer, diagnosticar e recuperar erros).
 *
 * As páginas continuam escrevendo a mensagem do PHP do jeito de sempre:
 *     <div class="message success">Tutor cadastrado com sucesso!</div>
 *     <div class="message error">Não foi possível salvar...</div>
 * Este arquivo pega essas mensagens e mostra cada uma como "toast": um aviso flutuante na parte de baixo da tela,
 * sempre visível mesmo se a pessoa estiver no fim de um formulário comprido.
 *
 *   SUCESSO (verde) .... some sozinho depois de 7 segundos (com fade-out).
 *   AVISO   (amarelo) .. some sozinho depois de 7 segundos.
 *   ERRO    (vermelho) . NÃO some sozinho: fica até a pessoa fechar no X. Dá tempo de ler e entender o problema.
 *                        Se houver campo com erro (aria-invalid="true"), o toast ganha o botão "Corrigir campo",
 *                        que leva o cursor direto até ele, e o campo também recebe o foco ao abrir a página.
 *
 * Passar o mouse por cima (ou focar com Tab) pausa a contagem. Esc fecha o último aviso.
 *
 * USO PELO JAVASCRIPT DAS PÁGINAS:
 *     BPToast.mostrar('Pagamento registrado.', 'success');
 *     BPToast.mostrar('Não foi possível carregar. Atualize a página (F5).', 'error');
 *
 * PARA MANTER UMA MENSAGEM NO LUGAR ORIGINAL (sem virar toast): coloque data-sem-toast na <div>.
 */
(function () {
    'use strict';

    const DURACAO_MS = 7000;

    const TIPOS = {
        success: { icone: 'fa-circle-check', titulo: 'Tudo certo' },
        info: { icone: 'fa-circle-info', titulo: 'Informação' },
        warning: { icone: 'fa-triangle-exclamation', titulo: 'Atenção' },
        error: { icone: 'fa-circle-exclamation', titulo: 'Algo deu errado' },
    };

    let pilha = null;

    function obterPilha() {
        if (pilha) return pilha;

        pilha = document.createElement('section');
        pilha.className = 'bp-toasts';
        pilha.setAttribute('aria-label', 'Avisos do sistema');
        document.body.appendChild(pilha);
        return pilha;
    }

    function fechar(toast) {
        if (!toast || toast.classList.contains('is-saindo')) return;

        clearTimeout(toast._temporizador);
        toast.classList.add('is-saindo');
        // Espera o fade-out terminar; o setTimeout garante a remoção mesmo sem animação.
        setTimeout(() => toast.remove(), 350);
    }

    function iniciarContagem(toast, restante) {
        toast._fim = Date.now() + restante;
        toast._temporizador = setTimeout(() => fechar(toast), restante);
        toast.style.setProperty('--bp-toast-duracao', restante + 'ms');
        toast.classList.remove('is-pausado');
    }

    // Primeiro campo marcado com erro pelo PHP (aria-invalid="true" ou classe input-error).
    function primeiroCampoComErro() {
        return document.querySelector('main [aria-invalid="true"], main .input-error');
    }

    function irParaCampo(campo) {
        campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        campo.focus({ preventScroll: true });
    }

    /**
     * Mostra um aviso.
     * conteudo: texto (string) ou nós HTML já existentes (quando vem de uma <div class="message">).
     */
    function mostrar(conteudo, tipo = 'info', opcoes = {}) {
        if (!TIPOS[tipo]) tipo = 'info';
        const config = TIPOS[tipo];
        const ehErro = tipo === 'error';

        const toast = document.createElement('div');
        toast.className = `bp-toast bp-toast-${tipo}`;
        // Erro interrompe o leitor de tela; sucesso espera ele terminar a frase atual.
        toast.setAttribute('role', ehErro ? 'alert' : 'status');
        toast.setAttribute('aria-live', ehErro ? 'assertive' : 'polite');

        toast.innerHTML =
            `<i class="fa-solid ${config.icone} bp-toast-icone" aria-hidden="true"></i>`
            + '<div class="bp-toast-corpo"><strong class="bp-toast-titulo"></strong><div class="bp-toast-texto"></div></div>'
            + '<button type="button" class="bp-toast-fechar" aria-label="Fechar aviso" title="Fechar (Esc)">'
            + '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';

        toast.querySelector('.bp-toast-titulo').textContent = opcoes.titulo || config.titulo;

        const texto = toast.querySelector('.bp-toast-texto');
        if (typeof conteudo === 'string') texto.textContent = conteudo;
        else texto.append(...conteudo);

        // HEURÍSTICA 9: caminho direto para a correção.
        const campo = ehErro ? primeiroCampoComErro() : null;
        if (campo) {
            const corrigir = document.createElement('button');
            corrigir.type = 'button';
            corrigir.className = 'bp-toast-acao';
            corrigir.textContent = 'Corrigir campo';
            corrigir.addEventListener('click', () => irParaCampo(campo));
            toast.querySelector('.bp-toast-corpo').appendChild(corrigir);
        }

        toast.querySelector('.bp-toast-fechar').addEventListener('click', () => fechar(toast));

        // Erro fica até ser fechado; os outros somem sozinhos.
        const fixo = opcoes.fixo ?? ehErro;
        if (!fixo) {
            toast.classList.add('tem-contagem');
            iniciarContagem(toast, opcoes.duracao || DURACAO_MS);

            const pausar = () => {
                if (toast.classList.contains('is-pausado')) return;
                clearTimeout(toast._temporizador);
                toast._restante = Math.max(1500, toast._fim - Date.now());
                toast.classList.add('is-pausado');
            };
            const continuar = () => {
                if (toast.matches(':hover') || toast.contains(document.activeElement)) return;
                if (toast.classList.contains('is-pausado')) iniciarContagem(toast, toast._restante);
            };
            toast.addEventListener('mouseenter', pausar);
            toast.addEventListener('focusin', pausar);
            toast.addEventListener('mouseleave', continuar);
            toast.addEventListener('focusout', () => setTimeout(continuar, 0));
        }

        obterPilha().appendChild(toast);
        return toast;
    }

    // Converte as <div class="message ..."> que o PHP colocou na página.
    function converterMensagensDaPagina() {
        const mensagens = document.querySelectorAll(
            '.message.success, .message.error, .message.info, .message.warning'
        );

        mensagens.forEach(mensagem => {
            if (mensagem.hasAttribute('data-sem-toast') || mensagem.closest('[data-sem-toast]')) return;
            if (!mensagem.textContent.trim()) return;

            const tipo = ['error', 'warning', 'success', 'info'].find(nome => mensagem.classList.contains(nome));
            mostrar(Array.from(mensagem.childNodes), tipo);
            mensagem.remove();
        });

        // Ao abrir a página com erro de validação, o cursor já vai para o campo a corrigir.
        const campo = primeiroCampoComErro();
        if (campo && !document.querySelector('.account-modal.is-open')) irParaCampo(campo);
    }

    // Esc fecha o aviso mais recente (só quando nenhuma janela está aberta, para não fechar as duas coisas).
    document.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape' || !pilha?.lastElementChild) return;
        if (document.body.classList.contains('account-modal-open')) return;
        fechar(pilha.lastElementChild);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', converterMensagensDaPagina, { once: true });
    } else {
        converterMensagensDaPagina();
    }

    window.BPToast = { mostrar, fechar };
})();
