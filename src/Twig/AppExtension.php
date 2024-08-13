<?php

namespace App\Twig;

use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\OperationHistory\ProductionHistoryRecord;
use App\Entity\OperationHistory\TransportHistoryRecord;
use App\Entity\Setting;
use App\Entity\Utilisateur;
use App\Service\FixedFieldService;
use App\Service\FormatService;
use App\Service\LanguageService;
use App\Service\OperationHistoryService;
use App\Service\SettingsService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use WiiCommon\Helper\Stream;

class AppExtension extends AbstractExtension {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public UserService $userService;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public FixedFieldService $fieldsParamService;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public OperationHistoryService $operationHistoryService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public SettingsService $settingsService;

    private array $settingsCache = [];

    public function getFunctions(): array {
        return [
            new TwigFunction('hasRight', [$this, 'hasRightFunction']),
            new TwigFunction('isCurrentClient', [$this, 'isCurrentClientNameFunction']),
            new TwigFunction('displayMenu', [$this, 'displayMenuFunction']),
            new TwigFunction('base64', [$this, 'base64']),
            new TwigFunction('logo', [$this, 'logo']),
            new TwigFunction('image_size', [$this, 'imageSize']),
            new TwigFunction('class', [$this, 'class']),
            new TwigFunction('setting', [$this, 'setting']),
            new TwigFunction('setting_value', [$this, 'settingValue']),
            new TwigFunction('call', [$this, 'call']),
            new TwigFunction('interleave', [$this, 'interleave']),
            new TwigFunction('formatHistory', [$this, 'formatHistory']),
            new TwigFunction('isImage', [$this, 'isImage']),
            new TwigFunction('merge', "array_merge"),
            new TwigFunction('diff', "array_diff"),
            new TwigFunction('getLanguage', [$this, "getLanguage"]),
            new TwigFunction('getDefaultLanguage', [$this, "getDefaultLanguage"]),
            new TwigFunction('getSettingTimestamp', [$this, "getSettingTimestamp"]),
            new TwigFunction('trans', [$this, "translate"], [
                "is_safe" => ["html"]
            ]),
            new TwigFunction('translateIn', [$this, "translateIn"], [
                "is_safe" => ["html"]
            ])
        ];
    }

    public function getFilters(): array {
        return [
            new TwigFilter('withoutExtension', [$this, 'withoutExtensionFilter']),
            new TwigFilter('isFieldRequired', [$this, 'isFieldRequiredFunction']),
            new TwigFilter('wordwrap', [$this, 'wordwrap']),
            new TwigFilter('ellipsis', [$this, 'ellipsis']),
            new TwigFilter("format_helper", [$this, "formatHelper"]),
            new TwigFilter("json_decode", "json_decode"),
            new TwigFilter("flip", [$this, "flip"]),
            new TwigFilter("unique", [$this, "unique"]),
            new TwigFilter("some", [$this, "some"]),
            new TwigFilter("ucfirst", "ucfirst"),
            new TwigFilter("transFreeFieldElements", [$this, "transFreeFieldElements"]),
        ];
    }

    public function getTests(): array {
        return [
            new TwigTest('instanceof', [$this, 'isInstanceOf']),
        ];
    }

    public function hasRightFunction(string $menuCode, string $actionLabel): bool {
        return $this->userService->hasRightFunction($menuCode, $actionLabel);
    }

    /**
     * @param string[]|string $clientName
     * @return bool
     */
    public function isCurrentClientNameFunction($clientName): bool {
        return $this->specificService->isCurrentClientNameFunction($clientName);
    }

    public function withoutExtensionFilter(string $filename): string {
        $array = explode('.', $filename);
        return $array[0];
    }

    public function isFieldRequiredFunction(array $config, string $fieldName, string $action): bool {
        return $this->fieldsParamService->isFieldRequired($config, $fieldName, $action);
    }

    public function base64(string $relativePath): string {
        $absolutePath = $this->kernel->getProjectDir() . "/$relativePath";
        if (file_exists($absolutePath)) {
            $type = pathinfo($absolutePath, PATHINFO_EXTENSION);
            $content = base64_encode(file_get_contents($absolutePath));

            if ($type == "svg") {
                $type = "svg+xml";
            }
            $res = "data:image/$type;base64,$content";
        }
        else {
            $res = '';
        }
        return $res;
    }

    public function imageSize(string $file): ?array {
        try {
            return getimagesize("{$this->kernel->getProjectDir()}/$file");
        } catch(Throwable $exception) {
            return null;
        }
    }

