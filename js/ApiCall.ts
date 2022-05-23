/// <reference path="./AjaxCallResponse.ts" />
/// <reference path="./AjaxCall.ts" />

/**
 * @deprecated use AjaxCallOptions instead.
 */
type ApiCallOptions = AjaxCallOptions;

/**
 * @deprecated use AjaxCallResponse instead.
 */
class ApiCallResponse extends AjaxCallResponse
{
}

/**
 * Encapsulates a call to an API endpoint.
 *
 * @deprecated use AjaxCall instead.
 */
class ApiCall extends AjaxCall
{
    private m_action: string;

    /**
     * Initialise a new API call.
     *
     * @param action The action parameter identifying the API call.
     * @param parameters The URL parameters for the call.
     * @param data The POST data to send with the call.
     * @param options Options for the call.
     */
    constructor(action: string, parameters: object = null, data: object = null, options: ApiCallOptions = null)
    {
        super(Application.instance.baseUrl, parameters, data, options);
        this.m_action = action;
    }

    /**
     * The action URL parameter that identifies the API call.
     */
    get action(): string
    {
        return this.m_action;
    }

    /**
     * Set the action URL parameter that identifies the API call.
     */
    set action(action: string)
    {
        if ("" == action) {
            throw new TypeError("action must not be empty");
        }

        this.m_action = action;
    }

    /**
     * Helper to build the query string for the request.
     * @protected
     */
    protected get queryString(): string
    {
        let queryString = "?action=" + encodeURIComponent(this.action);

        for (let pName in this.parameters) {
            if (!this.parameters.hasOwnProperty(pName)) {
                continue;
            }

            queryString += "&" + encodeURIComponent(pName) + "=" + encodeURIComponent(this.parameters[pName]);
        }

        return queryString;
    }
}
