(function() {
    window.addEventListener("load", function() {
        const pre = document.createElement("pre");
        pre.append(document.createTextNode("script2.js loaded"));
        document.querySelector("footer").append(pre);
    });
})();
