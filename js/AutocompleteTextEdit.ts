import {ApiCallResponse} from "./ApiCallResponse";
import {ApiCall} from "./ApiCall";

interface Suggestion {
    label: string,
    value: string,
}

interface ResponseProcessor {
    (responseData: string): Suggestion[];
}

interface HTMLSuggestionListElement extends HTMLUListElement {
    currentIndex: number;
}

interface HTMLSuggestionListItemElement extends HTMLLIElement {
    suggestion: string,
    autocompleteTextEdit: AutocompleteTextEdit,
}

export class AutocompleteTextEdit {
    public static readonly CssClassName: string = "autocomplete-text-edit";
    public static readonly InternalEditorCssClassName: string = "autocomplete-text-edit-editor";
    public static readonly SuggestionsListCssClassName: string = "autocomplete-text-edit-suggestions";

    private container: HTMLDivElement;
    public readonly suggestionsApiFunction: string;
    public readonly suggestionsApiParameterName: string;
    public readonly suggestionsApiOtherArguments: object;
    public readonly internalEditor: HTMLInputElement;
    public readonly suggestionsList: HTMLSuggestionListElement;
    private readonly customResponseProcessor?: ResponseProcessor;
    private m_timerId: number;
    private m_oldValue: string;

    public static initialise() {
        let editors = document.getElementsByClassName(AutocompleteTextEdit.CssClassName);

        for (let idx = 0; idx < editors.length; ++idx) {
            if(!(editors[idx] instanceof HTMLDivElement)) {
                console.warn("ignored invalid element type with " + AutocompleteTextEdit.CssClassName + " class");
                continue;
            }

            try {
                new AutocompleteTextEdit(<HTMLDivElement> editors[idx]);
            }
            catch(err) {
                console.error("Failed to initialise AutocompleteTextEdit: " + err);
            }
        }
    }

    public constructor(edit: HTMLDivElement) {
        let internalEditor = edit.getElementsByClassName(AutocompleteTextEdit.InternalEditorCssClassName);

        if (!internalEditor || 1 !== internalEditor.length) {
            throw new Error("failed to find text edit element for autocomplete text edit");
        }

        if ((internalEditor[0] instanceof HTMLInputElement)) {
            throw new TypeError("text edit for autocomplete text edit is not of correct type");
        }

        let suggestionsList = edit.getElementsByClassName(AutocompleteTextEdit.SuggestionsListCssClassName);

        if (!suggestionsList || 1 !== suggestionsList.length) {
            throw new Error("failed to find suggestions list element for autocomplete text edit");
        }

        if (!(suggestionsList[0] instanceof HTMLUListElement)) {
            throw new TypeError("suggestions list for autocomplete text edit is not of correct type");
        }

        let apiFnName = edit.dataset.apiFunctionName;

        if (!apiFnName) {
            throw new Error("failed to find API function name for autocomplete text edit");
        }

        let apiParamName = edit.dataset.apiFunctionContentParameterName;

        if (!apiParamName) {
            throw new Error("failed to find API parameter name for autocomplete text edit");
        }

        let otherArgs = {};

        for (let paramName in edit.dataset) {
            let result = paramName.match(/^apiFunctionParameter([a-zA-Z][a-zA-Z0-9_]+)$/);

            if (!result) {
                continue;
            }

            otherArgs[result[1]] = edit.dataset[paramName];
        }

        Object.defineProperty(suggestionsList[0], "currentIndex", {
            configurable: false,
            enumerable: true,
            writable: true,
            value: -1,
        });

        this.container = edit;
        this.internalEditor = internalEditor[0];
        this.suggestionsList = <HTMLSuggestionListElement>suggestionsList[0];
        this.suggestionsApiFunction = apiFnName;
        this.suggestionsApiParameterName = apiParamName;
        this.suggestionsApiOtherArguments = otherArgs;

        Object.defineProperty(edit, "autocompleteTextEdit", this.objectDescriptor);
        Object.defineProperty(edit, "internalEditor", this.objectDescriptor);
        Object.defineProperty(edit, "suggestionsList", this.objectDescriptor);

        if (edit.dataset.apiFunctionResponseProcessor) {
            if (!edit.dataset.apiFunctionResponseProcessor.match(/^[a-zA-Z][a-zA-Z0-9_.]*[a-zA-Z0-9_]$/)) {
                console.error(`invalid response processor function name "${edit.dataset.apiFunctionResponseProcessor}" - using default processor`);
            } else {
                this.customResponseProcessor = <ResponseProcessor>new Function("responseData", "return " + edit.dataset.apiFunctionResponseProcessor + "(responseData);");
            }
        }

        this.internalEditor.addEventListener("onKeyDown", function (ev: KeyboardEvent) {
            switch (ev.key) {
                case "Escape":
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.hideSuggestions();
                    break;

                case " ":
                    /* NOTE alt-space is sometimes swallowed by OS/browser */
                    if (ev.altKey) {
                        /* alt-space opens suggestions list */
                        // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                        this.showSuggestions();
                        ev.preventDefault();
                        ev.stopPropagation();
                    }
                    break;

                case "ArrowUp":
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.showSuggestions();
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.previousSuggestion();
                    ev.preventDefault();
                    ev.stopPropagation();
                    break;

                case "ArrowDown":
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.showSuggestions();
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.nextSuggestion();
                    ev.preventDefault();
                    ev.stopPropagation();
                    break;

                case "Enter":
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    if (this.suggestionsVisible()) {
                        // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                        let value = this.currentSuggestion;
                        // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                        this.currentIndex = -1;
                        // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                        this.hideSuggestions();

                        if (!(value instanceof undefined)) {
                            // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                            this.value = value;
                        }

                        ev.preventDefault();
                        ev.stopPropagation();
                    }
                    break;
            }
        }.bind(this));

        this.internalEditor.addEventListener("onKeyDown", function (ev: KeyboardEvent) {
            if ("Delete" === ev.key || "Backspace" === ev.key) {
                if(this.timerId) {
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    window.clearTimeout(this.m_timerId);
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.m_timerId = 0;
                }

                let self = this;

                // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                if ("" === this.value) {
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.clear();
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.hideSuggestions();
                }
                // TODO oldValue is on internalEditor - put it on this instead
                else if (this.value !== this.m_oldValue) {
                    /* if user doesn't type for 0.5s, fetch suggestions */
                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.m_timerId = window.setTimeout(function () {
                        // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                        this.fetchSuggestions();
                    }, 500);

                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.m_oldValue = this.value;
                }
            }
        }.bind(this));

