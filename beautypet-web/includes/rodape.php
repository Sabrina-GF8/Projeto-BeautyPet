</main>

    </div>

</div>


<!-- Foto do pet nos resultados da busca global. -->
<style>
.global-search-kind{overflow:hidden}
.global-search-kind img{width:100%;height:100%;display:block;object-fit:cover;border-radius:inherit}
</style>

<!-- [BP-ATALHOS] Estilo da dica que mostra o atalho quando o mouse para sobre um item do menu lateral. -->
<style>
/* Caixinha escura flutuante; fica "position:fixed" para não ser cortada pelo menu lateral. */
.bp-atalho-dica{
    position:fixed;
    left:0;
    top:0;
    z-index:10000;
    display:flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:8px;
    border:1px solid rgba(255,255,255,.18);
    background:#1f2937;
    color:#fff;
    font:500 13px/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;
    box-shadow:0 4px 14px rgba(0,0,0,.25);
    white-space:nowrap;
    /* Escondida por padrão; a classe "is-visible" (colocada pelo JS) a revela com um leve fade. */
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:opacity .15s ease;
}
.bp-atalho-dica.is-visible{opacity:1;visibility:visible}
/* Aparência de "tecla" para o texto Alt + letra. */
.bp-atalho-dica kbd{
    padding:2px 6px;
    border-radius:5px;
    background:rgba(255,255,255,.18);
    font:600 12px/1 ui-monospace,SFMono-Regular,Consolas,monospace;
}
/* Respeita quem desativa animações no sistema (acessibilidade). */
@media (prefers-reduced-motion:reduce){.bp-atalho-dica{transition:none}}
</style>

<!-- Script responsável pelas interações desta parte da página no navegador. -->

<script>

// Obtém a referência do elemento "profileButton" para ler ou alterar seu conteúdo/comportamento.

const profileButton = document.querySelector("#profileButton");

// Obtém a referência do elemento "switchUserModal" para ler ou alterar seu conteúdo/comportamento.

const switchUserModal = document.querySelector("#switchUserModal");

// Obtém a referência do elemento "switchUserClose" para ler ou alterar seu conteúdo/comportamento.

const switchUserClose = document.querySelector("#switchUserClose");


// Define uma função JavaScript reutilizável para executar esta etapa da interface.

function abrirTrocaUsuario() {

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.

    if (!switchUserModal) return;


    switchUserModal.classList.add("is-open");

    switchUserModal.setAttribute("aria-hidden", "false");

    document.body.classList.add("account-modal-open");


    // Armazena em "email" um valor usado pela lógica executada no navegador.

    const email = switchUserModal.querySelector("#emailTroca");

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.

    if (email) {

        setTimeout(() => email.focus(), 40);

    }

}


// Define uma função JavaScript reutilizável para executar esta etapa da interface.

function fecharTrocaUsuario() {

    // Verifica esta condição antes de alterar a interface ou continuar o processamento.

    if (!switchUserModal) return;


    switchUserModal.classList.remove("is-open");

    switchUserModal.setAttribute("aria-hidden", "true");

    document.body.classList.remove("account-modal-open");

}


// Verifica esta condição antes de alterar a interface ou continuar o processamento.

if (profileButton && switchUserModal) {

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    profileButton.addEventListener("click", abrirTrocaUsuario);


    // Verifica esta condição antes de alterar a interface ou continuar o processamento.

    if (switchUserClose) {

        // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

        switchUserClose.addEventListener("click", fecharTrocaUsuario);

    }


    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    switchUserModal.addEventListener("click", event => {

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (event.target === switchUserModal) {

            fecharTrocaUsuario();

        }

    });


    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    document.addEventListener("keydown", event => {

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (event.key === "Escape" && switchUserModal.classList.contains("is-open")) {

            fecharTrocaUsuario();

        }

    });


    // Verifica esta condição antes de alterar a interface ou continuar o processamento.

    if (switchUserModal.classList.contains("is-open")) {

        document.body.classList.add("account-modal-open");

    }

}


// Obtém a referência do elemento "photoInput" para ler ou alterar seu conteúdo/comportamento.

