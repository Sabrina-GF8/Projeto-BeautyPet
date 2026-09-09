        </main>
    </div>
</div>

<script>
// Seleciona os elementos responsáveis pelo menu de perfil do usuário.
const profile = document.querySelector(".profile-container");
const profileButton = document.querySelector("#profileButton");
const profileMenu = document.querySelector("#profileMenu");

// Verifica se o botão e o perfil existem na página.
if (profile && profileButton) {

    // Abre ou fecha o menu de perfil quando o botão é clicado.
    profileButton.addEventListener("click", event => {
        event.stopPropagation();
        profile.classList.toggle("open");
    });

    // Impede que um clique dentro do menu feche o menu automaticamente.
    if (profileMenu) {
        profileMenu.addEventListener("click", event => event.stopPropagation());
    }

    // Fecha o menu de perfil quando o usuário clica fora dele.
    document.addEventListener("click", () => profile.classList.remove("open"));
}


// Seleciona o campo de upload da foto e o espaço onde a prévia será exibida.
const photoInput = document.querySelector("#foto");
const photoPreview = document.querySelector("#photoPreview");

// Verifica se os elementos de foto existem na página.
if (photoInput && photoPreview) {

    // Detecta quando o usuário seleciona uma nova foto.
    photoInput.addEventListener("change", () => {

        // Obtém o primeiro arquivo selecionado.
        const file = photoInput.files[0];

        // Se nenhum arquivo foi selecionado, encerra a função.
        if (!file) return;

        // Cria um leitor para carregar o arquivo selecionado.
        const reader = new FileReader();

        // Executa quando o arquivo termina de ser carregado.
        reader.onload = event => {

            // Exibe a foto selecionada como uma prévia na tela.
            photoPreview.innerHTML = `<img src="${event.target.result}" alt="Prévia da foto do pet">`;
        };

        // Lê o arquivo como uma URL para poder exibi-lo na página.
        reader.readAsDataURL(file);
    });
}


// Seleciona todos os botões responsáveis por mostrar ou ocultar senhas.
document.querySelectorAll("[data-password-target]").forEach(button => {

    // Detecta quando o usuário clica no botão de mostrar/ocultar senha.
    button.addEventListener("click", () => {

        // Obtém o ID do campo de senha relacionado ao botão.
        const targetId = button.dataset.passwordTarget;

        // Localiza o campo de senha pelo ID.
        const input = document.getElementById(targetId);

        // Localiza o ícone dentro do botão.
        const icon = button.querySelector("i");

        // Se o campo de senha não existir, encerra a função.
        if (!input) return;

        // Verifica se a senha está atualmente escondida.
        const mostrar = input.type === "password";

        // Alterna entre mostrar e esconder a senha.
        input.type = mostrar ? "text" : "password";

        // Altera o ícone de acordo com o estado da senha.
        if (icon) {
            icon.className = mostrar ? "fa-regular fa-eye-slash" : "fa-regular fa-eye";
        }
    });
});
</script>
</body>
</html>
