/*
 * ARQUIVO: assets/js/preferencias.js
 * [BP-ALTERAÇÃO #4] Arquivo novo. Carregado no <head> de todas as páginas pelo includes/topo.php
 * (no <head> para o tema escuro já aparecer sem "piscar" branco ao abrir a página).
 *
 * RESPONSABILIDADE: guardar e aplicar as preferências de aparência e acessibilidade
 * escolhidas em pages/configuracoes.php.
 *
 * ONDE FICA GUARDADO: no navegador (localStorage, chave "beautypet.preferencias").
 *   -> Vale para este computador/navegador. Não mexe no banco de dados.
 *   -> Para levar a preferência junto com o usuário, no futuro dá para salvar o mesmo objeto numa tabela.
 *
 * COMO FUNCIONA: cada preferência vira um atributo na tag <html>, e o assets/css/preferencias.css
 * aplica o visual. Ex.: tema escuro -> <html data-tema="escuro">.
 *
 *   tema              'claro' | 'escuro' | 'sistema'  -> data-tema="claro|escuro"
 *   fonte             'normal' | 'grande' | 'muito-grande' -> data-fonte
 *   contraste         true/false -> data-contraste="alto"
 *   reduzirAnimacoes  true/false -> data-reduzir-animacoes="1"
 *   focoReforcado     true/false -> data-foco-reforcado="1"
 *   alertaVisual      true/false -> mostra um aviso na tela quando chega solicitação do app (bom para surdos)
 *   vlibras           true/false -> carrega o tradutor de Libras do governo (vlibras.gov.br)
 */
(function () {
    'use strict';

    const CHAVE = 'beautypet.preferencias';

    const PADRAO = {
        tema: 'claro',
        fonte: 'normal',
        contraste: false,
        reduzirAnimacoes: false,
        focoReforcado: false,
        alertaVisual: false,
        vlibras: false,
    };

    function ler() {
        try {
            const salvo = JSON.parse(localStorage.getItem(CHAVE) || '{}');
            return { ...PADRAO, ...(salvo && typeof salvo === 'object' ? salvo : {}) };
        } catch (erro) {
            return { ...PADRAO }; // navegador sem localStorage (ex.: janela anônima bloqueada)
        }
    }

    function salvar(preferencias) {
        const completas = { ...PADRAO, ...preferencias };
        try {
            localStorage.setItem(CHAVE, JSON.stringify(completas));
        } catch (erro) {
            // Sem localStorage: aplica só nesta página.
        }
        aplicar(completas);
        return completas;
    }

    const sistemaEscuro = () => window.matchMedia?.('(prefers-color-scheme: dark)').matches;

    // Traduz as preferências em atributos da tag <html>.
    function aplicar(preferencias = ler()) {
        const html = document.documentElement;
        const tema = preferencias.tema === 'sistema'
            ? (sistemaEscuro() ? 'escuro' : 'claro')
            : (preferencias.tema === 'escuro' ? 'escuro' : 'claro');

        html.dataset.tema = tema;
        alternarAtributo(html, 'fonte', preferencias.fonte !== 'normal' ? preferencias.fonte : null);
        alternarAtributo(html, 'contraste', preferencias.contraste ? 'alto' : null);
        alternarAtributo(html, 'reduzirAnimacoes', preferencias.reduzirAnimacoes ? '1' : null);
        alternarAtributo(html, 'focoReforcado', preferencias.focoReforcado ? '1' : null);

        if (preferencias.vlibras) carregarVLibras();
    }

    function alternarAtributo(elemento, nome, valor) {
        if (valor) elemento.dataset[nome] = valor;
        else delete elemento.dataset[nome];
    }

    // Tradutor de Libras oficial do governo federal (gratuito). Só é carregado se a opção estiver ligada.
    let vlibrasCarregado = false;
    function carregarVLibras() {
        if (vlibrasCarregado) return;
        vlibrasCarregado = true;

        const iniciar = () => {
            const caixa = document.createElement('div');
            caixa.setAttribute('vw', '');
            caixa.className = 'enabled';
            caixa.innerHTML = '<div vw-access-button class="active"></div>'
                + '<div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div>';
            document.body.appendChild(caixa);

            const script = document.createElement('script');
            script.src = 'https://vlibras.gov.br/app/vlibras-plugin.js';
            script.onload = () => {
                try {
                    new window.VLibras.Widget('https://vlibras.gov.br/app');
                    // O plugin se inicia no evento "load"; se a página já carregou, dispara de novo.
                    if (document.readyState === 'complete') window.dispatchEvent(new Event('load'));
                } catch (erro) {
                    // Sem internet: o restante do sistema continua normal.
                }
            };
            document.body.appendChild(script);
        };

        if (document.body) iniciar();
        else document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    }

    // Aviso na tela quando chega uma solicitação do app (o includes/rodape.php dispara o evento).
    function mostrarAvisoVisual(total) {
        if (!ler().alertaVisual) return;

        const aviso = document.createElement('a');
        aviso.className = 'bp-aviso-flutuante bp-aviso-solicitacao';
        aviso.setAttribute('role', 'alert');
        aviso.href = document.querySelector('#requestNotificationBell')?.href || '#';
        aviso.innerHTML = '<i class="fa-solid fa-bell" aria-hidden="true"></i><span></span>';
        aviso.querySelector('span').textContent = total === 1
            ? 'Nova solicitação de agendamento pelo app.'
            : `${total} solicitações de agendamento pendentes.`;
        document.body.appendChild(aviso);
        setTimeout(() => aviso.remove(), 9000);
    }

    document.addEventListener('beautypet:nova-solicitacao', (evento) => {
        mostrarAvisoVisual(Number(evento.detail?.total || 1));
    });

    // Se o tema for "seguir o sistema", acompanha a troca de tema do Windows/celular em tempo real.
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
        if (ler().tema === 'sistema') aplicar();
    });

    aplicar();

    // Usado pela página pages/configuracoes.php.
    window.BeautyPetPreferencias = { ler, salvar, aplicar, PADRAO };
})();
