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
        console.log(category, menu, submenu, translation, Translation.fetch(TRANSLATIONS, category, menu, submenu, translation));
        return Translation.fetch(TRANSLATIONS, category, menu, submenu, translation) || Translation.fetch(DEFAULT_TRANSLATIONS, category, menu, submenu, translation);
    }

    static fetch(repository, category, menu, submenu, translation) {
        const item = TRANSLATIONS[category || ``][menu || ``][submenu || ``];
        if(typeof item === `object`) {
            return item[translation || ``] || translation;
        } else {
            return item || translation;
        }
    }
}
