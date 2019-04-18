<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;



use App\Entity\Action;
use App\Entity\Utilisateur;

use App\Repository\ActionRepository;
use Symfony\Component\Security\Core\Security;


class UserService
{

    /**
     * @var Utilisateur
     */
    private $user;

    /**
     * @var ActionRepository
     */
    private $actionRepository;


    public function __construct(Security $security, ActionRepository $actionRepository)
    {
        $this->user= $security->getUser();
        $this->actionRepository = $actionRepository;
    }


    public function getCurrentUser()
    {
        return $this->user;
    }

    public function getCurrentUserRole()
    {
        $role = null;

        $user = $this->user;
        if ($user) {
            $role = $user->getRole();
        }

        return $role;
    }

    public function hasRightFunction(string $menuCode, string $actionLabel = Action::YES)
    {
        $role = $this->getCurrentUserRole();
        $actions = $role->getActions();

        $thisAction = $this->actionRepository->findOneByMenuCodeAndLabel($menuCode, $actionLabel);

        if ($thisAction) {
            foreach ($actions as $action) {
                if ($action->getId() == $thisAction->getId()) return true;
            }
        }

        return false;
    }
}
