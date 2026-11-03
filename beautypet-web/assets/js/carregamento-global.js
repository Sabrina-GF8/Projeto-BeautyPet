/*
 * ARQUIVO: assets/js/carregamento-global.js
 * [BP-CARREGANDO] Arquivo novo. Carregado no <head> de todas as páginas pelo includes/topo.php.
 *
 * HEURÍSTICA 1 DE NIELSEN (visibilidade do status do sistema) + HEURÍSTICA 5 (prevenção de erros).
 *
 * O QUE FAZ, SEM PRECISAR MEXER NAS PÁGINAS:
 *   1. Ao enviar qualquer formulário, o botão clicado ganha uma bolinha girando e o texto muda
 *      ("Salvando...", "Excluindo...", "Entrando...").
 *   2. Formulário POST (grava no banco): aparece uma tela por cima de tudo ("Salvando, aguarde...")
 *      que impede um segundo clique. Assim o mesmo cadastro não é gravado duas vezes.
 *      O mesmo formulário também não pode ser enviado de novo enquanto o primeiro envio não termina
 *      (vale até para quem aperta Enter várias vezes).
 *   3. Sem internet: o envio é barrado ANTES de sair do navegador, com um aviso dizendo o que fazer.
 *      Os dados digitados continuam no formulário.
 *   4. Ao clicar em um link do sistema, aparece uma barra fina no topo mostrando que a página está carregando.
 *   5. Se a resposta demorar, a tela de espera avisa que a internet parece lenta (a pessoa não fica sem saber).
 *
 * QUANDO A PÁGINA PRECISA CHAMAR NA MÃO (ex.: buscar dados com fetch, sem recarregar a página):
 *   BPCarregando.iniciar({ botao: meuBotao, mensagem: 'Buscando...', bloquearTela: false });
 *   ...
 *   BPCarregando.parar(meuBotao);
 *
 * PARA DESLIGAR EM UM FORMULÁRIO ESPECÍFICO: coloque o atributo data-sem-carregando no <form>.
 * PARA TROCAR O TEXTO DO BOTÃO: coloque data-texto-carregando="Enviando..." no botão.
 */
