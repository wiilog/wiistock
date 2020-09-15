class Trans {
    static original(key) {
        if(translations[key]) {
            return translations[key].original;
        } else {
            return key;
        }
    }

    static translated(key) {
        if(translations[key]) {
            return translations[key].translated;
        } else {
            return key;
        }
    }
}
