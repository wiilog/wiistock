<?php

namespace App\Service;

use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use App\Entity\Utilisateur;
use App\Helper\LanguageHelper;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TranslationService {

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public TokenStorageInterface $tokenStorage;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    private array $translations = [];

    /**
     * Translates the given input
     * The function expects from 1 to 4 strings, then an array of
     * strings to replace or a user or both in any order.
     *
     * Example usage with more or less string inputs and with and without custom user and params array :
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Groupes")
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Groupes", $customUser)
     *
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Mouvementé la dernière fois le {1}", [1 => "DATE"])
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Mouvementé la dernière fois le {1}", [1 => "DATE"], $customUser)
     *
     * translate("Référentiel", "Natures", "Sélectionner une nature")
     * translate("Référentiel", "Natures", "Sélectionner une nature", $customUser)
     *
     * translate("Référentiel", "Natures", "La nature {1} a bien été créée", [1 => "NOMNATURE"])
     * translate("Référentiel", "Natures", "La nature {1} a bien été créée", [1 => "NOMNATURE"], $customUser)
     *
     * @param mixed ...$args Arguments
     * @return string Translated input
     */
    public function translate(mixed ...$args): string {
        $user = null;
        foreach($args as $arg) {
            if ($arg instanceof Utilisateur) {
                $user = $arg;
            }
        }

        if(!isset($user)) {
            $user = $this->tokenStorage->getToken()?->getUser();
        }

        $args = Stream::from($args)
            ->filter(fn($arg) => !$arg instanceof Utilisateur)
            ->toArray();

        $slug = $user?->getLanguage()?->getSlug() ?: $this->formatService->defaultLanguage();

        return $this->translateIn($slug, ...$args);
    }

    /**
     * Translates the given input with the given slug (1st parameter) ; Same usage of TranslateService::translate
     * The function expects from 1 to 4 strings, then an array of strings to replace or a user or both in any order.
     *
     *
     * Example usage with more or less string inputs and with and without custom user and params array :
     * translateIn($slug, "Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Groupes")
     *
     * @param mixed ...$args Arguments
     * @return string Translated input
     */
    public function translateIn(string $slug, mixed ...$args): string {
        $defaultSlug = Language::DEFAULT_LANGUAGE_TRANSLATIONS[$slug] ?? $this->languageService->getDefaultSlug();

        return $this->getTranslation($slug, $defaultSlug, false, ...$args)
            ?: $this->getTranslation($defaultSlug, $defaultSlug, true, ...$args);
    }

    /**
     * @param string $slug Target slug language
     * @param string $defaultSlug Fallback slug if translation does not exist
     * @param bool $lastResort if false and translation was not found then we return null, else if true then we return the original label to translate
     * @param mixed ...$args
     * @return string|null
     */
    private function getTranslation(string $slug, string $defaultSlug, bool $lastResort, mixed ...$args): ?string {
        $variables = ["category", "menu", "submenu", "input"];
        foreach($variables as $variable) {
            $$variable = null;
        }

        $enableTooltip = true;
        foreach($args as $arg) {
            if(is_array($arg)) {
                $params = $arg;
            }else if(is_bool($arg)) {
                $enableTooltip = $arg;
            } else if(!($arg instanceof Utilisateur)) {
                if(empty($variables)) {
                    throw new RuntimeException("Too many arguments, expected at most 4 strings, 1 array, 1 boolean and 1 user");
                }

                ${array_shift($variables)} = $arg;
            }
        }

        if(!isset($this->translations[$slug])) {
            $this->generateCache($slug);
            $this->generateJavascripts();
        }

        $transCategory = $this->translations[$slug][$category ?: null] ?? null;
        if(!is_array($transCategory)) {
            $output = $transCategory ?? ($lastResort ? ($input ?? $submenu ?? $menu ?? $category): null);
        }

        if(!isset($output)) {
            $transMenu = $transCategory[$menu ?: null] ?? null;
            if (!is_array($transMenu)) {
                $output = $transMenu ?? ($lastResort ? ($input ?? $submenu ?? $menu) : null);
            }
        }

        if(!isset($output)) {
            $transSubmenu = $transMenu[$submenu ?: null] ?? null;
            if (!is_array($transSubmenu)) {
                $output = $transSubmenu ?? ($lastResort ? ($input ?? $submenu) : null);
            }
        }

        if(!isset($output)) {
            $output = $transSubmenu[$input] ?? ($lastResort ? $input : null);
        }

        if($output === null) {
            return null;
        }

        if(isset($params)) {
            foreach($params as $key => $value) {
                $output = str_replace( '{' . $key . '}', $value, $output);
            }
        }

        if($slug === $defaultSlug) {
            $tooltip = htmlspecialchars($output);
        } else {
            $tooltip = htmlspecialchars($this->getTranslation($defaultSlug, $defaultSlug, true, false, ...$args));
        }

        return $enableTooltip ? "<span title='$tooltip'>$output</span>" : $output;
    }

    private function createCategoryStack(Translation $translation): array {
        $stack = [];

        $category = $translation->getSource()->getCategory();
        while($category) {
            $stack[] = $category;
            $category = $category->getParent();
        }

        return array_reverse($stack);
    }

    public function generateCache(?string $slug = null) {
        $languageRepository = $this->manager->getRepository(Language::class);
        $translationSourceRepository = $this->manager->getRepository(TranslationSource::class);

        $languages = $slug ? $languageRepository->findBy(["slug" => $slug]) : $languageRepository->findAll();
        $sources = $translationSourceRepository->findAll();

        /** @var Language $language */
        foreach($languages as $language) {
            $slug = $language->getSlug();
            $this->translations[$slug] = [];

            /** @var Translation $translation */
            foreach($sources as $source) {
                //no category means it's a translation for natures, types, etc
                if($source->getCategory() === null) {
                    continue;
                }

                $original = $source->getTranslationIn(Language::FRENCH_DEFAULT_SLUG);
                $translation = $source->getTranslationIn($slug) ?? null;

                $zoomedTranslations = &$this->translations[$slug];
                $stack = $translation ? $this->createCategoryStack($translation) : [];
                foreach($stack as $category) {
                    if(!isset($zoomedTranslations[$category->getLabel()])) {
                        $zoomedTranslations[$category->getLabel()] = [];
                    }

                    $zoomedTranslations = &$zoomedTranslations[$category->getLabel()];
                }

                if($translation) {
                    $zoomedTranslations[$original->getTranslation()] = $translation->getTranslation();
                }
            }

            $this->cacheService->set(CacheService::TRANSLATIONS, $slug, $this->translations[$slug]);
            if ($language->getSelected()) {
                $this->cacheService->set(CacheService::TRANSLATIONS, "default", $this->translations[$slug]);
            }
        }
    }

    public function generateJavascripts() {
        $languageRepository = $this->manager->getRepository(Language::class);
        $outputDirectory = "{$this->kernel->getProjectDir()}/public/generated";

        $languages = $languageRepository->findAll();

        /** @var Language $language */
        foreach($languages as $language) {
            $slug = $language->getSlug();
            if(!isset($this->translations[$slug])) {
                $this->generateCache($slug);
            }
        }

        $content = "//generated file for translations\n";
        $content .= "const DEFAULT_SLUG = `{$this->languageService->getDefaultSlug()}`;\n";
        $content .= "const TRANSLATIONS = " . json_encode($this->translations) . ";\n";

        file_put_contents("$outputDirectory/translations.js", $content);
    }

    public function editEntityTranslations(EntityManagerInterface $entityManager,
                                           TranslationSource      $source,
                                           array                  $labels,
                                           string                 $key = "label") {
        foreach ($labels as $label) {
            $labelLanguage = $entityManager->find(Language::class, $label["language-id"]);
            $currentTranslation = $source->getTranslationIn($labelLanguage);

            if(!($label[$key] ?? null)) {
                if($currentTranslation) {
                    $source->removeTranslation($currentTranslation);
                    $entityManager->remove($currentTranslation);
                }

                continue;
            }

            if (!$currentTranslation) {
                $newTranslation = new Translation();
                $newTranslation
                    ->setTranslation($label[$key] ?? '')
                    ->setSource($source)
                    ->setLanguage($labelLanguage);

                $source->addTranslation($newTranslation);
                $entityManager->persist($newTranslation);
            } else {
                $currentTranslation->setTranslation($label[$key]);
            }
        }
    }

    public function setFirstTranslation(EntityManagerInterface $entityManager,
                                        mixed                  $entity,
                                        string                 $firstLabel,
                                        string                 $setter = null) {
        $languageRepository = $entityManager->getRepository(Language::class);
        $language = $languageRepository->findOneBy(["slug" => Language::FRENCH_SLUG]);

        $labelTranslation = new TranslationSource();
        $entityManager->persist($labelTranslation);
        $frenchTranslation = new Translation();
        $entityManager->persist($frenchTranslation);

        $frenchTranslation
            ->setLanguage($language)
            ->setSource($labelTranslation)
            ->setTranslation($firstLabel);

        $entity->{$setter ?? "setLabelTranslation"}($labelTranslation);
    }

    /**
     * @param Language|string|(Language|string)[] $sources
     * @param bool $clearSources Clear given sources array to remove duplicates, only for an internal use for now
     */
    public function translateFreeFieldListValues(Language|string|array $sources,
                                                 Language|string       $target,
                                                 FreeField             $freeField,
                                                 array|string          $values,
                                                 bool                  $keepDefault = false,
                                                 bool                  $clearSources = true): array|string|null {

        $sources = !is_array($sources) ? [$sources] : $sources;
        if ($clearSources) {
            $sources = Stream::from($sources)
                ->map(fn(Language|string $lang) => LanguageHelper::clearLanguage($lang))
                ->unique()
                ->toArray();
        }

        $multipleTryMode = count($sources) > 1;
        $source = $sources[0];

        if (empty($values)) {
            $res = null;
        }
        else {
            $sourceElements = $freeField->getElementsIn($source);
            $targetElements = $freeField->getElementsIn($target, $this->languageService->getDefaultSlug());
            if (empty($sourceElements) || empty($targetElements)) {
                $res = null;
            }
            else {
                $sourceElementsStream = Stream::from($sourceElements);

                $getValue = function(Stream $sourceElementsStream, array $targetElements, string $value) use ($keepDefault) {
                    $fallbackValue = $keepDefault ? $value : null;
                    $key = $sourceElementsStream->findKey(fn($v) => trim($v) === trim($value));
                    return isset($key)
                        ? ($targetElements[$key] ?? $fallbackValue) // value is known in source language
                        : null;
                };

                // multiple list values
                if (is_array($values)) {
                    $res = Stream::from($values)
                        ->filterMap(fn(string $value) => $getValue($sourceElementsStream, $targetElements, $value))
                        ->toArray();
                }
                else {
                    // list values
                    $res = $getValue($sourceElementsStream, $targetElements, $values);
                }
            }
        }

        return empty($res) && $multipleTryMode
            ? $this->translateFreeFieldListValues(array_slice($sources, 1), $target, $freeField, $values, $keepDefault, false)
            : $res;
    }

}
