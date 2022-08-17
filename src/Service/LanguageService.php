<?php

namespace App\Service;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;

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

}
