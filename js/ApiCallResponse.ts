export class ApiCallResponse {
    public readonly code: number = NaN;
    public readonly message: string = "";
    public readonly data: string = "";

    public constructor(responseBody: string) {
        let lines = responseBody.split("\n");
        let codeLine = lines[0].match(/^([0-9]+)(?: (.*))?$/);

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

    public isValid(): boolean {
        return !isNaN(this.code);
    }
}