const photoInput = document.querySelector("#foto");

// Obtém a referência do elemento "photoPreview" para ler ou alterar seu conteúdo/comportamento.

const photoPreview = document.querySelector("#photoPreview");


// Verifica esta condição antes de alterar a interface ou continuar o processamento.

if (photoInput && photoPreview) {

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    photoInput.addEventListener("change", () => {

        // Armazena em "file" um valor usado pela lógica executada no navegador.

        const file = photoInput.files[0];


        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (!file) return;


        // Armazena em "reader" um valor usado pela lógica executada no navegador.

        const reader = new FileReader();


        reader.onload = event => {

            photoPreview.innerHTML = `<img src="${event.target.result}" alt="Prévia da foto do pet">`;

        };


        reader.readAsDataURL(file);

    });

}


// Percorre os elementos encontrados e aplica a mesma regra a cada um deles.

document.querySelectorAll("[data-password-target]").forEach(button => {

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    button.addEventListener("click", () => {

        // Armazena em "targetId" um valor usado pela lógica executada no navegador.

        const targetId = button.dataset.passwordTarget;

        // Obtém a referência do elemento "input" para ler ou alterar seu conteúdo/comportamento.

        const input = document.getElementById(targetId);

        // Armazena em "icon" um valor usado pela lógica executada no navegador.

        const icon = button.querySelector("i");


        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (!input) return;


        // Armazena em "mostrar" um valor usado pela lógica executada no navegador.

        const mostrar = input.type === "password";

        input.type = mostrar ? "text" : "password";


        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (icon) {

            icon.className = mostrar ? "fa-regular fa-eye-slash" : "fa-regular fa-eye";

        }

    });

});


// Busca reutilizável de pets e tutores.

const delaySearch = (callback, delay = 260) => {

    let timer;

    return (...args) => {

        clearTimeout(timer);

        timer = setTimeout(() => callback(...args), delay);

    };

};


// Percorre os elementos encontrados e aplica a mesma regra a cada um deles.

