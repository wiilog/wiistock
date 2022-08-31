class Trans {
    static original(key) {
        return "BUG TICKET : " + key;
    }

    static translated(key) {
        return "BUG TICKET " + key;
    }
}

class Translation {
    static FRENCH_SLUG = `french`;
    static FRENCH_DEFAULT_SLUG = `french-default`;
    static ENGLISH_SLUG = `english`;
    static ENGLISH_DEFAULT_SLUG = `english-default`;

    static slug;
    static defaultSlug = DEFAULT_SLUG;

    static of(...args) {
        Translation.slug = $(`#language`).val();

        let defaultSlug;
        if(Translation.slug === Translation.FRENCH_SLUG) {
            defaultSlug = Translation.FRENCH_DEFAULT_SLUG;
        } else if(Translation.slug === Translation.ENGLISH_SLUG) {
            defaultSlug = Translation.ENGLISH_DEFAULT_SLUG;
        } else {
            defaultSlug = Translation.defaultSlug;
        }

        const trans = Translation.fetch(Translation.slug, defaultSlug, false, ...args);

        if(trans) {
            return trans;
        } else if(defaultSlug === Translation.FRENCH_SLUG) {
            return Translation.fetch(Translation.FRENCH_SLUG, defaultSlug, false, ...args)
                || Translation.fetch(Translation.FRENCH_DEFAULT_SLUG, defaultSlug, false, ...args);
        } else {
            return Translation.fetch(Translation.ENGLISH_SLUG, defaultSlug, false, ...args)
                || Translation.fetch(Translation.ENGLISH_DEFAULT_SLUG, defaultSlug, false, ...args);
        }
    }

    static fetch(slug, defaultSlug, lastResort, ...args) {
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
            if (typeof arg === 'object') {
                params = arg;
            } else if(typeof arg === `boolean`) {
                enableTooltip = arg;
            } else {
                if(variables.length === 0) {
                    throw new Error(`Too many arguments, expected at most 4 strings, 1 array and 1 boolean`);
                }

                stack[variables.shift()] = arg
            }
        }

        let output = null;


        const transCategory = TRANSLATIONS[slug][stack.category];

        if(typeof transCategory !== `object`) {
            output = transCategory || (lastResort ? stack.translation || stack.submenu || stack.menu || stack.category : null);
        }

        let transMenu = null;
        if(output === null) {
            transMenu = transCategory[stack.menu];
            if (typeof transMenu !== `object`) {
                output = transMenu || (lastResort ? stack.translation || stack.submenu || stack.menu : null);
            }
        }

        let transSubmenu = null;
        if(output === null) {
            transSubmenu = transMenu[stack.submenu];
            if (typeof transSubmenu !== `object`) {
                output = transSubmenu || (lastResort ? stack.translation || stack.submenu : null);
            }
        }

        if(output === null) {
            output = transSubmenu[stack.translation] || (lastResort ? stack.translation : null);
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

    static escape(unsafe) {
        return unsafe
            .replace(/&/g, `&amp;`)
            .replace(/</g, `&lt;`)
            .replace(/>/g, `&gt;`)
            .replace(/"/g, `&quot;`)
            .replace(/'/g, `&#039;`);
    }
}
