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
use App\Service\UserService;
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


    public function __construct(UserService $userService, ActionRepository $actionRepository, RoleRepository $roleRepository)
    {
        $this->userService = $userService;
        $this->actionRepository = $actionRepository;
        $this->roleRepository = $roleRepository;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('hasRight', [$this, 'hasRightFunction'])
        ];
    }

    public function hasRightFunction(string $menuCode, string $actionLabel = Action::YES)
    {
        $role = $this->userService->getCurrentUserRole();
        $actions = $role->getActions();

        $thisAction = $this->actionRepository->findOneByMenuCodeAndLabel($menuCode, $actionLabel);

        foreach($actions as $action) {
            if ($action->getId() == $thisAction->getId()) return true;
        }

        return false;
    }
}