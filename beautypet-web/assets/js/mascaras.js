/*
 * ARQUIVO: assets/js/mascaras.js
 * [BP-ALTERAÇÃO #6] Arquivo novo. Carregado em todas as páginas pelo includes/rodape.php.
 *
 * RESPONSABILIDADE: formatar campos enquanto a pessoa digita.
 * Para usar, basta colocar o atributo data-mascara no <input>:
 *
 *   data-mascara="cpf"       -> 000.000.000-00 (e avisa se os dígitos verificadores estiverem errados)
 *   data-mascara="telefone"  -> (00) 00000-0000 ou (00) 0000-0000
 *   data-mascara="cep"       -> 00000-000 (e preenche rua/bairro/cidade/UF pelo ViaCEP, se estiverem vazios)
 *   data-mascara="dinheiro"  -> 1.234,56 digitando da direita para a esquerda (como no app do banco)
 *   data-mascara="decimal"   -> número com vírgula, ex.: peso 12,5
 *
 * Campos dentro de .money-input (os que já mostram "R$" ao lado) recebem a máscara de dinheiro sozinhos.
 *
 * COMO FUNCIONAM "dinheiro" E "decimal" (importante para o fechamento diário):
 *   O <input type="number"> original CONTINUA na página, escondido, sempre com o valor no formato "12.34".
 *   Na frente dele é criado um campo "gêmeo" (texto) que mostra "12,34". Os dois ficam sincronizados:
 *     - a pessoa digita no gêmeo   -> o original recebe "12.34" e dispara o evento "input";
 *     - o JavaScript da página faz original.value = "5" -> o gêmeo mostra "5,00";
 *     - disabled / readonly / hidden / required do original são copiados para o gêmeo;
 *     - form.reset() também ressincroniza.
 *   Assim, o PHP e os cálculos já existentes (que leem input.value) continuam funcionando sem nenhuma mudança.
 *
 * CPF, telefone e CEP são formatados no próprio campo: o PHP já guarda só os números (somenteNumeros()).
 */