document.querySelectorAll("[data-search-picker]").forEach(picker => {

    // Armazena em "type" um valor usado pela lógica executada no navegador.

    const type = picker.dataset.searchPicker;

    // Armazena em "input" um valor usado pela lógica executada no navegador.

    const input = picker.querySelector("[data-search-input]");

    // Armazena em "valueInput" um valor usado pela lógica executada no navegador.

    const valueInput = picker.querySelector("[data-search-value]");

    // Armazena em "results" um valor usado pela lógica executada no navegador.

    const results = picker.querySelector("[data-search-results]");

    // Armazena em "required" um valor usado pela lógica executada no navegador.

    const required = picker.dataset.required === "1";


    // Verifica esta condição antes de alterar a interface ou continuar o processamento.

    if (!input || !valueInput || !results || !type) return;


    // Armazena em "closeResults" um valor usado pela lógica executada no navegador.

    const closeResults = () => {

        results.hidden = true;

        results.innerHTML = "";

        picker.classList.remove("is-open");

    };


    // Armazena em "selectItem" um valor usado pela lógica executada no navegador.

    const selectItem = item => {

        valueInput.value = item.id;

        input.value = item.label;

        input.dataset.selectedLabel = item.label;

        input.setCustomValidity("");

        picker.dispatchEvent(new CustomEvent("search-picker:selected", {

            bubbles: true,

            detail: item

        }));

        closeResults();

    };


    // Armazena em "renderResults" um valor usado pela lógica executada no navegador.

    const renderResults = items => {

        results.innerHTML = "";


        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (!items.length) {

            // Armazena em "empty" um valor usado pela lógica executada no navegador.

            const empty = document.createElement("div");

            empty.className = "search-picker-empty";

            empty.textContent = "Nenhum resultado encontrado.";

            results.appendChild(empty);

        } else {

            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.

            items.forEach(item => {

                // Armazena em "button" um valor usado pela lógica executada no navegador.

                const button = document.createElement("button");

                button.type = "button";

                button.className = "search-picker-option";


                // Armazena em "main" um valor usado pela lógica executada no navegador.

                const main = document.createElement("strong");

                main.textContent = item.label;

                button.appendChild(main);


                // Verifica esta condição antes de alterar a interface ou continuar o processamento.

                if (item.detail) {

                    // Armazena em "detail" um valor usado pela lógica executada no navegador.

                    const detail = document.createElement("small");

                    detail.textContent = item.detail;

                    button.appendChild(detail);

                }


                // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

                button.addEventListener("mousedown", event => event.preventDefault());

                // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

                button.addEventListener("click", () => selectItem(item));

                results.appendChild(button);

            });

        }


        results.hidden = false;

        picker.classList.add("is-open");

    };


    // Armazena em "search" um valor usado pela lógica executada no navegador.

    const search = delaySearch(async () => {

        // Armazena em "q" um valor usado pela lógica executada no navegador.

        const q = input.value.trim();


        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (q.length < 1) {

            closeResults();

            return;

        }


        results.hidden = false;

        results.innerHTML = '<div class="search-picker-loading"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';

        picker.classList.add("is-open");


        try {

            // Armazena em "response" um valor usado pela lógica executada no navegador.

            // [BP-ALTERAÇÃO #5] Caminho absoluto: o caminho relativo dava 404 no Dashboard (index.php fica fora de pages/).

            const response = await fetch(`<?= BASE_URL ?>/pages/busca-registros.php?tipo=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`, {

                headers: {"X-Requested-With": "XMLHttpRequest"}

            });


            // Verifica esta condição antes de alterar a interface ou continuar o processamento.

            if (!response.ok) throw new Error("Falha na busca");

            renderResults(await response.json());

        } catch (error) {

            results.innerHTML = '<div class="search-picker-empty">Não foi possível buscar agora.</div>';

        }

    });


    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    input.addEventListener("input", () => {

        // Armazena em "selectedLabel" um valor usado pela lógica executada no navegador.

        const selectedLabel = input.dataset.selectedLabel || "";

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (input.value !== selectedLabel) valueInput.value = "";

        input.setCustomValidity("");

        search();

    });


    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    input.addEventListener("focus", () => {

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (input.value.trim() && !valueInput.value) search();

    });


    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    input.addEventListener("blur", () => setTimeout(closeResults, 130));


    // Armazena em "form" um valor usado pela lógica executada no navegador.

    const form = picker.closest("form");

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    form?.addEventListener("submit", event => {

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (required && !valueInput.value) {

            event.preventDefault();

            input.setCustomValidity(`Selecione um ${type === "pet" ? "pet" : "tutor"} nos resultados da busca.`);

            input.reportValidity();

            input.focus();

        }

    });

});


// Busca geral do cabeçalho: abre a ficha do pet ou tutor escolhido.

const globalSearch = document.querySelector("[data-global-search]");

// Verifica esta condição antes de alterar a interface ou continuar o processamento.

