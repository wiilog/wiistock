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
use App\Repository\UtilisateurRepository;
use App\Repository\RoleRepository;
use Symfony\Component\Security\Core\Security;


class UserService
{
     /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var RoleRepository
     */
    private $roleRepository;
    /**
     * @var Utilisateur
     */
    private $user;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

     /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;


    public function __construct( \Twig_Environment $templating, RoleRepository $roleRepository, UtilisateurRepository $utilisateurRepository, Security $security, ActionRepository $actionRepository)
    {
        $this->user= $security->getUser();
        $this->actionRepository = $actionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->roleRepository = $roleRepository;
        $this->templating = $templating;
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

    public function getDataForDatatable($params = null)
    {
        $data = $this->getUtilisateurDataByParams($params);
        $data['recordsTotal'] = (int)$this->utilisateurRepository->countAll();
        return $data;
    }

    /**
     * @param null $params
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getUtilisateurDataByParams($params = null)
    {
        $utilisateurs = $this->utilisateurRepository->findByParams($params);

        $rows = [];
        foreach ($utilisateurs as $utilisateur) {
            $rows[] = $this->dataRowUtilisateur($utilisateur);
        }
        return ['data' => $rows];
    }

    /**
     * @param Utilisateur $utilisateur
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function dataRowUtilisateur($utilisateur)
    {
        $idUser = $utilisateur->getId();
        $roles = $this->roleRepository->findAll();
        
        $row = [
            
                'id' => ($utilisateur->getId() ? $utilisateur->getId() : 'Non défini'),
                "Nom d'utilisateur" => ($utilisateur->getUsername() ? $utilisateur->getUsername() : ''),
                'Email' => ($utilisateur->getEmail() ? $utilisateur->getEmail() : ''),
                'Dernière connexion' => ($utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : ''),
                'Rôle' => $this->templating->render('utilisateur/role.html.twig', ['utilisateur' => $utilisateur, 'roles' => $roles]),
                            'Actions' => $this->templating->render(
                                'utilisateur/datatableUtilisateurRow.html.twig',
                                [
                                    'idUser' => $idUser,
                                ]
                            ),
                        ];
           
        return $row;
    }
}



// $utilisateurs = $this->utilisateurRepository->findAll();
            // $roles = $this->roleRepository->findAll();

            // $rows = [];
            // foreach ($utilisateurs as $utilisateur) {
            //     $idUser = $utilisateur->getId();
            //     $rows[] =
            //         [
            //             'id' => ($utilisateur->getId() ? $utilisateur->getId() : 'Non défini'),
            //             "Nom d'utilisateur" => ($utilisateur->getUsername() ? $utilisateur->getUsername() : ''),
            //             'Email' => ($utilisateur->getEmail() ? $utilisateur->getEmail() : ''),
            //             'Dernière connexion' => ($utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : ''),
            //             'Rôle' => $this->renderView('utilisateur/role.html.twig', ['utilisateur' => $utilisateur, 'roles' => $roles]),
            //             'Actions' => $this->renderView(
            //                 'utilisateur/datatableUtilisateurRow.html.twig',
            //                 [
            //                     'idUser' => $idUser,
            //                 ]
            //             ),
            //         ];
            // }
            // $data['data'] = $rows;
