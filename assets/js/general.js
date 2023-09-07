import IncreaseDecreaseField from "@app/increase-decrease-field";
import Select2 from "@app/select2";
import Select2Old from "@app/select2-old";

export default class Wiistock {
    static Select2 = Select2;
    static Select2Old = Select2Old;

    static download(url) {
        let isFirefox = navigator.userAgent.includes("Firefox");

        if(isFirefox) {
            window.open(url);
        } else {
            window.location.href = url;
        }
    }

    static initialize() {
        IncreaseDecreaseField.initialize();
        Wiistock.registerNumberInputProtection();
    }

    static registerNumberInputProtection() {
        const forbiddenChars = [
            "e",
            "E",
            "+",
            "-"
        ];

        $(document).on(`keydown`, `input[type=number]`, function (e) {
            if($(this).is(`[data-negative]`)) {
                const dashIndex = forbiddenChars.findIndex(token => token === '-');
                forbiddenChars.splice(dashIndex, 1);
            }
            const step = Number($(this).attr(`step`));
            if(step % 1 === 0 && (e.key === `,` || e.key === `.`)) {
                e.preventDefault();
            }

            if (forbiddenChars.includes(e.key)) {
                e.preventDefault();
            }
        });
    }
}