if (globalSearch) {

    // Armazena em "input" um valor usado pela lógica executada no navegador.

    const input = globalSearch.querySelector("#globalSearchInput");

    // Armazena em "results" um valor usado pela lógica executada no navegador.

    const results = globalSearch.querySelector("#globalSearchResults");


    // Armazena em "closeGlobal" um valor usado pela lógica executada no navegador.

    const closeGlobal = () => {

        results.hidden = true;

        results.innerHTML = "";

        globalSearch.classList.remove("is-open");

    };


    // Armazena em "runGlobalSearch" um valor usado pela lógica executada no navegador.

    const runGlobalSearch = delaySearch(async () => {

        // Armazena em "q" um valor usado pela lógica executada no navegador.

        const q = input.value.trim();

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (!q) {

            closeGlobal();

            return;

        }


        results.hidden = false;

        results.innerHTML = '<div class="search-picker-loading"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';

        globalSearch.classList.add("is-open");


        try {

            // Armazena em "response" um valor usado pela lógica executada no navegador.

            // [BP-ALTERAÇÃO #5] Caminho absoluto: o caminho relativo dava 404 no Dashboard (index.php fica fora de pages/).

            const response = await fetch(
                `<?= BASE_URL ?>/pages/busca-registros.php?tipo=geral&q=${encodeURIComponent(q)}`,
                {
                    headers: {"X-Requested-With": "XMLHttpRequest"}
                }
            );

            // Verifica esta condição antes de alterar a interface ou continuar o processamento.

            if (!response.ok) throw new Error("Falha na busca");

            // Armazena em "items" um valor usado pela lógica executada no navegador.

            const items = await response.json();

            results.innerHTML = "";


            // Verifica esta condição antes de alterar a interface ou continuar o processamento.

            if (!items.length) {

                results.innerHTML = '<div class="search-picker-empty">Nenhum pet ou tutor encontrado.</div>';

                return;

            }


            // Percorre os elementos encontrados e aplica a mesma regra a cada um deles.

            items.forEach(item => {

                // Armazena em "link" um valor usado pela lógica executada no navegador.

                const link = document.createElement("a");

                const caminhoItem = String(item.url || "").replace(/^\/+/, "");
                const destino = caminhoItem.startsWith("http://") || caminhoItem.startsWith("https://")
                    ? caminhoItem
                    : `<?= BASE_URL ?>/pages/${caminhoItem}`;

                link.href = destino;

                link.className = "global-search-option";

                link.innerHTML = `<span class="global-search-kind"></span><span><strong></strong><small></small></span>`;

                const visual = link.querySelector(".global-search-kind");

                if (item.kind === "pet" && item.foto_url) {
                    const foto = document.createElement("img");
                    foto.src = item.foto_url;
                    foto.alt = `Foto de ${item.label}`;
                    foto.loading = "lazy";

                    foto.addEventListener("error", () => {
                        visual.innerHTML = '<i class="fa-solid fa-paw"></i>';
                    });

                    visual.appendChild(foto);
                } else {
                    visual.innerHTML = `<i class="fa-solid ${item.kind === "pet" ? "fa-paw" : "fa-user"}"></i>`;
                }

                link.querySelector("strong").textContent = item.label;

                link.querySelector("small").textContent = item.detail || "";

                // Mantém o resultado clicável mesmo quando o campo perde o foco.
                link.addEventListener("mousedown", event => {
                    event.preventDefault();
                });

                link.addEventListener("click", event => {
                    event.preventDefault();
                    window.location.assign(destino);
                });

                results.appendChild(link);

            });

        } catch (error) {

            results.innerHTML = '<div class="search-picker-empty">Não foi possível buscar agora.</div>';

        }

    });


    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    input?.addEventListener("input", runGlobalSearch);

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    input?.addEventListener("focus", () => {

        // Verifica esta condição antes de alterar a interface ou continuar o processamento.

        if (input.value.trim()) runGlobalSearch();

    });

    // Registra um evento para executar a lógica quando o usuário interagir com este elemento.

    input?.addEventListener("blur", () => {
        setTimeout(() => {
            if (!globalSearch.contains(document.activeElement)) {
                closeGlobal();
            }
        }, 180);
    });

}