(function () {
    'use strict';

    // Seletor dos campos que recebem máscara. Para incluir um campo novo, prefira o atributo data-mascara.
    const SELETOR_MASCARAS = '[data-mascara], .money-input > input[type="number"]';

    // Lê/grava o "value" nativo do input, sem passar pela interceptação criada para os campos gêmeos.
    const valorNativo = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    const somenteDigitos = (texto) => String(texto ?? '').replace(/\D+/g, '');

    // ------------------------------------------------------------------
    // Formatações de texto (CPF, telefone, CEP)
    // ------------------------------------------------------------------
    const formatarCpf = (texto) => {
        const d = somenteDigitos(texto).slice(0, 11);
        return d
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
    };

    const formatarTelefone = (texto) => {
        const d = somenteDigitos(texto).slice(0, 11);
        if (d.length <= 2) return d.length ? `(${d}` : '';
        if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
        if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`; // fixo
        return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`; // celular
    };

    const formatarCep = (texto) => {
        const d = somenteDigitos(texto).slice(0, 8);
        return d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
    };

    // Mesma regra do cpfValido() do PHP (pages/cadastro-tutor.php).
    const cpfValido = (texto) => {
        const cpf = somenteDigitos(texto);
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        for (let posicao = 9; posicao <= 10; posicao++) {
            let soma = 0;
            for (let i = 0; i < posicao; i++) soma += Number(cpf[i]) * (posicao + 1 - i);
            const digito = ((10 * soma) % 11) % 10;
            if (Number(cpf[posicao]) !== digito) return false;
        }
        return true;
    };

    const FORMATADORES_TEXTO = { cpf: formatarCpf, telefone: formatarTelefone, cep: formatarCep };

    function aplicarMascaraTexto(input, tipo) {
        const formatar = FORMATADORES_TEXTO[tipo];
        input.inputMode = 'numeric';
        input.value = formatar(input.value); // formata também o valor que veio do banco (edição)

        input.addEventListener('input', () => {
            input.value = formatar(input.value);
            input.setCustomValidity('');
            if (tipo === 'cep' && somenteDigitos(input.value).length === 8) buscarCep(input);
        });

        if (tipo === 'cpf') {
            input.addEventListener('blur', () => {
                const preenchido = somenteDigitos(input.value).length > 0;
                input.setCustomValidity(preenchido && !cpfValido(input.value)
                    ? 'CPF inválido. Confira os números digitados.'
                    : '');
                if (input.validationMessage) input.reportValidity();
            });
        }
    }

    // Preenche o endereço pelo CEP (serviço gratuito ViaCEP). Só preenche campos que estão vazios.
    async function buscarCep(inputCep) {
        const cep = somenteDigitos(inputCep.value);
        const form = inputCep.form || document;
        const campo = (nome) => form.querySelector(`[name="${nome}"]`);

        try {
            const resposta = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            if (!resposta.ok) return;
            const dados = await resposta.json();

            if (dados.erro) {
                inputCep.setCustomValidity('CEP não encontrado.');
                inputCep.reportValidity();
                return;
            }

            const preencher = (nome, valor) => {
                const alvo = campo(nome);
                if (alvo && !alvo.value && valor) alvo.value = valor;
            };
            preencher('logradouro', dados.logradouro);
            preencher('bairro', dados.bairro);
            preencher('cidade', dados.localidade);
            preencher('estado', dados.uf);
        } catch (erro) {
            // Sem internet ou ViaCEP fora do ar: a pessoa digita o endereço normalmente.
        }
    }

    // ------------------------------------------------------------------
    // Campos numéricos com gêmeo (dinheiro e decimal)
    // ------------------------------------------------------------------
    const formatoBR = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // "12.34" (formato do original) -> "12,34" (formato mostrado no gêmeo)
    function numeroParaTexto(valor, tipo) {
        const texto = String(valor ?? '').trim();
        if (texto === '') return '';
        const numero = Number(texto);
        if (!Number.isFinite(numero)) return '';
        return tipo === 'dinheiro' ? formatoBR.format(numero) : texto.replace('.', ',');
    }

    // Texto digitado no gêmeo -> { mostrar: "1.234,56", original: "1234.56" }
    function lerDigitado(texto, tipo) {
        if (tipo === 'dinheiro') {
            // Da direita para a esquerda: os dígitos digitados são os centavos.
            const digitos = somenteDigitos(texto).replace(/^0+/, '').slice(0, 12);
            if (digitos === '') return { mostrar: '', original: '' };
            const numero = Number(digitos) / 100;
            return { mostrar: formatoBR.format(numero), original: numero.toFixed(2) };
        }

        // decimal: aceita números e uma vírgula (ou ponto), com até 2 casas.
        let limpo = String(texto).replace(/\./g, ',').replace(/[^\d,]/g, '');
        const [inteiro, ...resto] = limpo.split(',');
        limpo = resto.length ? `${inteiro},${resto.join('').slice(0, 2)}` : inteiro;
        const original = limpo === '' || limpo === ',' ? '' : limpo.replace(',', '.').replace(/^\./, '0.');
        return { mostrar: limpo, original };
    }

    function criarGemeo(original, tipo) {
        const gemeo = document.createElement('input');
        gemeo.type = 'text';
        gemeo.inputMode = 'decimal';
        gemeo.autocomplete = 'off';
        // Não copia as classes do original: o JavaScript da página pode procurar campos por classe.
        gemeo.className = 'bp-mascara-gemeo';
        gemeo.placeholder = original.placeholder || (tipo === 'dinheiro' ? '0,00' : '');
        gemeo.setAttribute('data-mascara-gemeo', tipo);
        if (original.getAttribute('aria-label')) gemeo.setAttribute('aria-label', original.getAttribute('aria-label'));

        // Leitores de tela e o clique no <label for="..."> passam a usar o gêmeo.
        if (original.id) {
            gemeo.id = `${original.id}__mascara`;
            document.querySelectorAll(`label[for="${CSS.escape(original.id)}"]`).forEach((label) => {
                label.htmlFor = gemeo.id;
            });
        }

        const mostrarValorDoOriginal = () => {
            gemeo.value = numeroParaTexto(valorNativo.get.call(original), tipo);
        };

        const copiarEstado = () => {
            gemeo.disabled = original.disabled;
            gemeo.readOnly = original.readOnly;
            gemeo.required = original.required;
            gemeo.hidden = original.hidden;
        };

        // Pessoa digitou no gêmeo -> atualiza o original e avisa o JavaScript da página.
        gemeo.addEventListener('input', () => {
            const lido = lerDigitado(gemeo.value, tipo);
            gemeo.value = lido.mostrar;
            valorNativo.set.call(original, lido.original);
            original.dispatchEvent(new Event('input', { bubbles: true }));
        });
        gemeo.addEventListener('change', () => original.dispatchEvent(new Event('change', { bubbles: true })));
        gemeo.addEventListener('blur', () => {
            // Dinheiro vazio volta para 0,00 quando o original começou com valor (ex.: value="0.00").
            if (tipo === 'dinheiro' && gemeo.value === '' && original.defaultValue !== '') {
                valorNativo.set.call(original, '0.00');
                mostrarValorDoOriginal();
                original.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

        // JavaScript da página escreveu original.value = ... -> o gêmeo mostra o novo valor.
        Object.defineProperty(original, 'value', {
            configurable: true,
            get() { return valorNativo.get.call(this); },
            set(novo) { valorNativo.set.call(this, novo); mostrarValorDoOriginal(); },
        });
        // original.focus() leva o foco para o campo visível.
        original.focus = (opcoes) => gemeo.focus(opcoes);

        new MutationObserver(copiarEstado).observe(original, {
            attributes: true,
            attributeFilter: ['disabled', 'readonly', 'required', 'hidden'],
        });

        original.form?.addEventListener('reset', () => setTimeout(mostrarValorDoOriginal, 0));

        original.classList.add('bp-mascara-original');
        original.tabIndex = -1;
        original.setAttribute('aria-hidden', 'true');
        original.insertAdjacentElement('beforebegin', gemeo);

        copiarEstado();
        mostrarValorDoOriginal();
    }

    // ------------------------------------------------------------------
    // Inicialização (também para campos criados depois, ex.: linhas novas do fechamento diário)
    // ------------------------------------------------------------------
    function aplicar(input) {
        if (!(input instanceof HTMLInputElement) || input.dataset.mascaraAplicada) return;
        if (input.matches('[data-mascara-gemeo]')) return;

        const tipo = input.dataset.mascara || 'dinheiro';
        input.dataset.mascaraAplicada = '1';

        if (FORMATADORES_TEXTO[tipo]) {
            aplicarMascaraTexto(input, tipo);
        } else if (tipo === 'dinheiro' || tipo === 'decimal') {
            criarGemeo(input, tipo);
        }
    }

    function aplicarEm(raiz) {
        if (raiz.matches?.(SELETOR_MASCARAS)) aplicar(raiz);
        raiz.querySelectorAll?.(SELETOR_MASCARAS).forEach(aplicar);
    }

    aplicarEm(document);

    new MutationObserver((mudancas) => {
        mudancas.forEach((mudanca) => mudanca.addedNodes.forEach((no) => {
            if (no.nodeType === Node.ELEMENT_NODE) aplicarEm(no);
        }));
    }).observe(document.body, { childList: true, subtree: true });

    // Disponível para testes no console: BeautyPetMascaras.cpfValido('...')
    window.BeautyPetMascaras = { aplicarEm, cpfValido, formatarCpf, formatarTelefone, formatarCep };
})();
