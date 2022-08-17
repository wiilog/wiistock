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
        const item = TRANSLATIONS[category || ``][menu || ``][submenu || ``];
        if(Array.isArray(item)) {
            return item[translation || ``] || translation;
        } else {
            return item || translation;
        }
    }
}
