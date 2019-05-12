import {ContentStructureError} from "./Application.js";

interface TabSwitchHTMLLIElement extends HTMLLIElement {
    tabIndex: number;
}

interface TabContentHTMLElement extends HTMLLIElement {
    tabIndex: number;
}

class TabbedView {
    private static readonly HtmlClassName: string = "eq-tabview";
    private static readonly TabSwitchesHtmlClassName: string = "eq-tabview-tabs";
    private static readonly TabContentHtmlClassName: string = "eq-tabview-content-container";

    public readonly container: HTMLElement = null;
    public readonly tabSwitches: HTMLCollection = null;
    public readonly tabs: HTMLCollection = null;

    public static initialise(): void {
        for(let elem of document.getElementsByClassName(TabbedView.HtmlClassName)) {
            try {
                new TabbedView(elem);
            }
            catch(err) {
                console.error(`failed to initialise TabbedView: ${err}`);
                console.error(elem);
            }
        }
    }

    public constructor(elem) {
        // could have nested TabbedView objects, so detect direct children only
        let tabSwitches: HTMLCollection = null;
        let tabs: HTMLCollection = null;

        for(let child of elem.children) {
            if(child.classList.contains(TabbedView.TabSwitchesHtmlClassName)) {
                if(tabSwitches) {
                    throw new ContentStructureError("too many tab switch containers found");
                }

                tabSwitches = child.children;
            }

            if(child.classList.contains(TabbedView.TabContentHtmlClassName)) {
                if(tabs) {
                    throw new ContentStructureError("too many tab content containers found");
                }

                tabs = child.children;
            }
        }

        if(null === tabSwitches) {
            throw new ContentStructureError("failed to locate container for tab switches");
        }

        if(null === tabs) {
            throw new ContentStructureError("failed to locate container for tab content");
        }

        this.container = elem;
        this.tabSwitches = tabSwitches;
        this.tabs = tabs;

        let onTabClicked = (ev: Event) => {
            this.currentTab = (<TabSwitchHTMLLIElement> ev.target).tabIndex;;
        };

        let idx = 0;

        for(let tabSwitch of this.tabSwitches) {
            (<TabSwitchHTMLLIElement> tabSwitch).tabIndex = idx;
            tabSwitch.addEventListener("click", onTabClicked);
            ++idx;
        }

        idx = 0;

        for(let tab of this.tabs) {
            (<TabContentHTMLElement> tab).tabIndex = idx;
            ++idx;
        }
    }

    get tabCount() {
        return Math.min(this.tabs.length, this.tabSwitches.length);
    }

    set currentTab(idx) {
        if(idx === this.currentTab) {
            return;
        }

        if(0 > idx || this.tabCount <= idx) {
            console.warn(`ignored attempt to set current tab to invalid index ${idx}`);
            return;
        }

        for(let tabSwitch of this.tabSwitches) {
            tabSwitch.classList.remove("selected");
        }
        for(let tab of this.tabs) {
            tab.classList.remove("selected");
        }

        this.tabSwitches[idx].classList.add("selected");
        this.tabs[idx].classList.add("selected");
    }

    get currentTab() {
        let n = this.tabs.length;

        for(let idx = 0; idx < n; ++idx) {
            if(this.tabs[idx].classList.contains("selected")) {
                return idx;
            }

        }

        n = this.tabSwitches.length;

        for(let idx = 0; idx < n; ++idx) {
            if(this.tabSwitches[idx].classList.contains("selected")) {
                return idx;
            }
        }

        console.warn("no current tab");
        console.warn(this.container);
        return -1;
    }
}

(function() {
    window.addEventListener("load", function() {
        TabbedView.initialise();
    });
}());