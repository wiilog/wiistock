<?php

namespace App\Helper;

use App\Entity\Language;

class LanguageHelper {
    public static function clearLanguage(Language|string|null $language): ?string {
        $slug = $language instanceof Language ? $language->getSlug() : $language;

        return match($slug) {
            Language::FRENCH_DEFAULT_SLUG => Language::FRENCH_SLUG,
            Language::ENGLISH_DEFAULT_SLUG => Language::ENGLISH_SLUG,
            default => $slug,
        };
    }
}
