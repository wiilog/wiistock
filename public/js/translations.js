class Trans {
    static original(key) {
        return "BUG TICKET : " + key;
    }

    static translated(key) {
        return "BUG TICKET " + key;
    }
}

class Translation {
    static of(category, menu, submenu, translation) {
        return Translation.fetch(TRANSLATIONS, category, menu, submenu, translation) || Translation.fetch(DEFAULT_TRANSLATIONS, category, menu, submenu, translation);
    }

    static fetch(repository, category = null, menu = null, submenu = null, translation = null) {
        const transCategory = TRANSLATIONS[category];
        if(typeof transCategory !== `object`) {
            return transCategory || translation || submenu || menu || category;
        }

        const transMenu = transCategory[menu];
        if(typeof transMenu !== `object`) {
            return transMenu || translation || submenu || menu;
        }

        const transSubmenu = transMenu[submenu];
        if(typeof transSubmenu !== `object`) {
            return transSubmenu || translation || submenu;
        }

        return transSubmenu[translation] || translation;
    }
}