// Busca instantânea das páginas "Fichas dos pets" e "Fichas dos tutores".
// Usa o mesmo endpoint da busca geral, mantendo o formulário como fallback ao pressionar Enter.
document.querySelectorAll("[data-records-live-search]").forEach(searchBox => {
    const type = searchBox.dataset.recordsLiveSearch;
    const input = searchBox.querySelector("[data-records-live-input]");
    const results = searchBox.querySelector("[data-records-live-results]");

    if (!type || !input || !results) return;

    let ultimaBusca = 0;

    const closeRecordsResults = () => {
        results.hidden = true;
        results.innerHTML = "";
        searchBox.classList.remove("is-open");
    };

    const destinoDoItem = item => {
        const caminhoItem = String(
            item.url || (type === "pet"
                ? `ficha-pet.php?id=${item.id}`
                : `ficha-tutor.php?id=${item.id}`)
        ).replace(/^\/+/, "");

        return caminhoItem.startsWith("http://") || caminhoItem.startsWith("https://")
            ? caminhoItem
            : `<?= BASE_URL ?>/pages/${caminhoItem}`;
    };

    const renderRecordsResults = items => {
        results.innerHTML = "";

        if (!items.length) {
            results.innerHTML = '<div class="records-live-empty">Nenhum resultado encontrado.</div>';
            results.hidden = false;
            searchBox.classList.add("is-open");
            return;
        }

        items.forEach(item => {
            const link = document.createElement("a");
            const destino = destinoDoItem(item);

            link.href = destino;
            link.className = "records-live-option";
            link.innerHTML = '<span class="records-live-visual"></span><span class="records-live-copy"><strong></strong><small></small></span>';

            const visual = link.querySelector(".records-live-visual");
            const nome = String(item.name || item.label || "");
            const detalhe = String(item.detail || "");

            if (type === "pet" && item.foto_url) {
                const foto = document.createElement("img");
                foto.src = item.foto_url;
                foto.alt = `Foto de ${nome}`;
                foto.loading = "lazy";
                foto.addEventListener("error", () => {
                    visual.innerHTML = '<i class="fa-solid fa-paw"></i>';
                });
                visual.appendChild(foto);
            } else {
                visual.innerHTML = `<i class="fa-solid ${type === "pet" ? "fa-paw" : "fa-user"}"></i>`;
            }

            link.querySelector("strong").textContent = nome;
            link.querySelector("small").textContent = detalhe;

            // Evita o blur do campo fechar a lista antes do clique ser processado.
            link.addEventListener("mousedown", event => event.preventDefault());
            link.addEventListener("click", event => {
                event.preventDefault();
                window.location.assign(destino);
            });

            results.appendChild(link);
        });

        results.hidden = false;
        searchBox.classList.add("is-open");
    };

    const runRecordsSearch = delaySearch(async () => {
        const q = input.value.trim();

        if (!q) {
            closeRecordsResults();
            return;
        }

        const buscaAtual = ++ultimaBusca;
        results.hidden = false;
        results.innerHTML = '<div class="records-live-loading"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
        searchBox.classList.add("is-open");

        try {
            const response = await fetch(
                `<?= BASE_URL ?>/pages/busca-registros.php?tipo=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`,
                {
                    headers: {"X-Requested-With": "XMLHttpRequest"}
                }
            );

            if (!response.ok) throw new Error("Falha na busca");

            const items = await response.json();

            // Ignora respostas antigas caso a pessoa continue digitando muito rápido.
            if (buscaAtual !== ultimaBusca) return;

            renderRecordsResults(Array.isArray(items) ? items : []);
        } catch (error) {
            if (buscaAtual !== ultimaBusca) return;
            results.innerHTML = '<div class="records-live-empty">Não foi possível buscar agora.</div>';
            results.hidden = false;
        }
    }, 180);

    input.addEventListener("input", runRecordsSearch);

    input.addEventListener("focus", () => {
        if (input.value.trim()) runRecordsSearch();
    });

    input.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            closeRecordsResults();
            input.blur();
        }
    });

    input.addEventListener("blur", () => {
        setTimeout(() => {
            if (!searchBox.contains(document.activeElement)) {
                closeRecordsResults();
            }
        }, 180);
    });
});

// ============================================================

// NOTIFICAÇÕES DE SOLICITAÇÕES DO MOBILE

// ============================================================

// O sistema consulta o servidor periodicamente para que o sino acenda

// mesmo se a funcionária estiver usando outra tela do sistema.

const requestNotificationBell = document.querySelector("#requestNotificationBell");

const requestNotificationBadge = document.querySelector("#requestNotificationBadge");


