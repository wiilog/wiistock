<?php

namespace App\Service;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class LanguageService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Security $security;

    #[Required]
    public CacheService $cacheService;

    public function getLanguages(): array
    {
        $user = $this->security->getUser();
        $userId = $user->getId();

        return $this->cacheService->get(CacheService::LANGUAGES, "languagesSelector" . $userId, function () {
            $languages = $this->cacheService->get(CacheService::LANGUAGES, "languagesNotHidden", function () {
                return $this->manager->getRepository(Language::class)->findBy(["hidden" => false]);
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

        return ['dateFormat' => Stream::from(Language::DATE_FORMATS)
            ->map(fn($format, $key) => [
                "label" => $key,
                "value" => $format
            ])
            ->toArray(),
            'value' => $user?->getDateFormat()];
    }

}
