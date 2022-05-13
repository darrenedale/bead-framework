/**
 * Encapsulates a standardised response from an Ajax call.
 */
class AjaxCallResponse
{
    /**
     * The response status code.
     */
    public readonly code: number = NaN;

    /**
     * The response status message.
     */
    public readonly message: string = "";

    /**
     * The response payload.
     */
    public readonly data: string = "";

    /**
     * Initialise a new response from some content received.
     * @param responseBody The content received.
     */
    public constructor(responseBody: string)
    {
        const lines = responseBody.split("\n");
        const codeLine = lines[0].match(/^([0-9]+)(?: (.*))?$/);

        if (!codeLine || 3 !== codeLine.length) {
            console.warn("invalid response: first line must contain response code and optional message");
            return;
        }

        this.code = parseInt(codeLine[1]);
        this.message = codeLine[2];

        if ("string" !== typeof this.message) {
            this.message = "";
        }

        lines.shift();
        this.data = lines.join("\n");
    }

    /**
     * Check whether the response is valid.
     */
    public isValid(): boolean
    {
        return !isNaN(this.code);
    }
}
