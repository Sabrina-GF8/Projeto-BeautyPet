/*
 * ARQUIVO: assets/js/carregando.js
 * [BP-CARREGANDO] Arquivo novo. Carregado em todas as páginas pelo includes/topo.php.
 *
 * RESPONSABILIDADE (Heurística 1 de Nielsen: visibilidade do status do sistema):
 *   1) Mostrar uma bolinha girando no botão e um aviso de texto ("Salvando...") enquanto algo é enviado,
 *      para a pessoa saber que o sistema está trabalhando, principalmente quando a internet está lenta.
 *   2) Impedir envio duplicado: depois do primeiro clique, qualquer outro clique/Enter em botão de enviar é ignorado,
 *      então o mesmo dado não chega duas vezes ao banco.
 *   3) Avisar quando a internet cai ou volta, e não tentar enviar sem conexão.
 *
 * COMO FUNCIONA SEM MEXER NAS PÁGINAS:
 *   Este arquivo escuta o evento "submit" de TODOS os formulários da página. Formulários novos já nascem protegidos.
 *   Não é preciso escrever nada em cada tela.
 *
 * O QUE NÃO É TRATADO (de propósito):
 *   - Formulário cujo envio foi cancelado por outro código (ex.: confirm("Excluir?") respondido "Cancelar",
 *     ou validação própria da página). Se o envio não acontece, não há o que mostrar.
 *   - Formulário que não troca a página (feito com fetch/AJAX, como o chat do assistente).
 *     Para esses, use a função BPCarregando.executar(...) descrita abaixo.
 *
 * COMO AJUSTAR EM UM CASO ESPECÍFICO (todos opcionais):
 *   data-carregando="Texto..."   no <form> ou no <button>: troca a mensagem do aviso.
 *   data-sem-carregando          no <form>: este formulário não recebe o indicador nem o bloqueio.
 *
 * PARA CÓDIGO COM fetch (AJAX):
 *   BPCarregando.executar(botao, () => fetch(...), { mensagem: "Abrindo o registro..." });
 *   Mostra a bolinha no botão, ignora cliques repetidos e tira tudo sozinho ao terminar (com sucesso ou erro).
 *   Também existem BPCarregando.iniciar({ botao, mensagem }) e BPCarregando.parar(botao) para quem precisar de controle manual.
 *
 * Visual: assets/css/carregando.css
 */