    public function logo(string $platform, bool $path = false): ?string {
        switch($platform) {
            case "website":
                $logo = $this->settingsService->getValue($this->manager, Setting::FILE_WEBSITE_LOGO);
                break;
            case "email":
                $logo = $this->settingsService->getValue($this->manager, Setting::FILE_EMAIL_LOGO);
                break;
            default:
                break;
        }

        if(isset($logo)) {
            if($path) {
                return "public/$logo";
            } else {
                return $_SERVER["APP_URL"] . "/$logo";
            }
        } else {
            return null;
        }
    }

    public function wordwrap(string $value, int $length): string|Markup {
        if(strlen($value) > $length) {
            return new Markup(implode("<br>", str_split($value, $length)), "UTF-8");
        } else {
            return $value;
        }
    }

    public function ellipsis(string $value, int $length): string {
        if(strlen($value) > $length) {
            return str_split($value, $length)[0] . "...";
        } else {
            return $value;
        }
    }

    public function formatHelper($input, string $formatter, ...$options): ?string {
        return $this->formatService->{$formatter}($input, ...$options);
    }

    public function call($function) {
        return $function();
    }

    public function class($object): string {
        return (new ReflectionClass($object))->getName();
    }

    public function setting($setting) {
        return constant(Setting::class . "::" . $setting);
    }

    public function settingValue($setting, $class = null): mixed {
        if (!isset($this->settingsCache[$setting])) {
            $this->settingsCache[$setting] = $this->settingsService->getValue($this->manager, $this->setting($setting));
            if ($class && $this->settingsCache[$setting]) {
                $this->settingsCache[$setting] = $this->manager->find($class, $this->settingsCache[$setting]);
            } else if ($class) {
                return null;
            }
        }
        return $this->settingsCache[$setting];
    }

    public function interleave(array $array1, array $array2): array {
        $results = [];
        $array1Keys = array_keys($array1);
        $array2Keys = array_keys($array2);

        for ($i = 0; $i < count(max($array1, $array2)); $i++) {
            if(isset($array1Keys[$i])) {
                $results[$array1Keys[$i]] = $array1[$array1Keys[$i]];
            }
            if(isset($array2Keys[$i])) {
                $results[$array2Keys[$i]] = $array2[$array2Keys[$i]];
            }
        }

        return $results;
    }

    public function isInstanceOf($entity, string $class): bool {
        $reflexionClass = new ReflectionClass($class);
        return is_object($entity) && $reflexionClass->isInstance($entity);
    }

    public function flip(array $array): array {
        return array_flip($array);
    }

    public function unique(array $array): array {
        return array_unique($array);
    }

    public function formatHistory(ProductionHistoryRecord|TransportHistoryRecord $history): ?string {
        return $this->operationHistoryService->formatHistory($history);
    }

    public function some(Collection|array $array, callable $callback): bool {
        return Stream::from($array)->some($callback);
    }

    public function isImage(string $path): bool {
        try {
            $imagesize = getimagesize($this->kernel->getProjectDir() . '/' . $path);
            $imageType = $imagesize[2] ?? null;
            return in_array($imageType, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP]);
        }
        catch (Throwable) {
            return false;
        }
    }

    public function getLanguage(){
        return $this->userService->getUser()?->getLanguage()
            ?? $this->manager->getRepository(Language::class)->findOneBy(['selected' => true]);
    }

    public function translate(mixed... $args): string {
        return $this->translationService->translate(...$args);
    }

    public function translateIn(mixed... $args): string {
        return $this->translationService->translateIn(...$args);
    }

    public function getDefaultLanguage(): string {
        $defaultSlugLanguage = $this->languageService->getDefaultSlug();
        return $this->languageService->getReverseDefaultLanguage($defaultSlugLanguage);
    }

    /**
     * Get values of a free field list (multiple or not) (in French) and translate it in user language
     */
    public function transFreeFieldElements($values, FreeField $freeField, ?Utilisateur $user = null): string|array|null {
        if ($values) {
            $user = $user ?: $this->userService->getUser();
            $userLanguage = $user?->getLanguage();
            $defaultLanguage = $this->languageService->getDefaultSlug();
            $isFill = !empty($values);
            $translation = $this->translationService->translateFreeFieldListValues(Language::FRENCH_SLUG, $userLanguage, $freeField, $values);
            return $translation
                // if free field is not translated in userLanguage ?
                ?: ($isFill && $userLanguage !== $defaultLanguage
                    ? $this->translationService->translateFreeFieldListValues(Language::FRENCH_SLUG, $defaultLanguage, $freeField, $values)
                    : null);
        }
        return null;
    }

    public function getSettingTimestamp(): string {
        return $this->settingsService->getTimestamp();
    }
}
