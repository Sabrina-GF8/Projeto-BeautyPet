        </main>
    </div>
</div>

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
            const response = await fetch(`busca-registros.php?tipo=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`, {
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
            const response = await fetch(`busca-registros.php?tipo=geral&q=${encodeURIComponent(q)}`);
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
                link.href = item.url;
                link.className = "global-search-option";
                link.innerHTML = `<span class="global-search-kind"><i class="fa-solid ${item.kind === "pet" ? "fa-paw" : "fa-user"}"></i></span><span><strong></strong><small></small></span>`;
                link.querySelector("strong").textContent = item.label;
                link.querySelector("small").textContent = item.detail || "";
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
    input?.addEventListener("blur", () => setTimeout(closeGlobal, 140));
}
</script>
</body>
</html>