        // TODO bind keypress
    }

    get objectDescriptor() {
        return {
            enumerable: true,
            configurable: false,
            writable: false,
            value: this,
        };
    }

    protected processResponse(responseData: string): Suggestion[] {
        if(this.customResponseProcessor) {
            return this.customResponseProcessor(responseData);
        }

        // default response processor
        let items = responseData.split(/\n/);
        let suggestions = [];

        for(let item of items) {
            suggestions.push({
                "value":  item,
                "display": item,
            });
        }

        return suggestions;
    }

    public clear(): void {
        while(this.suggestionsList.firstChild) {
            this.suggestionsList.removeChild(this.suggestionsList.firstChild);
        }
    }

    public add(value: string, label?: string|Node): void {
        let suggestionItem = <HTMLSuggestionListItemElement> document.createElement("LI");

        if(!label) {
            label = value;
        }

        let display: Node;

        if("string" === typeof label) {
            display = document.createTextNode(value);
        }

        if("object" !== typeof display || !display.nodeName) {
            console.warn("invalid display object for suggestion - using value instead");
            display = document.createTextNode(value);
        }

        suggestionItem.setAttribute("class", "autocomplete-suggestion");
        suggestionItem.appendChild(display);
        Object.defineProperty(suggestionItem, "suggestion", {"configurable": false, "enumerable": false, "writable": false, "value": value});
        Object.defineProperty(suggestionItem, "autocompleteTextEdit", {"configurable": false, "enumerable": false, "writable": false, "value": this});

        this.suggestionsList.appendChild(suggestionItem);

        suggestionItem.addEventListener("click", function () {
            // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to suggestionItem)
            this.autocompleteTextEdit.value = this.suggestion;
            this.autocompleteTextEdit.hide();
        }.bind(suggestionItem));
    }

    public fetchSuggestions(): void {
        if (0 <= this.currentIndex) {
            let item = this.suggestionsList.children[this.currentIndex];
            item.classList.remove("current");
        }

        this.currentIndex = -1;
        let self = this;
        let params = this.suggestionsApiOtherArguments;
        params[this.suggestionsApiParameterName] = this.value;

        let successFn = function(response: ApiCallResponse) {
            if (0 === response.code) {
                let items = self.processResponse(response.data);
                let count = 0;

                for(let item of items) {
                    if("object" !== typeof item || !item.value) {
                        console.warn("skipped invalid item found in API call response");
                        continue;
                    }

                    // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
                    this.add(item.value, item.label);
                    ++count;
                }

                if (0 === count) {
                    let item = document.createElement("LI");
                    item.setAttribute("class", "autocomplete-no-suggestions-message");
                    item.appendChild(document.createTextNode("No suggestions..."));
                    self.suggestionsList.appendChild(item);
                }
            }

            // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
            this.currentIndex = -1;
            // noinspection JSPotentiallyInvalidUsageOfClassThis (explicityly bound to this)
            this.showSuggestions();
        }.bind(this);

        this.clear();
        let apiCall = new ApiCall(this.suggestionsApiFunction, params, null, {onSuccess: successFn});
        apiCall.send();
    }

    public showSuggestions(): void {
        this.suggestionsList.style.display = "";
    }

    public hideSuggestions(): void {
        this.suggestionsList.style.display = "none";
    }

    public suggestionsVisible(): boolean {
        return !this.suggestionsList.style.display || "" === this.suggestionsList.style.display;
    }

    public get name(): string {
        return this.internalEditor.name;
    }

    public set name(value: string) {
        this.internalEditor.name = value;
    }

    public get value(): string {
        return this.internalEditor.value;
    }

    public set value(val: string) {
        this.internalEditor.value = val;
    }

    public get placeholder(): string {
        return this.internalEditor.placeholder;
    }

    public set placeholder(value: string) {
        this.internalEditor.placeholder = value;
    }

    public get currentIndex(): number {
        return this.suggestionsList.currentIndex;
    }

    public set currentIndex(idx: number) {
        if(idx === this.suggestionsList.currentIndex) {
            return;
        }

        if(-1 > idx) {
            idx = -1;
        }

        let n = this.suggestionsList.children.length;

        if(1 === n && this.suggestionsList.children[0].classList.contains("autocomplete-no-suggestions-message")) {
            n = 0;
        }

        if(idx >= -1 && idx < n) {
            if(0 <= this.suggestionsList.currentIndex) {
                let item = this.suggestionsList.children[this.suggestionsList.currentIndex];
                item.classList.remove("current");
            }

            this.suggestionsList.currentIndex = idx;

            if(-1 < this.suggestionsList.currentIndex) {
                let item = this.suggestionsList.children[this.suggestionsList.currentIndex];
                item.classList.add("current");
            }

            if(this.internalEditor) {
                this.syncToCurrentSuggestion();
            }
        }

        console.log(`invalid index: ${idx}`);
        return;
    }

    public get suggestionCount(): number {
        return this.suggestionsList.children.length;
    }

    public get currentSuggestion(): string {
        return this.suggestion(this.currentIndex);
    }

    public nextSuggestion(): void {
        ++this.currentIndex;

        if(-1 < this.currentIndex && Element.prototype.scrollIntoView) {
            this.suggestionsList.children[this.currentIndex].scrollIntoView(false);
        }
    }

    public previousSuggestion(): void {
        if(0 < this.currentIndex) {
            --this.currentIndex;

            if(-1 < this.currentIndex && Element.prototype.scrollIntoView) {
                this.suggestionsList.children[this.currentIndex].scrollIntoView(false);
            }
        }
    }

    public suggestion(idx: number): string {
        if (idx >= 0 && idx < this.suggestionsList.children.length) {
            let item = this.suggestionsList.children[idx];

            if (item && item.tagName && "LI" === item.tagName) {
                return item.childNodes[0]["suggestion"];
            }
        }

        console.warn(`invalid index: ${idx}`);
        return undefined;
    }

    public syncToSuggestion(idx: number): void {
        this.internalEditor.value = this.suggestion(idx);
    }

    public syncToCurrentSuggestion(): void {
        this.syncToSuggestion(this.suggestionsList.currentIndex);
    }
}

(function() {
   window.addEventListener("load", function() {
       AutocompleteTextEdit.initialise();
   })
})();