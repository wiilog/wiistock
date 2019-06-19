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
use App\Repository\ParamClientRepository;
use App\Repository\RoleRepository;
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
	 * @var ParamClientRepository
	 */
    private $paramClientRepository;


    public function __construct(ParamClientRepository $paramClientRepository, UserService $userService, ActionRepository $actionRepository, RoleRepository $roleRepository)
    {
        $this->userService = $userService;
        $this->actionRepository = $actionRepository;
        $this->roleRepository = $roleRepository;
        $this->paramClientRepository = $paramClientRepository;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('hasRight', [$this, 'hasRightFunction']),
            new TwigFunction('isCurrentClient', [$this, 'isCurrentClientNameFunction'])
        ];
    }

    public function getFilters()
	{
		return [
			new TwigFilter('withoutExtension', [$this, 'withoutExtensionFilter'])
		];
	}

	public function hasRightFunction(string $menuCode, string $actionLabel = Action::YES)
    {
		return $this->userService->hasRightFunction($menuCode, $actionLabel);
    }

    public function isCurrentClientNameFunction(string $clientName)
	{
		$currentClient = $this->paramClientRepository->findOne();
		return $currentClient->getClient() == $clientName;
	}

    public function withoutExtensionFilter(string $filename)
	{
		$array = explode('.', $filename);
		return $array[0];
	}
}