if (requestNotificationBell && requestNotificationBadge) {

    let ultimoTotalSolicitacoes = Number(

        requestNotificationBell.dataset.initialCount || 0

    );


    const atualizarVisualSino = total => {

        const possuiPendentes = total > 0;


        requestNotificationBell.classList.toggle(

            "has-notifications",

            possuiPendentes

        );


        requestNotificationBadge.hidden = !possuiPendentes;

        requestNotificationBadge.textContent = total > 99 ? "99+" : String(total);


        requestNotificationBell.setAttribute(

            "aria-label",

            possuiPendentes

                ? `${total} solicitação(ões) de agendamento pendente(s)`

                : "Nenhuma solicitação de agendamento pendente"

        );

    };


    const consultarSolicitacoesMobile = async () => {

        try {

            const resposta = await fetch(

                `<?= BASE_URL ?>/pages/notificacoes-solicitacoes.php?_=${Date.now()}`,

                {

                    cache: "no-store",

                    headers: {

                        "X-Requested-With": "XMLHttpRequest"

                    }

                }

            );


            if (!resposta.ok) {

                return;

            }


            const dados = await resposta.json();


            if (!dados.sucesso) {

                return;

            }


            const totalAtual = Number(dados.total || 0);


            // Quando chega uma nova solicitação enquanto a página já está aberta,

            // além de acender o sino fazemos um pulso rápido para chamar atenção.

            if (totalAtual > ultimoTotalSolicitacoes) {

                requestNotificationBell.classList.remove("notification-arrived");

                void requestNotificationBell.offsetWidth;

                requestNotificationBell.classList.add("notification-arrived");


                window.setTimeout(() => {

                    requestNotificationBell.classList.remove("notification-arrived");

                }, 800);


                // [BP-ALTERAÇÃO #4] Avisa o assets/js/preferencias.js; se "Alerta visual" estiver ligado em

                // Configurações, aparece um aviso grande na tela (ajuda quem não ouve ou não está olhando o sino).

                document.dispatchEvent(new CustomEvent("beautypet:nova-solicitacao", { detail: { total: totalAtual } }));

            }


            atualizarVisualSino(totalAtual);

            ultimoTotalSolicitacoes = totalAtual;

        } catch (erro) {

            // Uma falha temporária de rede não interfere no restante da página.

        }

    };


    atualizarVisualSino(ultimoTotalSolicitacoes);
    consultarSolicitacoesMobile();

    // Consulta a cada 8 segundos.

    window.setInterval(consultarSolicitacoesMobile, 8000);


    // Também confere imediatamente quando o usuário volta para esta aba.

    document.addEventListener("visibilitychange", () => {

        if (!document.hidden) {

            consultarSolicitacoesMobile();

        }

    });

}

</script>


