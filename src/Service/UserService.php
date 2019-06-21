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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;


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


    public function __construct(\Twig_Environment $templating, RoleRepository $roleRepository, UtilisateurRepository $utilisateurRepository, Security $security, ActionRepository $actionRepository)
    {
        $this->user = $security->getUser();
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
        $user = $this->user;

        $role = $user ? $user->getRole() : null;

        return $role;
    }

    public function hasRightFunction(string $menuCode, string $actionLabel = Action::YES)
    {
        $role = $this->getCurrentUserRole();
		$actions = $role ? $role->getActions() : [];

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
        $data['recordsFiltered'] = (int)$this->utilisateurRepository->countAll();
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

    public function checkPassword($password, $password2)
    {
        if ($password !== $password2) {
            $response = false;
            $message = 'Les mots de passe ne correspondent pas.';
            //TODO gérer retour erreur propre
        }
        elseif (strlen($password) < 8) {
            $response = false;
            $message = 'Le mot de passe doit faire au moins 8 caractères.';
            //TODO gérer retour erreur propre
        }
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $response = false;
            $message = 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial.';
            //TODO gérer retour erreur propre
        }
        else{
            $response = true ;
            $message = '';
        }
        return $result [] = array ('response' => $response , 'message' => $message);
    }
}
