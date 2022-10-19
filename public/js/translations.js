class TranslationClass {
    /*
    FRENCH_SLUG;
    FRENCH_DEFAULT_SLUG;
    ENGLISH_SLUG;
    ENGLISH_DEFAULT_SLUG;

    slug;
    defaultSlug;
*/
    constructor() {
        this.FRENCH_SLUG = `french`;
        this.FRENCH_DEFAULT_SLUG = `french-default`;
        this.ENGLISH_SLUG = `english`;
        this.ENGLISH_DEFAULT_SLUG = `english-default`;
        this.defaultSlug = DEFAULT_SLUG; // defined in generated/translations.js
    }

    /**
     * @param args Same as php method TranslationService::translate
     * @return {string}
     */
    of(...args) {
        Translation.slug = $(`#language`).val();

        let defaultSlug;
        if(Translation.slug === Translation.FRENCH_SLUG) {
            defaultSlug = Translation.FRENCH_DEFAULT_SLUG;
        } else if(Translation.slug === Translation.ENGLISH_SLUG) {
            defaultSlug = Translation.ENGLISH_DEFAULT_SLUG;
        } else {
            defaultSlug = Translation.defaultSlug;
        }

        return (
            Translation.fetch(Translation.slug, defaultSlug, false, ...args)
            || Translation.fetch(defaultSlug, defaultSlug, true, ...args)
        );
    }

    fetch(slug, defaultSlug, lastResort, ...args) {
        let enableTooltip = true;
        let params = null;

        const variables = [`category`, `menu`, `submenu`, `translation`]
        const stack = {
            category: null,
            menu: null,
            submenu: null,
            translation: null,
        };

        for(const arg of args) {
            if (typeof arg === 'object' && arg !== null) {
                params = arg;
            } else if(typeof arg === `boolean`) {
                enableTooltip = arg;
            } else {
                if(variables.length === 0) {
                    throw new Error(`Too many arguments, expected at most 4 strings, 1 array and 1 boolean`);
                }

                stack[variables.shift()] = arg || '';
            }
        }

        let output = null;

        const transCategory = TRANSLATIONS[slug][stack.category];

        if(typeof transCategory !== `object`) {
            output = transCategory || (lastResort ? stack.translation || stack.submenu || stack.menu || stack.category : null);
        }

        if(transCategory){
            let transMenu = null;
            if(output === null) {
                transMenu = transCategory[stack.menu];
                if (typeof transMenu !== `object`) {
                    output = transMenu || (lastResort ? stack.translation || stack.submenu || stack.menu : null);
                }
            }

            if(transMenu) {
                let transSubmenu = null;
                if (output === null) {
                    transSubmenu = transMenu[stack.submenu];
                    if (typeof transSubmenu !== `object`) {
                        output = transSubmenu || (lastResort ? stack.translation || stack.submenu : null);
                    }
                }

                if (transSubmenu) {
                    if (output === null) {
                        output = transSubmenu[stack.translation] || (lastResort ? stack.translation : null);
                    }
                }
            }
        }

        if(output === null) {
            return null;
        }

        if(params !== null) {
            for(const [key, value] of Object.entries(params)) {
                output = output.replaceAll(`{${key}}`, value);
            }
        }

        let tooltip;
        if(slug === defaultSlug) {
            tooltip = Translation.escape(output);
        } else {
            tooltip = Translation.escape(Translation.fetch(defaultSlug, defaultSlug, true, false, ...args));
        }

        return enableTooltip ? `<span title="${tooltip}">${output}</span>` : output;
    }

    escape(unsafe) {
        return unsafe
            .replace(/&/g, `&amp;`)
            .replace(/</g, `&lt;`)
            .replace(/>/g, `&gt;`)
            .replace(/"/g, `&quot;`)
            .replace(/'/g, `&#039;`);
    }
}

// for external dashboard
// we can't use static keywords
const Translation = new TranslationClass();
