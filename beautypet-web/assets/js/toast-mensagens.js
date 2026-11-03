/*
 * ARQUIVO: assets/js/toast-mensagens.js
 * [BP-TOAST] Arquivo novo. Carregado em todas as páginas pelo includes/topo.php.
 *
 * RESPONSABILIDADE (Heurística 1 de Nielsen: visibilidade do status do sistema):
 *   Transformar as mensagens de sucesso e de erro em "toast": uma caixinha flutuante que aparece, fica visível
 *   por exatamente 7 segundos e some sozinha com um fade-out suave.
 *
 * COMO FUNCIONA SEM MEXER NAS PÁGINAS E SEM MEXER NA LÓGICA:
 *   As páginas continuam escrevendo a mensagem do mesmo jeito, por exemplo:
 *       <div class="message success">Cadastro salvo!</div>
 *       <div class="message error" role="alert">Não foi possível salvar.</div>
 *   Quando a página termina de carregar, este arquivo procura essas mensagens e MOVE cada uma (o próprio elemento,
 *   com o mesmo texto e os mesmos atributos) para uma área flutuante na parte de baixo da tela.
 *   Cada mensagem é um toast independente, com o seu próprio relógio de 7 segundos.
 *   O PHP, as rotas e os formulários não sabem que isso existe.
 *
 * O QUE É TRANSFORMADO:
 *   .message.success / .message.error                 (páginas do sistema)
 *   .account-message.success / .account-message.error (troca de usuário e criação de usuário)
 *
 * O QUE NÃO É TRANSFORMADO (de propósito):
 *   .account-message.info (aviso fixo, não é resultado de uma ação) e mensagens dentro de um modal fechado.
 *
 * PARA CÓDIGO COM fetch (AJAX), SE UM DIA PRECISAR:
 *   BPToast.mostrar("success", "Salvo com sucesso!");
 *   BPToast.mostrar("error", "Não foi possível salvar.");
 *
 * Visual: assets/css/toast-mensagens.css
 */
(function () {
    "use strict";

    // Evita registrar tudo duas vezes se o arquivo for carregado em duplicidade.
    if (window.BPToast) return;

    // ---------- Tempos (em milissegundos) ----------
    // Quanto tempo a mensagem fica totalmente visível.
    var TEMPO_VISIVEL = 7000;
    // Duração do fade-out. Precisa ser igual ao "transition" do toast-mensagens.css (.45s).
    var TEMPO_SUMIR = 450;

    // Mensagens que viram toast.
    var SELETOR = ".message.success, .message.error, .account-message.success, .account-message.error";

    // Ícones em SVG dentro do próprio código: não dependem do Font Awesome nem da internet.
    var ICONE_SUCESSO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    var ICONE_ERRO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    var ICONE_FECHAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

    // Área fixa onde os toasts ficam empilhados. Criada só na primeira vez que é necessária.
    var area = null;

    function garantirArea() {
        if (area || !document.body) return;
        area = document.createElement("div");
        area.className = "bp-toast-area";
        document.body.appendChild(area);
    }

    // ------------------------------------------------------------
    // Transforma UMA mensagem em toast
    // ------------------------------------------------------------

    function transformar(mensagem, tipo) {
        garantirArea();
        if (!area) return;

        var erro = tipo === "error";

        // 1) O conteúdo original (texto, <strong>, etc.) vai para dentro de um <span>, sem ser alterado.
        var texto = document.createElement("span");
        texto.className = "bp-toast-texto";
        while (mensagem.firstChild) texto.appendChild(mensagem.firstChild);

        // 2) Ícone: check no sucesso, X no erro. É decorativo; quem informa o tipo ao leitor de tela é o role.
        var icone = document.createElement("span");
        icone.className = "bp-toast-icone";
        icone.setAttribute("aria-hidden", "true");
        icone.innerHTML = erro ? ICONE_ERRO : ICONE_SUCESSO;

        // 3) Botão para fechar antes dos 7 segundos (a pessoa continua no controle).
        var fechar = document.createElement("button");
        fechar.type = "button";
        fechar.className = "bp-toast-fechar";
        fechar.setAttribute("aria-label", "Fechar mensagem");
        fechar.innerHTML = ICONE_FECHAR;

        // 4) Barrinha que mostra o tempo restante.
        var barra = document.createElement("span");
        barra.className = "bp-toast-barra";
        barra.setAttribute("aria-hidden", "true");

        mensagem.appendChild(icone);
        mensagem.appendChild(texto);
        mensagem.appendChild(fechar);
        mensagem.appendChild(barra);

        mensagem.classList.add("bp-toast", erro ? "bp-toast-erro" : "bp-toast-sucesso");
        mensagem.style.setProperty("--bp-toast-tempo", TEMPO_VISIVEL + "ms");

        // Leitor de tela: erro interrompe e anuncia na hora; sucesso espera a vez. Respeita o role que a página já tinha.
        if (!mensagem.hasAttribute("role")) mensagem.setAttribute("role", erro ? "alert" : "status");

        // 5) Vai para a área flutuante (sai do topo da página e deixa de ocupar espaço lá).
        area.appendChild(mensagem);

        // 6) Relógio de 7 segundos, só desta mensagem. Depois: fade-out e remoção.
        var relogio = setTimeout(sair, TEMPO_VISIVEL);

        function sair() {
            clearTimeout(relogio);
            if (mensagem.classList.contains("bp-toast-saindo")) return;
            mensagem.classList.add("bp-toast-saindo");
            setTimeout(function () {
                if (mensagem.parentNode) mensagem.parentNode.removeChild(mensagem);
            }, TEMPO_SUMIR + 50);
        }

        fechar.addEventListener("click", sair);
    }

    // ------------------------------------------------------------
    // Procura as mensagens da página
    // ------------------------------------------------------------

    function varrer() {
        var lista = document.querySelectorAll(SELETOR);

        for (var i = 0; i < lista.length; i++) {
            var mensagem = lista[i];

            // Já virou toast.
            if (mensagem.classList.contains("bp-toast")) continue;

            // Mensagem dentro de um modal fechado (ex.: a troca de usuário): não está na tela, então não vira toast.
            var modal = mensagem.closest(".account-modal, .account-page-modal");
            if (modal && !modal.classList.contains("is-open")) continue;

            transformar(mensagem, mensagem.classList.contains("error") ? "error" : "success");
        }
    }

    // O arquivo é carregado no <head>: espera a página ser montada antes de procurar as mensagens.
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", varrer);
    } else {
        varrer();
    }

    // Voltar pelo botão do navegador pode trazer a página "congelada" com toasts pela metade. Aqui eles são limpos.
    window.addEventListener("pagehide", function () {
        if (area) area.innerHTML = "";
    });

    // ------------------------------------------------------------
    // API pública (para código com fetch/AJAX)
    // ------------------------------------------------------------

    function mostrar(tipo, texto) {
        var mensagem = document.createElement("div");
        var erro = tipo === "error" || tipo === "erro";
        mensagem.className = "message " + (erro ? "error" : "success");
        mensagem.textContent = texto;
        transformar(mensagem, erro ? "error" : "success");
    }

    window.BPToast = { mostrar: mostrar };
})();
