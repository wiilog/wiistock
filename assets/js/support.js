export default class BrowserSupport {
    static input(type) {
        if(type === "datetime-local") {
            const input = document.createElement("input");
            input.setAttribute("type", "datetime-local");
            input.setAttribute("value", "a");

            return input.value !== "a";
        }
    }
}
