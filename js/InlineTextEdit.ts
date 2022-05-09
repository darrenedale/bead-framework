interface HTMLInlineInternalTextEdit extends HTMLInputElement {
    readonly inlineTextEdit: InlineTextEdit,
}

interface HTMLInlineInternalDisplayElement extends HTMLSpanElement {
    readonly inlineTextEdit: InlineTextEdit,
}

interface HTMLInlineTextEditRootElement extends HTMLDivElement {
    readonly inlineTextEdit: InlineTextEdit,
}

class InlineTextEdit {
    public static readonly HtmlClassName: string = "eq-inline-text-edit";
    public static readonly InternalEditorHtmlClassName: string = InlineTextEdit.HtmlClassName + "-editor";
    public static readonly InternalDisplayElementHtmlClassName: string = InlineTextEdit.HtmlClassName + "-display";

    private container: HTMLInlineTextEditRootElement;
    public readonly submitApiFunction: string;
    public readonly submitApiParameterName: string;
    public readonly submitApiOtherArguments: object;
    public readonly internalEditor: HTMLInlineInternalTextEdit;
    public readonly internalDisplay: HTMLInlineInternalDisplayElement;

    private m_oldValue: string;
    private m_submitting: boolean = false;

    public static bootstrap(): boolean {
        if(InlineTextEdit.bootstrap.hasOwnProperty("success")) {
            return InlineTextEdit.bootstrap["success"];
        }

        for (let editor of document.querySelectorAll(`.${InlineTextEdit.HtmlClassName}`)) {
            if(!(editor instanceof HTMLDivElement)) {
                console.warn("ignored invalid element type with " + InlineTextEdit.HtmlClassName + " class");
                continue;
            }

            try {
                new InlineTextEdit(<HTMLInlineTextEditRootElement> editor);
            }
            catch(err) {
                console.error("failed to initialise AdvancedSearchForm " + editor + ": " + err);
            }
        }

        Object.defineProperty(InlineTextEdit.bootstrap, "success", {
            enumerable: false,
            configurable: false,
            writable: false,
            value: true,
        });

        return InlineTextEdit.bootstrap["success"];
    }

    public constructor(edit: HTMLInlineTextEditRootElement) {
        let internalEditor = edit.getElementsByClassName(InlineTextEdit.InternalEditorHtmlClassName);

        if (!internalEditor || 1 !== internalEditor.length) {
            throw new Error("failed to find text edit element for inline text edit");
        }

        if (!(internalEditor[0] instanceof HTMLInputElement)) {
            throw new TypeError("text edit for inline text edit is not of correct type");
        }

        let displayElement = edit.getElementsByClassName(InlineTextEdit.InternalDisplayElementHtmlClassName);

        if (!displayElement || 1 !== displayElement.length) {
            throw new Error("failed to find display element for inline text edit");
        }

        if (!(displayElement[0] instanceof HTMLSpanElement)) {
            throw new TypeError("display element for inline text edit is not of correct type");
        }

        let apiFnName = edit.dataset.apiFunctionName;

        if (undefined == apiFnName) {
            throw new Error("failed to find API function name for inlne text edit");
        }

        let apiParamName = edit.dataset.apiFunctionContentParameterName;

        if (undefined == apiParamName) {
            throw new Error("failed to find API parameter name for inline text edit");
        }

        let otherArgs = {};

        for (let paramName in edit.dataset) {
            let result = paramName.match(/^apiFunctionParameter([a-zA-Z][a-zA-Z0-9_]+)$/);

            if (!result) {
                continue;
            }

            otherArgs[result[1]] = edit.dataset[paramName];
        }

        this.container = edit;
        this.internalEditor = <HTMLInlineInternalTextEdit> internalEditor[0];
        this.internalDisplay = <HTMLInlineInternalDisplayElement> displayElement[0];
        this.submitApiFunction = apiFnName;
        this.submitApiParameterName = apiParamName;
        this.submitApiOtherArguments = otherArgs;
        this.m_oldValue = this.value;

        // these are readonly in the interface, so we cheat to set them. it's OK, because these are interfaces we "own"
        Object.defineProperty(edit, "inlineTextEdit", this.objectDescriptor);
        Object.defineProperty(this.internalEditor, "inlineTextEdit", this.objectDescriptor);
        Object.defineProperty(this.internalDisplay, "inlineTextEdit", this.objectDescriptor);

        this.internalEditor.addEventListener("keypress", (ev: KeyboardEvent) => {
            this.onKeyPress(ev);
        });

        this.internalEditor.addEventListener("keydown", (ev: KeyboardEvent) => {
            this.onKeyDown(ev);
        });

        this.internalEditor.addEventListener("blur", () => {
            this.onFocusOut();
        });

        this.internalDisplay.addEventListener("click", () => {
            this.showEditor();
            this.internalEditor.focus();
        });

        this.hideEditor();
    }

