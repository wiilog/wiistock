<?php

namespace App\Twig;

use App\Entity\ParametrageGlobal;
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
        ];
    }

    public function getFilters() {
        return [
            new TwigFilter('withoutExtension', [$this, 'withoutExtensionFilter']),
            new TwigFilter('isFieldRequired', [$this, 'isFieldRequiredFunction']),
            new TwigFilter('wordwrap', [$this, 'wordwrap']),
            new TwigFilter('ellipsis', [$this, 'ellipsis']),
            new TwigFilter("format_helper", [$this, "formatHelper"]),
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
        $pgr = $this->manager->getRepository(ParametrageGlobal::class);

        switch($platform) {
            case "website":
                $logo = $pgr->getOneParamByLabel(ParametrageGlobal::WEBSITE_LOGO);
                break;
            case "email":
                $logo = $pgr->getOneParamByLabel(ParametrageGlobal::EMAIL_LOGO);
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
        return constant(ParametrageGlobal::class . "::" . $setting);
    }

    public function settingValue($setting, $class = null) {

        if(!isset($this->settingsCache[$setting])) {
            $repository = $this->manager->getRepository(ParametrageGlobal::class);
            $this->settingsCache[$setting] = $repository->getOneParamByLabel($this->setting($setting));
        }

        $value = $this->settingsCache[$setting];

        if($class) {
            return $this->manager->find($class, $value);
        } else {
            return $value;
        }
    }

}
