        </main>
    </div>
</div>

<script>
const profile = document.querySelector(".profile-container");
const profileButton = document.querySelector("#profileButton");
const profileMenu = document.querySelector("#profileMenu");

if (profile && profileButton) {
    profileButton.addEventListener("click", event => {
        event.stopPropagation();
        profile.classList.toggle("open");
    });

    if (profileMenu) {
        profileMenu.addEventListener("click", event => event.stopPropagation());
    }

    document.addEventListener("click", () => profile.classList.remove("open"));
}

const photoInput = document.querySelector("#foto");
const photoPreview = document.querySelector("#photoPreview");

if (photoInput && photoPreview) {
    photoInput.addEventListener("change", () => {
        const file = photoInput.files[0];

        if (!file) return;

        const reader = new FileReader();

        reader.onload = event => {
            photoPreview.innerHTML = `<img src="${event.target.result}" alt="Prévia da foto do pet">`;
        };

        reader.readAsDataURL(file);
    });
}

document.querySelectorAll("[data-password-target]").forEach(button => {
    button.addEventListener("click", () => {
        const targetId = button.dataset.passwordTarget;
        const input = document.getElementById(targetId);
        const icon = button.querySelector("i");

        if (!input) return;

        const mostrar = input.type === "password";
        input.type = mostrar ? "text" : "password";

        if (icon) {
            icon.className = mostrar ? "fa-regular fa-eye-slash" : "fa-regular fa-eye";
        }
    });
});
</script>
</body>
</html>