    public showEditor(): void {
        this.internalEditor.style.display = "";
        this.internalDisplay.style.display = "none";
    }

    public hideEditor(): void {
        this.internalEditor.style.display = "none";
        this.internalDisplay.style.display = "";
    }

    public accept(): void {
        if(this.m_submitting) {
            return;
        }

        if(this.value == this.m_oldValue) {
            this.hideEditor();
            return;
        }

        this.submitText();
    }

    public cancel(): void {
        this.value = this.m_oldValue;
        this.hideEditor()
    }

    protected syncDisplayWithEditor() {
        while(this.internalDisplay.firstChild) {
            this.internalDisplay.removeChild(this.internalDisplay.firstChild);
        }

        this.internalDisplay.appendChild(document.createTextNode(this.value));
    }

    private onSubmitSucceeded(response: ApiCallResponse): void {
        if(0 === response.code) {
            this.m_oldValue = this.value;
            this.syncDisplayWithEditor();
            this.hideEditor();
            return;
        }

        let msg = document.createElement("div");
        msg.appendChild(document.createTextNode(response.message));

        let toast = Application.instance.toast(msg, {
            "timeout": 0,
            "closeButton": true,
            "customButtons": [
                {
                    "content": "Forget changes",
                    "fn": () => {
                        this.cancel();
                        toast.close();
                    },
                },
            ]
        });
    }

    private onSubmitFailed(response: ApiCallResponse): void {
        let msg = document.createElement("div");
        msg.appendChild(document.createTextNode("The API call failed: " + response.message));

        let toast = Application.instance.toast(msg, {
            "timeout": 0,
            "closeButton": true,
            "customButtons": [
                {
                    "content": "Forget changes",
                    "fn": () => {
                        this.cancel();
                        toast.close();
                    },
                },
            ]
        });
    }

    protected submitText() {
        if(!this.submitApiFunction) {
            this.syncDisplayWithEditor();
            this.hideEditor();
        }

        this.m_submitting = true;
        let params = this.submitApiOtherArguments;
        params[this.submitApiParameterName] = this.value;
        let apiCall = new ApiCall(this.submitApiFunction, params, null, {
            "onSuccess": (response: ApiCallResponse) => {
                this.onSubmitSucceeded(response);
                this.m_submitting = false;
            },
            "onFailure": (response: ApiCallResponse) => {
                this.onSubmitFailed(response);
                this.m_submitting = false;
            },
        });

        apiCall.send();
    }

    protected onFocusOut() {
        this.accept();
    }

    protected onKeyPress(ev: KeyboardEvent) {
        switch (ev.key) {
            case "Enter":
                this.accept();
                // remove focus now, while it will be ignored, otherwise the toast might steal focus later and trigger
                // another attempted updated after we've unset the ignore flag
                this.internalEditor.blur();
                break;
        }
    }

    protected onKeyDown(ev: KeyboardEvent) {
        switch (ev.key) {
            case "Escape":
                this.cancel();
                break;
        }
    }

    get objectDescriptor(): PropertyDescriptor {
        return {
            enumerable: true,
            configurable: false,
            writable: false,
            value: this,
        };
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
        this.syncDisplayWithEditor();
    }

    public get placeholder(): string {
        return this.internalEditor.placeholder;
    }

    public set placeholder(value: string) {
        this.internalEditor.placeholder = value;
    }
}

(function() {
    window.addEventListener("load", function() {
        InlineTextEdit.bootstrap();
    });
})();