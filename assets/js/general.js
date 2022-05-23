import IncreaseDecreaseField from "@app/increase-decrease-field";

export default class Wiistock {
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
        Wiistock.registerNumberInputProtection()
    }

    static registerNumberInputProtection() {
        const forbiddenChars = [
            "e",
            "E",
            "+",
            "-"
        ];

        $(document).on(`keydown`, `input[type=number]`, function (e) {
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
