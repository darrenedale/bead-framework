export class Popup {
    public static readonly HtmlClassName = "equit-popup";
    public static readonly AnchorHtmlClassName = "popup-anchor";
    public static readonly ContentHtmlClassName = "popup-content";

    private static readonly ValidTriggers = ["click", "hover"];
    private static readonly DefaultTrigger = "click";

    private m_popupIsVisible: boolean = false;
    private readonly m_container: HTMLElement = null;
    private readonly m_anchor: HTMLElement = null;
    private readonly m_content: HTMLElement = null;

    public static initialise() {
        for(let popup of document.getElementsByClassName(Popup.HtmlClassName)) {
            if(!(popup instanceof HTMLElement)) {
                continue;
            }

            new Popup(popup);
        }
    }

    public constructor(container: HTMLElement) {
        if(!container.dataset.triggers) {
            throw new Error("failed to find required data attribute 'triggers'");
        }

        let anchorElements = container.getElementsByClassName(Popup.AnchorHtmlClassName);

        if(!anchorElements || 1 !== anchorElements.length || !(anchorElements[0] instanceof HTMLElement)) {
            throw new Error("failed to find popup's anchor element");
        }

        let contentElements = container.getElementsByClassName(Popup.ContentHtmlClassName);

        if(!contentElements || 1 !== contentElements.length || !(contentElements[0] instanceof HTMLElement)) {
            throw new Error("failed to find popup's content element");
        }

        this.m_container = container;
        this.m_anchor = <HTMLElement> anchorElements[0];
        this.m_content = <HTMLElement> contentElements[0];

        let descriptor = {
            enumerable: true,
            writable: false,
            configurable: false,
            value: this,
        };

        Object.defineProperty(container, "popup", descriptor);
        Object.defineProperty(this.m_anchor, "popup", descriptor);
        Object.defineProperty(this.m_content, "popup", descriptor);

        // extract and validate the triggers
        let triggers = container.dataset.triggers.split("|").filter(function(val) {
            return -1 !== Popup.ValidTriggers.indexOf(val);
        });

        // set to default if no valid triggers found
        if(0 === triggers.length) {
            triggers = [Popup.DefaultTrigger];
        }

        if(-1 !== triggers.indexOf("click")) {
            this.m_anchor.addEventListener("click", () => {
                this.togglePopup();
            });
        }

        if(-1 !== triggers.indexOf("hover")) {
            container.addEventListener("mouseover", () => {
                this.showPopup();
            });

            container.addEventListener("mouseover", () => {
                this.hidePopup();
            });
        }

        this.hidePopup();
    }

    public togglePopup(): void {
        if(this.popupIsVisible) {
            this.hidePopup();
        }
        else {
            this.showPopup();
        }
    }

    public showPopup(): void {
        this.m_content.classList.add("visible");
        this.m_popupIsVisible = true;
    }

    public hidePopup() {
        this.m_content.classList.remove("visible");
        this.m_popupIsVisible = false;
    }

    public get popupIsVisible(): boolean {
        return this.m_popupIsVisible;
    }
}

(function() {
    window.addEventListener("load", function() {
        Popup.initialise();
    });
})();
