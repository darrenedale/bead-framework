import {ApiCallResponse} from "./ApiCallResponse.js";
import {Application} from "./Application.js";

// a callback for when an API call succeeded in getting a valid response from the server
export interface SuccessfulApiCallCallback {
    (response: ApiCallResponse): void;
}

// a callback for when an API failed to get a valid response from the server
export interface AbortedApiCallCallback {
    (): void;
}

export interface ApiCallOptions {
    onSuccess?: SuccessfulApiCallCallback;
    onFailure?: SuccessfulApiCallCallback;
    onFinished?: SuccessfulApiCallCallback;
    onAbort?: AbortedApiCallCallback;
}

/**
 * Encapsulates a call to an API endpoint.
 */
export class ApiCall extends XMLHttpRequest {
    private m_action: string;
    private m_options: ApiCallOptions;
    private m_parameters: object;
    private m_data: object;

    constructor(action: string, parameters: object = null, data: object = null, options: ApiCallOptions = null) {
        super();
        this.m_action = action;
        this.m_parameters = parameters;
        this.m_data = data;
        this.m_options = options;
    }

    get action(): string {
        return this.m_action;
    }

    set action(action: string) {
        if ("" == action) {
            throw new TypeError("action must not be empty");
        }

        this.m_action = action;
    }

    get options(): ApiCallOptions {
        return this.m_options;
    }

    set options(options: ApiCallOptions | null) {
        this.m_options = options;
    }

    get parameters(): object {
        return this.m_parameters;
    }

    set parameters(parameters: object | null) {
        this.m_parameters = parameters;
    }

    get data(): object {
        return this.m_parameters;
    }

    set data(data: object | null) {
        this.m_data = data;
    }

    private onApiCallLoad(): void {
        if (!this.options) {
            return;
        }

        let response = new ApiCallResponse(this.responseText);

        if (this.options.onFinished) {
            this.options.onFinished(response);
        }

        if (200 <= this.status && 299 >= this.status) {
            if (this.options.onSuccess) {
                this.options.onSuccess(response);
            }
        } else if (this.options.onFailure) {
            this.options.onFailure(response);
        }
    }

    private onApiCallAbort() {
        if (this.options && this.options.onAbort) {
            this.options.onAbort();
        }
    }

    private onApiCallError() {
        if (!this.options) {
            return;
        }

        let response = new ApiCallResponse(this.responseText);

        if (this.options && this.options.onFinished) {
            this.options.onFinished(response);
        }

        if (this.options && this.options.onFailure) {
            this.options.onFailure(response);
        }
    }

    public send(): void {
        let url = Application.baseUrl + "?action=" + encodeURIComponent(this.action);

        this.addEventListener("load", function () {
            // noinspection JSPotentiallyInvalidUsageOfClassThis (function is explicitly bound)
            this.onApiCallLoad();
        }.bind(this), true);

        this.addEventListener("abort", function () {
            // noinspection JSPotentiallyInvalidUsageOfClassThis (function is explicitly bound)
            this.onApiCallAbort();
        }.bind(this), true);

        this.addEventListener("error", function () {
            // noinspection JSPotentiallyInvalidUsageOfClassThis (function is explicitly bound)
            this.onApiCallError();
        }.bind(this), true);

        if (this.parameters) {
            for (let pName in this.parameters) {
                if (!this.parameters.hasOwnProperty(pName)) {
                    continue;
                }

                url += "&" + encodeURIComponent(pName) + "=" + encodeURIComponent(this.parameters[pName]);
            }
        }

        // if we have data, POST it ...
        if (this.data) {
            let body = "";
            let boundary = "-o-o-o-bndy" + Date.now().toString(16) + "-o-o-o-";

            this.open("POST", url, true);
            this.setRequestHeader("Content-Type", "multipart\/form-data; boundary=" + boundary);

            for (let dName in this.data) {
                if (!this.data.hasOwnProperty(dName)) {
                    continue;
                }

                /* if the data is an array, send each element with the same name */
                let actualData = this.data[dName];

                if (!Array.isArray(actualData)) {
                    actualData = [actualData];
                }

                actualData.forEach(
                    function (v) {
                        body += "--" + boundary + "\r\nContent-Type: text/plain\r\nContent-Disposition: form-data; name=\"" + dName + "\"\r\n\r\n" + v + "\r\n";
                    });
            }

            body += "--" + boundary + "--\r\n";
            super.send(body);
        } else {
            // ... otherwise just GET the response
            this.open("GET", url, true);
            super.send();
        }
    }
}
