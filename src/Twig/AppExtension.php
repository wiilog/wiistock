<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 12/04/2019
 * Time: 10:37
 */

namespace App\Twig;

use App\Entity\Action;

use App\Repository\ActionRepository;
use App\Repository\RoleRepository;

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
     * @var ActionRepository
     */
    private $actionRepository;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

	/**
	 * @var SpecificService
	 */
    private $specificService;


    public function __construct(SpecificService $specificService,
                                UserService $userService,
                                ActionRepository $actionRepository,
                                RoleRepository $roleRepository)
    {
        $this->userService = $userService;
        $this->actionRepository = $actionRepository;
        $this->roleRepository = $roleRepository;
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
            new TwigFilter('isFieldRequired', [$this, 'isFieldRequiredFunction']),
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
