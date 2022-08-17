<?php

namespace App\Service;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class LanguageService
{
    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Security $security;

    public function getLanguages() : array {
        $languageRepository = $this->manager->getRepository(Language::class);

        $user = $this->security->getUser();
        $languages = $languageRepository->findAll();

        $mappedLanguages = [];
        foreach ($languages as $language) {
            $mappedLanguages[] = $language->serialize($user);
        }

        return $mappedLanguages;
    }

    public function getDateFormats() : array {
        $user = $this->security->getUser();

        return ['dateFormat' => Stream::from(Language::DATE_FORMATS)
            ->map(fn($format, $key) => [
                "label" => $key,
                "value" => $format
            ])
            ->toArray(),
            'value' => $user->getDateFormat()];
    }
}
