<?php

namespace App\Service;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class LanguageService {

    /**
     * TODO WIIS-8159
     */
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public Security $security;

    #[Required]
    public CacheService $cacheService;

    /**
     * @param EntityManagerInterface|null $entityManager required when method is called in critical part which close the entity Manager.
     */
    public function getNewUserLanguage(?EntityManagerInterface $entityManager = null): ?Language {
        if (!$entityManager) {
            $entityManager = $this->entityManager;
        }
        $languageRepository = $entityManager->getRepository(Language::class);

        $selectedDefaultSlug = $this->getDefaultSlug($entityManager);
        $slug = Stream::from(Language::DEFAULT_LANGUAGE_TRANSLATIONS)
            ->flip()
            ->find(fn(string $defaultSlug) => $defaultSlug === $selectedDefaultSlug);
        return $languageRepository->findOneBy(['slug' => $slug ?? Language::FRENCH_SLUG]);
    }

    /**
     * @param EntityManagerInterface|null $entityManager required when method is called in critical part which close the entity Manager.
     */
    public function getDefaultLanguage(?EntityManagerInterface $entityManager = null): ?Language {
        if (!$entityManager) {
            $entityManager = $this->entityManager;
        }
        $languageRepository = $entityManager->getRepository(Language::class);
        return $languageRepository->findOneBy(["selected" => true])
            ?: $languageRepository->findOneBy(['slug' => Language::DEFAULT_LANGUAGE_SLUG]);
    }

    /**
     * @param EntityManagerInterface|null $entityManager required when method is called in critical part which close the entity Manager.
     */
    public function getDefaultSlug(?EntityManagerInterface $entityManager = null): string {
        if (!$entityManager) {
            $entityManager = $this->entityManager;
        }
        return $this->cacheService->get(CacheService::LANGUAGES, "default-language-slug", fn() => (
            $this
                ->getDefaultLanguage($entityManager)
                ->getSlug()
        ));
    }

    public function getLanguages(): array
    {
        $user = $this->security->getUser();
        $userId = $user->getId();

        return $this->cacheService->get(CacheService::LANGUAGES, "languagesSelector" . $userId, function () {
            $languages = $this->cacheService->get(CacheService::LANGUAGES, "languagesNotHidden", function () {
                $languageRepository = $this->entityManager->getRepository(Language::class);
                return $languageRepository->findBy(["hidden" => false]);
            });

            $user = $this->security->getUser();
            $mappedLanguages = [];
            /** @var Language $language */
            foreach ($languages as $language) {
                $mappedLanguages[] = $language->serialize($user);
            }

            return $mappedLanguages;
        });
    }

    /**
     * Renvoie le slug du language sélectionné par l'utilisateur
     * @return string
     */
    public function getCurrentUserLanguageSlug(): string {
        $user = $this->security->getUser();
        return $user->getLanguage()->getSlug();
    }

    public function getDateFormats() : array {
        $user = $this->security->getUser();

        return [
            'dateFormat' => Stream::from(Language::DATE_FORMATS)
                ->map(fn($format, $key) => [
                    "label" => $key,
                    "value" => $format
                ])
                ->toArray(),
            'value' => $user?->getDateFormat()
        ];
    }

}
