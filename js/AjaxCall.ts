/**
 * Callback for when an API call succeeded in getting a valid response from the server
 */
type SuccessfulAjaxCallCallback = (response: AjaxCallResponse) => void;

/**
 * Callback for when an API failed to get a valid response from the server
 */
type AbortedAjaxCallCallback = () => void;

/**
 * Options for an Ajax call.
 */
interface AjaxCallOptions
{
    onSuccess?: SuccessfulAjaxCallCallback;
    onFailure?: SuccessfulAjaxCallCallback;
    onFinished?: SuccessfulAjaxCallCallback;
    onAbort?: AbortedAjaxCallCallback;
}

interface AjaxCallHeader
{
    name: string;
    value: string;
}

/**
 * Encapsulates an asynchronous call to an endpoint.
 */
class AjaxCall
{
    /**
     * The endpoint for the Ajax request.
     * @private
     */
    private m_endpoint: string;
    private m_options: AjaxCallOptions;
    private m_parameters: object;
    private m_data: object;
    private m_xhr: XMLHttpRequest;

    /**
     * Common headers sent with all Ajax requests.
     * @private
     */
    private static m_commonHeaders: AjaxCallHeader[] = [];

    /**
     * Initialise a new Ajax call.
     *
     * @param endpoint The remote endpoint to call.
     * @param parameters The URL parameters for the call.
     * @param data The POST data to send with the call.
     * @param options Options for the call.
     */
    constructor(endpoint: string, parameters: object = null, data: object = null, options: AjaxCallOptions = null)
    {
        this.m_xhr = new XMLHttpRequest();
        this.endpoint = endpoint;
        this.m_parameters = parameters;
        this.m_data = data;
        this.m_options = options;
    }

    /**
     * Add a header to be included with every Ajax request.
     *
     * @param header The header name.
     * @param value The header value.
     */
    public static addCommonHeader(header: string, value: string)
    {
        AjaxCall.m_commonHeaders.push({name: header, value: value});
    }

    /**
     * The common headers that will be sent with all Ajax requests.
     */
    public static get commonHeaders(): AjaxCallHeader[]
    {
        return AjaxCall.m_commonHeaders;
    }

    /**
     * The endpoint for the Ajax request.
     */
    get endpoint(): string
    {
        return this.m_endpoint;
    }

    /**
     * Set the endpoint for the Ajax request.
     * @param endpoint
     */
    set endpoint(endpoint: string)
    {
        if ("" == endpoint) {
            throw new TypeError("endpoint must not be empty");
        }

        this.m_endpoint = endpoint;
    }

    /**
     * The Ajax call options.
     */
    get options(): AjaxCallOptions
    {
        return this.m_options;
    }

    /**
     * Set the Ajax call options.
     */
    set options(options: AjaxCallOptions | null)
    {
        this.m_options = options;
    }

    /**
     * The URL parameters for the Ajax call.
     */
    get parameters(): object
    {
        return this.m_parameters;
    }

    /**
     * Set the URL parameters for the Ajax call.
     */
    set parameters(parameters: object | null)
    {
        this.m_parameters = parameters;
    }

    /**
     * The POST data for the Ajax call.
     */
    get data(): object
    {
        return this.m_data;
    }

    /**
     * Set the POST data for the Ajax call.
     */
    set data(data: object | null)
    {
        this.m_data = data;
    }

    /**
     * The response status from the Ajax call.
     */
    get status(): number
    {
        return this.m_xhr.status;
    }

    /**
     * The response text from the Ajax call.
     */
    get responseText(): string
    {
        return this.m_xhr.responseText;
    }

    /**
     * Handler for when the Ajax call response is received.
     * @private
     */
    private onAjaxCallLoad(): void
    {
        if (!this.options) {
            return;
        }

        let response = new AjaxCallResponse(this.responseText);
        console.debug(this.responseText);

        if (this.options.onFinished) {
            this.options.onFinished(response);
        }

        if (200 <= this.m_xhr.status && 299 >= this.status) {
            if (this.options.onSuccess) {
                this.options.onSuccess(response);
            }
        } else if (this.options.onFailure) {
            this.options.onFailure(response);
        }
    }

    /**
     * Handler for when the Ajax call is aborted.
     * @private
     */
    private onAjaxCallAbort()
    {
        if (this.options && this.options.onAbort) {
            this.options.onAbort();
        }
    }

    /**
     * Handler for when the Ajax call experiences an error.
     * @private
     */
    private onAjaxCallError()
    {
        if (!this.options) {
            return;
        }

        let response = new AjaxCallResponse(this.responseText);

        if (this.options && this.options.onFinished) {
            this.options.onFinished(response);
        }

        if (this.options && this.options.onFailure) {
            this.options.onFailure(response);
        }
    }

    /**
     * Helper to build the query string for the request.
     * @protected
     */
    protected get queryString(): string
    {
        let queryString = "";
        let first = true;

        for (let pName in this.parameters) {
            if (!this.parameters.hasOwnProperty(pName)) {
                continue;
            }

            if (first) {
                queryString += "?";
                first = false;
            } else {
                queryString += "&";
            }

            queryString += encodeURIComponent(pName) + "=" + encodeURIComponent(this.parameters[pName]);
        }

        return queryString;
    }

    /**
     * Helper to build the body for the request.
     *
     * @param boundary The boundary text to use between body parts.
     * @protected
     */
    protected buildRequestBody(boundary: string): string
    {
        let body = "";

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
        return body;
    }

    /**
     * Add the common headers for all requests to a given request.
     *
     * @param request The request to add the headers to.
     * @protected
     */
    protected static setRequestCommonHeaders(request: XMLHttpRequest): void
    {
        for (const header of AjaxCall.commonHeaders) {
            request.setRequestHeader(header.name, header.value);
        }

        request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    }

    /**
     * Send the Ajax call.
     *
     * Builds and sends the XMLHttpRequest object.
     */
    public send(): void
    {
        let url = this.endpoint;

        this.m_xhr.addEventListener("load", () => {
            this.onAjaxCallLoad();
        }, true);

        this.m_xhr.addEventListener("abort", () => {
            this.onAjaxCallAbort();
        }, true);

        this.m_xhr.addEventListener("error", () => {
            this.onAjaxCallError();
        }, true);

        if (this.parameters) {
            url += this.queryString;
        }

        if (this.data) {
            // if we have data, POST it ...
            let boundary = "-o-o-o-bndy" + Date.now().toString(16) + "-o-o-o-";
            this.m_xhr.open("POST", url, true);
            this.m_xhr.setRequestHeader("Content-Type", "multipart\/form-data; boundary=" + boundary);
            AjaxCall.setRequestCommonHeaders(this.m_xhr);
            this.m_xhr.send(this.buildRequestBody(boundary));
        } else {
            // ... otherwise just GET the response
            this.m_xhr.open("GET", url, true);
            AjaxCall.setRequestCommonHeaders(this.m_xhr);
            this.m_xhr.send();
        }
    }
}
