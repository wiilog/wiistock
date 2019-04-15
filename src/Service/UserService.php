<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;



use App\Entity\Utilisateur;

use Symfony\Component\Security\Core\Security;


class UserService
{

    /**
     * @var Utilisateur
     */
    private $user;


    public function __construct(Security $security)
    {

        $this->user= $security->getUser();

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
}
