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
     * Language given to a new user if not selected
     * TODO WIIS-8159 add EntityManager (impacts dans le FormatService)
     */
    public function getNewUserLanguage(): ?Language {
        $languageRepository = $this->entityManager->getRepository(Language::class);

        $selectedDefaultSlug = $this->getDefaultSlug();
        $slug = Stream::from(Language::DEFAULT_LANGUAGE_TRANSLATIONS)
            ->flip()
            ->find(fn(string $defaultSlug) => $defaultSlug === $selectedDefaultSlug);
        return $languageRepository->findOneBy(['slug' => $slug ?? Language::FRENCH_SLUG]);
    }

    /**
     * Fallback language for element not translated
     * TODO WIIS-8159 add EntityManager (impacts dans le FormatService)
     */
    public function getDefaultLanguage(): ?Language {
        $languageRepository = $this->entityManager->getRepository(Language::class);
        return $languageRepository->findOneBy(["selected" => true])
            ?: $languageRepository->findOneBy(['slug' => Language::DEFAULT_LANGUAGE_SLUG]);
    }

    /**
     * TODO WIIS-8159 add EntityManager (impacts dans le FormatService)
     */
    public function getDefaultSlug(): string {
        return $this->cacheService->get(CacheService::LANGUAGES, "default-language-slug", fn() => (
            $this
                ->getDefaultLanguage()
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