(function () {
    'use strict';

    // Depois deste tempo a tela de espera avisa que a conexão parece lenta.
    const AVISO_LENTO_MS = 8000;

    let telaEspera = null;
    let barraTopo = null;
    let temporizadorLento = null;
    const botoesOcupados = new Set();

    // ------------------------------------------------------------
    // Elementos criados uma única vez e reaproveitados
    // ------------------------------------------------------------

    function obterTelaEspera() {
        if (telaEspera) return telaEspera;

        telaEspera = document.createElement('div');
        telaEspera.className = 'bp-tela-espera';
        telaEspera.setAttribute('role', 'alertdialog');
        telaEspera.setAttribute('aria-modal', 'true');
        telaEspera.setAttribute('aria-live', 'assertive');
        telaEspera.setAttribute('aria-labelledby', 'bpTelaEsperaTitulo');
        telaEspera.hidden = true;
        telaEspera.innerHTML =
            '<div class="bp-tela-espera-caixa">'
            + '<span class="bp-spinner bp-spinner-grande" aria-hidden="true"></span>'
            + '<strong id="bpTelaEsperaTitulo"></strong>'
            + '<small class="bp-tela-espera-dica">Não feche nem atualize a página.</small>'
            + '<small class="bp-tela-espera-lento" hidden>A internet parece lenta. Continue aguardando: '
            + 'clicar de novo pode gravar os dados duas vezes.</small>'
            + '</div>';

        // Bloqueia teclado enquanto a tela está aberta (Enter/Espaço não disparam outro envio).
        telaEspera.tabIndex = -1;
        telaEspera.addEventListener('keydown', evento => {
            if (evento.key !== 'Tab') evento.preventDefault();
        });

        document.body.appendChild(telaEspera);
        return telaEspera;
    }

    function obterBarraTopo() {
        if (barraTopo) return barraTopo;

        barraTopo = document.createElement('div');
        barraTopo.className = 'bp-barra-carregando';
        barraTopo.setAttribute('aria-hidden', 'true');
        document.body.appendChild(barraTopo);
        return barraTopo;
    }

    // ------------------------------------------------------------
    // Botão em estado "carregando"
    // ------------------------------------------------------------

    // Escolhe um texto de acordo com o que o botão faz.
    function textoPadrao(botao, metodo) {
        if (botao?.dataset.textoCarregando) return botao.dataset.textoCarregando;

        const rotulo = (botao?.textContent || botao?.value || '').trim().toLowerCase();

        if (/exclu|remov|apag/.test(rotulo)) return 'Excluindo...';
        if (/entrar|login/.test(rotulo)) return 'Entrando...';
        if (/cancel/.test(rotulo)) return 'Cancelando...';
        if (/reabr/.test(rotulo)) return 'Reabrindo...';
        if (/finaliz|fechar|conclu/.test(rotulo)) return 'Finalizando...';
        if (/aprov|confirm|aceit/.test(rotulo)) return 'Confirmando...';
        if (/recus|rejeit/.test(rotulo)) return 'Recusando...';
        if (/envi/.test(rotulo)) return 'Enviando...';
        if (metodo === 'get' || /busc|filtr|pesquis/.test(rotulo)) return 'Carregando...';
        return 'Salvando...';
    }

    function marcarBotao(botao, texto) {
        if (!botao || botoesOcupados.has(botao)) return;

        botoesOcupados.add(botao);
        botao.dataset.bpConteudoOriginal = botao.tagName === 'INPUT' ? botao.value : botao.innerHTML;
        // Mantém a largura para o layout não "pular" quando o texto muda.
        botao.style.minWidth = botao.offsetWidth + 'px';
        botao.classList.add('bp-carregando');
        botao.setAttribute('aria-busy', 'true');
        botao.setAttribute('aria-disabled', 'true');

        if (botao.tagName === 'INPUT') {
            botao.value = texto;
        } else {
            botao.innerHTML = '<span class="bp-spinner" aria-hidden="true"></span><span></span>';
            botao.lastChild.textContent = texto;
        }
    }

    function desmarcarBotao(botao) {
        if (!botao || !botoesOcupados.has(botao)) return;

        botoesOcupados.delete(botao);
        const original = botao.dataset.bpConteudoOriginal;
        if (botao.tagName === 'INPUT') botao.value = original;
        else botao.innerHTML = original;

        delete botao.dataset.bpConteudoOriginal;
        botao.style.minWidth = '';
        botao.classList.remove('bp-carregando');
        botao.removeAttribute('aria-busy');
        botao.removeAttribute('aria-disabled');
        if (botao.dataset.bpDesativadoPorCarregamento) {
            botao.disabled = false;
            delete botao.dataset.bpDesativadoPorCarregamento;
        }
    }

    // ------------------------------------------------------------
    // API pública
    // ------------------------------------------------------------

    function iniciar(opcoes = {}) {
        const { botao = null, mensagem = 'Salvando, aguarde...', bloquearTela = true, metodo = 'post' } = opcoes;

        marcarBotao(botao, opcoes.textoBotao || textoPadrao(botao, metodo));
        obterBarraTopo().classList.add('is-ativa');

        if (!bloquearTela) return;

        const tela = obterTelaEspera();
        tela.querySelector('#bpTelaEsperaTitulo').textContent = mensagem;
        tela.querySelector('.bp-tela-espera-lento').hidden = true;
        tela.hidden = false;
        document.documentElement.classList.add('bp-ocupado');
        // Tira o foco do botão para Enter/Espaço não dispararem de novo.
        tela.focus({ preventScroll: true });

        clearTimeout(temporizadorLento);
        temporizadorLento = setTimeout(() => {
            tela.querySelector('.bp-tela-espera-lento').hidden = false;
        }, AVISO_LENTO_MS);
    }

    function parar(botao) {
        if (botao) desmarcarBotao(botao);
        else Array.from(botoesOcupados).forEach(desmarcarBotao);

        if (botoesOcupados.size) return;

        clearTimeout(temporizadorLento);
        barraTopo?.classList.remove('is-ativa');
        if (telaEspera) telaEspera.hidden = true;
        document.documentElement.classList.remove('bp-ocupado');
        document.querySelectorAll('form[data-bp-enviando]').forEach(form => delete form.dataset.bpEnviando);
    }

    // Aviso de erro: usa o toast (toast-mensagens.js) e, se ele não existir, um alert simples.
    function avisarErro(texto) {
        if (window.BPToast) window.BPToast.mostrar(texto, 'error');
        else window.alert(texto);
    }

    // ------------------------------------------------------------
    // 1, 2 e 3) Envio de formulários
    // ------------------------------------------------------------

    // Fase de captura: roda ANTES de qualquer outro código da página.
    // Se o formulário já está sendo enviado, o segundo envio é descartado aqui mesmo.
    window.addEventListener('submit', evento => {
        if (evento.target.dataset?.bpEnviando) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
        }
    }, true);

    // Fase de "bolha": roda DEPOIS do código da página. Assim, se a página cancelou o envio
    // (ex.: confirm('Excluir?') respondido com "Cancelar", ou campo obrigatório vazio), nada acontece.
    document.addEventListener('submit', evento => {
        const form = evento.target;
        if (evento.defaultPrevented || !(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-sem-carregando')) return;
        // Formulário que abre em outra aba ou baixa arquivo: a página atual não muda, então não bloqueia.
        if (form.target && form.target !== '_self') return;

        const metodo = (form.getAttribute('method') || 'get').toLowerCase();
        const botao = evento.submitter || form.querySelector('[type="submit"], button:not([type])');

        // HEURÍSTICA 5: sem internet o envio falharia e a pessoa veria a página de erro do navegador.
        if (metodo === 'post' && navigator.onLine === false) {
            evento.preventDefault();
            avisarErro('Sem conexão com a internet. Nada foi salvo e o que você digitou continua no formulário. '
                + 'Verifique o Wi-Fi ou o cabo e clique no botão de novo.');
            return;
        }

        form.dataset.bpEnviando = '1';

        iniciar({
            botao,
            metodo,
            bloquearTela: metodo === 'post',
            mensagem: metodo === 'post' ? textoPadrao(botao, metodo).replace('...', ', aguarde...') : 'Carregando...',
        });

        // Desativa os outros botões de envio do formulário. Fica para depois do envio começar porque
        // botão desativado não manda o name/value dele (alguns formulários usam isso para saber a ação).
        setTimeout(() => {
            form.querySelectorAll('button, input[type="submit"]').forEach(outro => {
                if (outro.type === 'button' || outro.disabled) return;
                outro.disabled = true;
                outro.dataset.bpDesativadoPorCarregamento = '1';
            });
        }, 0);
    });

    // form.submit() feito por código (ex.: trocar a data da agenda) não dispara o evento "submit".
    const submitOriginal = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (!this.hasAttribute('data-sem-carregando') && (!this.target || this.target === '_self')) {
            const metodo = (this.getAttribute('method') || 'get').toLowerCase();
            iniciar({ metodo, bloquearTela: metodo === 'post', mensagem: metodo === 'post' ? 'Salvando, aguarde...' : 'Carregando...' });
        }
        return submitOriginal.call(this);
    };

    // ------------------------------------------------------------
    // 4) Links do sistema: barra fina no topo
    // ------------------------------------------------------------

    document.addEventListener('click', evento => {
        if (evento.defaultPrevented || evento.button !== 0) return;
        if (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;

        const link = evento.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        const destino = new URL(link.href, location.href);
        if (destino.origin !== location.origin || !/^https?:$/.test(destino.protocol)) return;
        // Link para outra parte da mesma página (#ancora): não recarrega nada.
        if (destino.pathname === location.pathname && destino.search === location.search && destino.hash) return;

        obterBarraTopo().classList.add('is-ativa');
    });

    // ------------------------------------------------------------
    // Voltar pelo navegador: a página pode vir da memória ainda "carregando". Limpa tudo.
    // ------------------------------------------------------------

    window.addEventListener('pageshow', () => parar());

    // Se a internet cair com a página aberta, avisa na hora (antes de a pessoa tentar salvar).
    window.addEventListener('offline', () => {
        avisarErro('A internet caiu. Espere a conexão voltar antes de salvar, para não perder o que foi digitado.');
    });
    window.addEventListener('online', () => {
        window.BPToast?.mostrar('Conexão restabelecida. Você já pode salvar.', 'success');
    });

    window.BPCarregando = { iniciar, parar };
})();
