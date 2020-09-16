class Trans {
    static original(key) {
        if(TRANSLATIONS[key]) {
            return TRANSLATIONS[key].original;
        } else {
            return key;
        }
    }

    static translated(key) {
        if(TRANSLATIONS[key]) {
            return TRANSLATIONS[key].translated;
        } else {
            return key;
        }
    }
}
