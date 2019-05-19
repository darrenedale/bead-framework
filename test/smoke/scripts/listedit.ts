import { Application } from "../../../js/Application.js";

(function(window) {
    window.addEventListener("load", function() {
        let app = new Application();
        app.baseUrl = "listedit.php";
    });
})(window);
