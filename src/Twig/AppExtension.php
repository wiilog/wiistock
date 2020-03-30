<?php

namespace App\Twig;

use App\Service\SpecificService;
use App\Service\UserService;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class AppExtension extends AbstractExtension
{
    /**
     * @var UserService
     */
    private $userService;

	/**
	 * @var SpecificService
	 */
    private $specificService;


    public function __construct(SpecificService $specificService,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->specificService = $specificService;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('hasRight', [$this, 'hasRightFunction']),
            new TwigFunction('isCurrentClient', [$this, 'isCurrentClientNameFunction']),
			new TwigFunction('displayMenu', [$this, 'displayMenuFunction'])
        ];
    }

    public function getFilters()
	{
		return [
			new TwigFilter('withoutExtension', [$this, 'withoutExtensionFilter']),
            new TwigFilter('isFieldRequired', [$this, 'isFieldRequiredFunction'])
		];
	}

	public function hasRightFunction(string $menuCode, string $actionLabel)
    {
		return $this->userService->hasRightFunction($menuCode, $actionLabel);
    }

    public function isCurrentClientNameFunction(string $clientName)
	{
		return $this->specificService->isCurrentClientNameFunction($clientName);
	}

    public function withoutExtensionFilter(string $filename)
	{
		$array = explode('.', $filename);
		return $array[0];
	}

	public function isFieldRequiredFunction(array $config, string $fieldName, string $action): bool {
        return isset($config[$fieldName]) && isset($config[$fieldName][$action]) && $config[$fieldName][$action];
    }
}