(function () {
    "use strict";

    // Evita registrar tudo duas vezes se o arquivo for carregado em duplicidade.
    if (window.BPCarregando) return;

    // ---------- Tempos (em milissegundos) ----------
    // Só mostra o aviso de texto depois deste tempo, para envios rápidos não piscarem na tela.
    var ATRASO_AVISO = 400;
    // Depois deste tempo o texto passa a explicar que a internet pode estar lenta.
    var ATRASO_LENTO = 8000;
    // Depois deste tempo aparece o botão "Tentar de novo".
    var ATRASO_TRAVADO = 30000;

    var TEXTO_LENTO = "Está demorando mais que o normal. Sua internet pode estar lenta. Aguarde e não feche a página.";
    var TEXTO_TRAVADO = "Ainda sem resposta. Se quiser tentar de novo, confira antes se o registro já não foi salvo.";

    // Texto do aviso conforme o botão clicado (primeira regra que combinar vence).
    // Quem quiser outro texto num botão específico usa data-carregando="...".
    var REGRAS_MENSAGEM = [
        [/excluir|apagar|remover/, "Excluindo..."],
        [/recusar/, "Recusando..."],
        [/cancelar/, "Cancelando..."],
        [/reabrir/, "Reabrindo..."],
        [/finalizar|confirmar fechamento/, "Finalizando..."],
        [/confirmar/, "Confirmando..."],
        [/entrar/, "Entrando..."],
        [/iniciar/, "Iniciando..."],
        [/concluir/, "Concluindo..."],
        [/salvar|criar|cadastrar|gravar/, "Salvando..."]
    ];

    // Envio em andamento. Só existe um por vez: enquanto houver um, os outros são ignorados.
    var atual = null;

    // Elementos visuais, criados só na primeira vez que são necessários.
    var camada = null;
    var aviso = null;
    var textoAviso = null;
    var botaoLiberar = null;
    var faixa = null;
    var temporizadorFaixa = null;

    // ------------------------------------------------------------
    // Elementos da tela
    // ------------------------------------------------------------

    function garantirElementos() {
        if (camada || !document.body) return;

        // Película que impede cliques no resto da tela durante o envio.
        camada = document.createElement("div");
        camada.className = "bp-carregando-camada";
        camada.hidden = true;
        camada.setAttribute("aria-hidden", "true");

        // Caixa com a bolinha e o texto. role="status" faz o leitor de tela anunciar a mensagem.
        aviso = document.createElement("div");
        aviso.className = "bp-carregando-aviso";
        aviso.setAttribute("role", "status");
        aviso.setAttribute("aria-live", "polite");
        aviso.hidden = true;

        var giro = document.createElement("span");
        giro.className = "bp-giro";
        giro.setAttribute("aria-hidden", "true");

        textoAviso = document.createElement("span");
        textoAviso.className = "bp-carregando-texto";

        botaoLiberar = document.createElement("button");
        botaoLiberar.type = "button";
        botaoLiberar.className = "bp-carregando-liberar";
        botaoLiberar.textContent = "Tentar de novo";
        botaoLiberar.hidden = true;
        // Cancela a espera do navegador e devolve os botões. A pessoa decide, sabendo do risco avisado no texto.
        botaoLiberar.addEventListener("click", function () {
            if (typeof window.stop === "function") window.stop();
            parar();
        });

        aviso.appendChild(giro);
        aviso.appendChild(textoAviso);
        aviso.appendChild(botaoLiberar);

        document.body.appendChild(camada);
        document.body.appendChild(aviso);
    }

    // ------------------------------------------------------------
    // Botão com a bolinha
    // ------------------------------------------------------------

    function marcarBotao(botao) {
        var estilo = window.getComputedStyle(botao);

        // Guarda a cor do texto para a bolinha ter o mesmo contraste que o texto do botão tinha.
        botao.style.setProperty("--bp-cor-giro", estilo.color);

        // A bolinha é posicionada dentro do botão; precisa de um ponto de referência.
        atual.posicaoOriginal = null;
        if (estilo.position === "static") {
            atual.posicaoOriginal = botao.style.position;
            botao.style.position = "relative";
        }

        var giro = document.createElement("span");
        giro.className = "bp-giro bp-giro-botao";
        giro.setAttribute("aria-hidden", "true");
        botao.appendChild(giro);
        atual.giro = giro;

        botao.classList.add("bp-botao-ocupado");
        botao.setAttribute("aria-disabled", "true");
        botao.setAttribute("aria-busy", "true");
    }

    function desmarcarBotao() {
        var botao = atual.botao;
        if (!botao) return;

        if (atual.giro && atual.giro.parentNode) atual.giro.parentNode.removeChild(atual.giro);

        botao.classList.remove("bp-botao-ocupado");
        botao.removeAttribute("aria-disabled");
        botao.removeAttribute("aria-busy");
        botao.style.removeProperty("--bp-cor-giro");
        if (atual.posicaoOriginal !== null) botao.style.position = atual.posicaoOriginal;
    }

    // ------------------------------------------------------------
    // Início e fim de um envio
    // ------------------------------------------------------------

    /**
     * Liga o indicador. Devolve false se já existe um envio em andamento.
     * opcoes: { botao, form, mensagem, bloquearTela }
     */
    function iniciar(opcoes) {
        if (atual) return false;

        opcoes = opcoes || {};
        garantirElementos();

        atual = {
            botao: opcoes.botao || null,
            form: opcoes.form || null,
            giro: null,
            posicaoOriginal: null,
            temporizadores: []
        };

        if (atual.form) atual.form.setAttribute("aria-busy", "true");
        if (atual.botao) marcarBotao(atual.botao);

        // A película começa invisível: só barra os cliques. O escurecido leve vem junto com o aviso.
        if (opcoes.bloquearTela !== false && camada) camada.hidden = false;

        var mensagem = opcoes.mensagem || "Enviando...";
        var meu = atual;

        meu.temporizadores.push(setTimeout(function () {
            if (atual !== meu || !aviso) return;
            textoAviso.textContent = mensagem;
            aviso.hidden = false;
            if (opcoes.bloquearTela !== false) camada.classList.add("bp-visivel");
        }, ATRASO_AVISO));

        meu.temporizadores.push(setTimeout(function () {
            if (atual === meu && aviso) textoAviso.textContent = TEXTO_LENTO;
        }, ATRASO_LENTO));

        meu.temporizadores.push(setTimeout(function () {
            if (atual !== meu || !aviso) return;
            textoAviso.textContent = TEXTO_TRAVADO;
            botaoLiberar.hidden = false;
        }, ATRASO_TRAVADO));

        return true;
    }

    // Desliga o indicador e devolve a tela ao normal.
    // Se receber um botão, só desliga quando o indicador em andamento for DESSE botão. Assim um código não desliga,
    // sem querer, o indicador que outro código ligou (ex.: um envio de formulário em andamento).
    function parar(dono) {
        if (!atual) return;
        if (dono && atual.botao !== dono) return;

        atual.temporizadores.forEach(clearTimeout);
        if (atual.form) atual.form.removeAttribute("aria-busy");
        desmarcarBotao();

        if (camada) {
            camada.hidden = true;
            camada.classList.remove("bp-visivel");
        }
        if (aviso) {
            aviso.hidden = true;
            textoAviso.textContent = "";
            botaoLiberar.hidden = true;
        }

        atual = null;
    }

    /**
     * Para código que usa fetch: mostra a bolinha no botão enquanto a tarefa roda e ignora cliques repetidos.
     *   alvo   -> o botão clicado (ou null)
     *   tarefa -> função que devolve uma Promise (ou a própria Promise)
     *   opcoes -> { mensagem, bloquearTela } (bloquearTela é false por padrão aqui)
     * Devolve a Promise da tarefa; se já havia algo em andamento, devolve undefined sem rodar a tarefa.
     */
    function executar(alvo, tarefa, opcoes) {
        opcoes = opcoes || {};
        if (atual) return Promise.resolve(undefined);

        var botao = alvo && alvo.nodeType === 1 ? alvo : null;
        iniciar({
            botao: botao,
            mensagem: opcoes.mensagem || "Processando...",
            bloquearTela: opcoes.bloquearTela === true
        });

        var promessa;
        try {
            promessa = Promise.resolve(typeof tarefa === "function" ? tarefa() : tarefa);
        } catch (erro) {
            parar();
            return Promise.reject(erro);
        }

        return promessa.then(
            function (valor) { parar(); return valor; },
            function (erro) { parar(); throw erro; }
        );
    }

    // ------------------------------------------------------------
    // Escolha do texto do aviso
    // ------------------------------------------------------------

    function temArquivoEscolhido(form) {
        var campos = form.querySelectorAll('input[type="file"]');
        for (var i = 0; i < campos.length; i++) {
            if (campos[i].files && campos[i].files.length) return true;
        }
        return false;
    }

    function mensagemPara(form, botao) {
        // 1) Texto definido à mão na página, se houver.
        var manual = (botao && botao.getAttribute("data-carregando")) || form.getAttribute("data-carregando");
        if (manual) return manual;

        // 2) Formulário de consulta/filtro (GET): só carrega uma tela.
        if ((form.method || "get").toLowerCase() === "get") return "Carregando...";

        // 3) Envio com foto: costuma ser o mais demorado com internet fraca.
        if (temArquivoEscolhido(form)) return "Enviando os dados e a foto...";

        // 4) Pelo texto do botão (ou título, nos botões que só têm ícone).
        if (botao) {
            var texto = [botao.textContent, botao.getAttribute("title"), botao.getAttribute("aria-label")]
                .join(" ").replace(/\s+/g, " ").toLowerCase();
            for (var i = 0; i < REGRAS_MENSAGEM.length; i++) {
                if (REGRAS_MENSAGEM[i][0].test(texto)) return REGRAS_MENSAGEM[i][1];
            }
        }

        return "Enviando...";
    }

    // Formulários que não devem receber o indicador.
    function deveIgnorar(form, botaoEnvio) {
        if (form.hasAttribute("data-sem-carregando")) return true;
        if ((form.method || "").toLowerCase() === "dialog") return true;

        // Envio para outra aba/janela não troca esta página: não há o que esperar aqui.
        var destino = (botaoEnvio && botaoEnvio.formTarget) || form.target;
        return !!destino && destino !== "_self";
    }

    // ------------------------------------------------------------
    // Faixa de conexão
    // ------------------------------------------------------------

    function mostrarFaixa(texto, classe, esconderEmMs) {
        if (!document.body) return;

        if (!faixa) {
            faixa = document.createElement("div");
            faixa.setAttribute("role", "alert");
            document.body.appendChild(faixa);
        }

        clearTimeout(temporizadorFaixa);
        faixa.className = "bp-faixa-conexao " + classe;
        faixa.textContent = texto;
        faixa.hidden = false;

        if (esconderEmMs) {
            temporizadorFaixa = setTimeout(function () { faixa.hidden = true; }, esconderEmMs);
        }
    }

    var TEXTO_SEM_INTERNET = "Você está sem internet. Seus dados continuam na tela; salve quando a conexão voltar.";

    window.addEventListener("offline", function () {
        mostrarFaixa(TEXTO_SEM_INTERNET, "bp-faixa-offline");
    });

    window.addEventListener("online", function () {
        mostrarFaixa("Conexão restabelecida.", "bp-faixa-online", 3500);
    });

    // ------------------------------------------------------------
    // Ligação com os formulários da página
    // ------------------------------------------------------------

    // Fase de "bolha" no window: roda DEPOIS dos códigos de cada formulário. Assim, se o confirm("Excluir?") foi
    // cancelado ou uma validação da página barrou o envio (preventDefault), este código nem chega a agir.
    window.addEventListener("submit", function (evento) {
        var form = evento.target;
        if (!form || form.nodeName !== "FORM") return;
        if (evento.defaultPrevented) return;

        var botaoEnvio = evento.submitter || form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        if (deveIgnorar(form, botaoEnvio)) return;

        // Já existe um envio em andamento: este segundo é descartado (clique duplo, Enter repetido, outro botão).
        if (atual) {
            evento.preventDefault();
            return;
        }

        // Sem internet, enviar só daria erro. Barra e avisa, sem perder o que foi digitado.
        if (navigator.onLine === false) {
            evento.preventDefault();
            mostrarFaixa(TEXTO_SEM_INTERNET, "bp-faixa-offline");
            return;
        }

        iniciar({
            form: form,
            botao: botaoEnvio && botaoEnvio.nodeName !== "INPUT" ? botaoEnvio : null,
            mensagem: mensagemPara(form, botaoEnvio)
        });

        // Segurança: se algum código registrado depois deste cancelar o envio, não deixa a tela travada.
        setTimeout(function () {
            if (evento.defaultPrevented) parar();
        }, 0);
    }, false);

    // form.submit() chamado por código (ex.: onchange="this.form.submit()" nos campos de data) NÃO dispara o evento
    // "submit". Aqui o método é envolvido para esses casos também mostrarem que a página está carregando.
    var submitOriginal = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (!deveIgnorar(this, null)) {
            iniciar({ form: this, mensagem: mensagemPara(this, null) });
        }
        return submitOriginal.apply(this, arguments);
    };

    // Voltar pelo botão do navegador pode trazer a página "congelada" com o indicador ligado. Aqui ele é desligado.
    window.addEventListener("pageshow", function (evento) {
        if (evento.persisted) parar();
    });

    // ------------------------------------------------------------
    // API pública
    // ------------------------------------------------------------
    window.BPCarregando = {
        iniciar: iniciar,
        parar: parar,
        executar: executar
    };
})();