function submitWithIncorrectCsrf()
{
    const csrf = document.querySelector("input[name=\"_token\"]");
    csrf.value = csrf.value.substring(1);
    csrf.form.submit();
}

(() => {
    window.addEventListener("load", () => {
        document.querySelector("#incorrect-csrf-button").addEventListener("click", submitWithIncorrectCsrf);
    });
})();
