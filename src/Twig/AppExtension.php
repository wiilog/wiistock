<?php

namespace App\Twig;

use App\Entity\Setting;
use App\Service\FieldsParamService;
use App\Service\SpecificService;
use App\Service\UserService;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class AppExtension extends AbstractExtension {

    /**
     * @Required
     */
    public EntityManagerInterface $manager;

    /**
     * @Required
     */
    public UserService $userService;

    /**
     * @Required
     */
    public SpecificService $specificService;

    /**
     * @Required
     */
    public FieldsParamService $fieldsParamService;

    /**
     * @Required
     */
    public KernelInterface $kernel;

    private array $settingsCache = [];

    public function getFunctions() {
        return [
            new TwigFunction('hasRight', [$this, 'hasRightFunction']),
            new TwigFunction('isCurrentClient', [$this, 'isCurrentClientNameFunction']),
            new TwigFunction('displayMenu', [$this, 'displayMenuFunction']),
            new TwigFunction('logo', [$this, 'logo']),
            new TwigFunction('class', [$this, 'class']),
            new TwigFunction('setting', [$this, 'setting']),
            new TwigFunction('setting_value', [$this, 'settingValue']),
            new TwigFunction('call', [$this, 'call']),
            new TwigFunction('interleave', [$this, 'interleave']),
        ];
    }

    public function getFilters() {
        return [
            new TwigFilter('withoutExtension', [$this, 'withoutExtensionFilter']),
            new TwigFilter('isFieldRequired', [$this, 'isFieldRequiredFunction']),
            new TwigFilter('wordwrap', [$this, 'wordwrap']),
            new TwigFilter('ellipsis', [$this, 'ellipsis']),
            new TwigFilter("format_helper", [$this, "formatHelper"]),
            new TwigFilter("json_decode", "json_decode"),
        ];
    }

    public function hasRightFunction(string $menuCode, string $actionLabel) {
        return $this->userService->hasRightFunction($menuCode, $actionLabel);
    }

    /**
     * @param string[]|string $clientName
     * @return bool
     */
    public function isCurrentClientNameFunction($clientName) {
        return $this->specificService->isCurrentClientNameFunction($clientName);
    }

    public function withoutExtensionFilter(string $filename) {
        $array = explode('.', $filename);
        return $array[0];
    }

    public function isFieldRequiredFunction(array $config, string $fieldName, string $action): bool {
        return $this->fieldsParamService->isFieldRequired($config, $fieldName, $action);
    }

    public function base64(string $relativePath) {
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

    public function logo(string $platform, bool $file = false): ?string {
        $pgr = $this->manager->getRepository(Setting::class);

        switch($platform) {
            case "website":
                $logo = $pgr->getOneParamByLabel(Setting::WEBSITE_LOGO);
                break;
            case "email":
                $logo = $pgr->getOneParamByLabel(Setting::EMAIL_LOGO);
                break;
            default:
                break;
        }

        if(isset($logo)) {
            if($file) {
                return $this->base64("public/$logo");
            } else {
                return $_SERVER["APP_URL"] . "/$logo";
            }
        } else {
            return null;
        }
    }

    public function wordwrap(string $value, int $length) {
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

    public function formatHelper($input, string $formatter, ...$options): string {
        return FormatHelper::{$formatter}($input, ...$options);
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

    public function settingValue($setting, $class = null) {

        if (!isset($this->settingsCache[$setting])) {
            $repository = $this->manager->getRepository(Setting::class);
            $this->settingsCache[$setting] = $repository->getOneParamByLabel($this->setting($setting));
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
}
