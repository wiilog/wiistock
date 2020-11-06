<?php

namespace App\Twig;

use App\Entity\ParametrageGlobal;
use App\Service\FieldsParamService;
use App\Service\SpecificService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class AppExtension extends AbstractExtension {

    private $manager;
    private $userService;
    private $specificService;
    private $fieldsParamService;
    private $kernel;

    public function __construct(EntityManagerInterface $manager,
                                SpecificService $specificService,
                                FieldsParamService $fieldsParamService,
                                UserService $userService,
                                KernelInterface $kernel) {
        $this->manager = $manager;
        $this->userService = $userService;
        $this->specificService = $specificService;
        $this->fieldsParamService = $fieldsParamService;
        $this->kernel = $kernel;
    }

    public function getFunctions() {
        return [
            new TwigFunction('hasRight', [$this, 'hasRightFunction']),
            new TwigFunction('isCurrentClient', [$this, 'isCurrentClientNameFunction']),
            new TwigFunction('displayMenu', [$this, 'displayMenuFunction']),
            new TwigFunction('logo', [$this, 'logo']),
        ];
    }

    public function getFilters() {
        return [
            new TwigFilter('withoutExtension', [$this, 'withoutExtensionFilter']),
            new TwigFilter('isFieldRequired', [$this, 'isFieldRequiredFunction']),
            new TwigFilter('wordwrap', [$this, 'wordwrap']),
            new TwigFilter('ellipsis', [$this, 'ellipsis']),
        ];
    }

    public function hasRightFunction(string $menuCode, string $actionLabel) {
        return $this->userService->hasRightFunction($menuCode, $actionLabel);
    }

    public function isCurrentClientNameFunction(string $clientName) {
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
        $type = pathinfo($absolutePath, PATHINFO_EXTENSION);
        $content = base64_encode(file_get_contents($absolutePath));

        if($type == "svg") {
            $type = "svg+xml";
        }

        return "data:image/$type;base64,$content";
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
            case "mobile":
                $logo = $pgr->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO);
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

}