<!-- [BP-ALTERAÇÃO #6] Máscaras de CPF, telefone, CEP, dinheiro e peso (veja as instruções no topo do arquivo). -->

<script src="<?= BASE_URL ?>/assets/js/mascaras.js?v=bp-mascaras-v1"></script>

<!-- [BP-ATALHOS] Atalhos de teclado (Alt + letra) para o menu lateral + dica ao parar o mouse sobre o item. -->
<script>
(function () {
    // Pega todos os links do menu lateral que têm o atributo data-atalho (definido no topo.php).
    const links = document.querySelectorAll(".sidebar a[data-atalho]");
    if (!links.length) return;

    // Monta uma "tabela" letra -> link. Ex.: mapa["A"] é o link da Agenda.
    const mapa = {};
    links.forEach(link => {
        mapa[link.dataset.atalho.toUpperCase()] = link;
    });

    // ------------------------------------------------------------
    // 1) DICA QUE REVELA O ATALHO
    // ------------------------------------------------------------

    // Cria uma única caixinha de dica, reaproveitada por todos os itens.
    const dica = document.createElement("div");
    dica.className = "bp-atalho-dica";
    dica.setAttribute("role", "tooltip");
    document.body.appendChild(dica);

    // Monta o nome do item; nos submenus inclui o grupo (ex.: "Pets › Cadastro") para não confundir os dois "Cadastro".
    function nomeDoItem(link) {
        const nome = (link.getAttribute("aria-label") || link.textContent).trim().replace(/\s+/g, " ");
        const resumo = link.closest("details")?.querySelector("summary");
        const grupo = resumo ? resumo.getAttribute("aria-label") : "";
        return grupo ? grupo + " › " + nome : nome;
    }

    // Mostra a dica ao lado do item, centralizada na vertical.
    function mostrarDica(link) {
        dica.innerHTML = "";

        const texto = document.createElement("span");
        texto.textContent = nomeDoItem(link);

        const tecla = document.createElement("kbd");
        tecla.textContent = "Alt + " + link.dataset.atalho.toUpperCase();

        dica.append(texto, tecla);

        const caixa = link.getBoundingClientRect();
        const topo = caixa.top + caixa.height / 2 - dica.offsetHeight / 2;

        dica.style.left = caixa.right + 10 + "px";
        // Math.max/min impedem que a dica saia para fora da tela.
        dica.style.top = Math.max(4, Math.min(topo, window.innerHeight - dica.offsetHeight - 4)) + "px";
        dica.classList.add("is-visible");
    }

    // O mouse precisa ficar PARADO ~0,4 s sobre o item para a dica aparecer.
    let temporizador = null;

    function esconderDica() {
        clearTimeout(temporizador);
        dica.classList.remove("is-visible");
    }

    function agendarDica(link) {
        if (dica.classList.contains("is-visible")) return; // já está aparecendo
        clearTimeout(temporizador);                        // se o mouse mexeu, recomeça a contagem
        temporizador = setTimeout(() => mostrarDica(link), 400);
    }

    links.forEach(link => {
        link.addEventListener("mouseenter", () => agendarDica(link)); // mouse entrou no item
        link.addEventListener("mousemove", () => agendarDica(link));  // mouse ainda se movendo: espera parar
        link.addEventListener("mouseleave", esconderDica);            // mouse saiu: some
        link.addEventListener("click", esconderDica);                 // clicou: some

        // Quem navega só pelo teclado (Tab) também vê o atalho, sem esperar.
        link.addEventListener("focus", () => {
            if (link.matches(":focus-visible")) mostrarDica(link);
        });
        link.addEventListener("blur", esconderDica);
    });

    // A dica some ao rolar a página ou apertar Esc (evita ficar "perdida" na tela).
    window.addEventListener("scroll", esconderDica, true);
    document.addEventListener("keydown", event => {
        if (event.key === "Escape") esconderDica();
    });

    // ------------------------------------------------------------
    // 2) PREVENÇÃO DE ERROS: avisa antes de sair de um formulário preenchido
    // ------------------------------------------------------------

    let formularioAlterado = false;

    // Marca quando a pessoa digita/altera algo em um formulário da área principal.
    document.addEventListener("input", event => {
        if (event.target.closest && event.target.closest("main form")) {
            formularioAlterado = true;
        }
    });

    // Ao salvar/enviar, não há mais dados "pendentes".
    document.addEventListener("submit", () => {
        formularioAlterado = false;
    });

    // ------------------------------------------------------------
    // 3) EXECUTA O ATALHO (Alt + letra)
    // ------------------------------------------------------------

    document.addEventListener("keydown", event => {
        // Só vale Alt sozinho + letra. Ctrl/Shift/Cmd junto (inclusive AltGr do teclado ABNT2) são ignorados.
        if (!event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (event.getModifierState && event.getModifierState("AltGraph")) return;
        if (event.repeat) return; // segurar a tecla não dispara várias vezes

        // Com a janela "Trocar usuário" aberta, os atalhos ficam desligados para não tirar a pessoa dela.
        if (document.body.classList.contains("account-modal-open")) return;

        // event.code ("KeyA") funciona em qualquer layout; no Mac, Option+A geraria outro caractere em event.key.
        if (!event.code || !event.code.startsWith("Key")) return;
        const link = mapa[event.code.slice(3)];
        if (!link) return;

        // Impede que o navegador use o Alt+letra para outra coisa.
        event.preventDefault();
        esconderDica();

        // Já está nessa tela: apenas leva o foco para o conteúdo, sem recarregar a página.
        if (link.pathname === window.location.pathname) {
            const principal = document.getElementById("conteudo-principal");
            if (principal) principal.focus();
            return;
        }

        // Se há formulário preenchido e não salvo, pede confirmação antes de sair.
        if (formularioAlterado && !window.confirm("Há dados preenchidos que ainda não foram salvos. Deseja sair desta página mesmo assim?")) {
            return;
        }

        // Vai para a tela do item, igual a clicar nele.
        window.location.assign(link.href);
    });
})();
</script>

</body>

</html> 