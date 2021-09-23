<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\RoleService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/role")
 */
class RoleController extends AbstractController {

    /** @Required */
    public UserService $userService;

    /**
     * @Route("/", name="role_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_ROLE})
     */
    public function index(RoleService $roleService) {
        $templateParameters = $roleService->createFormTemplateParameters();
        return $this->render('role/index.html.twig', $templateParameters);
    }

    /**
     * @Route("/api", name="role_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_ROLE}, mode=HasPermission::IN_JSON)
     */
    public function api(EntityManagerInterface $entityManager): Response {

        $roleRepository = $entityManager->getRepository(Role::class);

        $roles = $roleRepository->findAllExceptNoAccess();
        $rows = [];
        foreach ($roles as $role) {
            $url['edit'] = $this->generateUrl('role_api_edit', ['id' => $role->getId()]);

            $rows[] = [
                'id' => $role->getId() ? $role->getId() : "Non défini",
                'Nom' => $role->getLabel() ? $role->getLabel() : "Non défini",
                'Actif' => $role->getActive() ? 'oui' : 'non',
                'Actions' => $this->renderView('role/datatableRoleRow.html.twig', [
                    'url' => $url,
                    'roleId' => $role->getId(),
                ]),
            ];
        }
        $data['data'] = $rows;
        return $this->json($data);
    }

    /**
     * @Route("/creer", name="role_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, RoleService $roleService, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $roleRepository = $entityManager->getRepository(Role::class);
            $parametreRepository = $entityManager->getRepository(Parametre::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $roleRepository->countByLabel($data['label']);

            if (!$labelExist) {
                $role = new Role();
                $role
                    ->setActive(true)
                    ->setLabel($data['label'])
                    ->setIsMailSendAccountCreation($data['role/isMailSendAccountCreation']);
                $entityManager->persist($role);

                unset($data['label']);
                unset($data['elem']);

                // on traite les paramètres
                foreach (array_keys($data) as $id) {
                    if (is_numeric($id)) {
                        $parametre = $parametreRepository->find($id);

                        $paramRole = new ParametreRole();
                        $paramRole
                            ->setParametre($parametre)
                            ->setRole($role)
                            ->setValue($data[$id]);
                        $entityManager->persist($paramRole);
                        $entityManager->flush();
                        $role->addParametreRole($paramRole);
                        unset($data[$id]);
                    }
                }

                $roleService->parseParameters($role, $data);
                $entityManager->flush();

                return $this->json([
                    "success" => true,
                    "msg" => "Le rôle <strong>{$role->getLabel()}</strong> a bien été créé."
                ]);
            } else {
                return $this->json([
                    "success" => false,
                    "msg" => "Le rôle <strong>{$data['label']}</strong> existe déjà, veuillez choisir un autre nom."
                ]);
            }
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="role_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            RoleService $roleService,
                            EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $roleRepository = $entityManager->getRepository(Role::class);
            $role = $roleRepository->find($data['id']);

            // on liste les id des actions que possède le rôle
            $actionsIdOfRole = array_map(
                function(Action $action) {
                    return $action->getId();
                },
                $role->getActions()->toArray()
            );

            $templateParameters = $roleService->createFormTemplateParameters($role);
            $templateParameters['role'] = $role;
            $templateParameters['actionsIdOfRole'] = $actionsIdOfRole;

            $json = $this->renderView('role/modalEditRoleContent.html.twig', $templateParameters);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="role_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         RoleService $roleService,
                         EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $roleRepository = $entityManager->getRepository(Role::class);
            $parametreRoleRepository = $entityManager->getRepository(ParametreRole::class);
            $parametreRepository = $entityManager->getRepository(Parametre::class);

            $role = $roleRepository->find($data['id']);

            $role->setLabel($data['label']);
            unset($data['label']);
            unset($data['elem']);
            unset($data['id']);

            $role->setIsMailSendAccountCreation($data['role/isMailSendAccountCreation']);

            // on traite les paramètres
            foreach (array_keys($data) as $id) {
                if (is_numeric($id)) {
                    $parametre = $parametreRepository->find($id);

                    $paramRole = $parametreRoleRepository->findOneByRoleAndParam($role, $parametre);
                    if (!$paramRole) {
                        $paramRole = new ParametreRole();
                        $entityManager->persist($paramRole);
                    }
                    $paramRole
                        ->setParametre($parametre)
                        ->setRole($role)
                        ->setValue($data[$id]);
                    $entityManager->flush();
                    unset($data[$id]);
                }
            }
            $roleService->parseParameters($role, $data);
            $entityManager->flush();

            $roleService->onRoleUpdate($role->getId());

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le rôle <strong>' . $role->getLabel() . '</strong> a bien été modifié.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="role_check_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkRoleCanBeDeleted(Request $request,
                                          EntityManagerInterface $entityManager): Response {
        if ($roleId = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $nbUsers = $utilisateurRepository->countByRoleId($roleId);

            if ($nbUsers > 0) {
                $delete = false;
                $html = $this->renderView('role/modalDeleteRoleWrong.html.twig', ['nbUsers' => $nbUsers]);
            } else {
                $delete = true;
                $html = $this->renderView('role/modalDeleteRoleRight.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="role_delete",  options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           RoleService $roleService,
                           EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $roleRepository = $entityManager->getRepository(Role::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $resp = false;

            if ($roleId = (int)$data['role']) {
                $role = $roleRepository->find($roleId);

                if ($role) {
                    $nbUsers = $utilisateurRepository->countByRoleId($roleId);

                    if ($nbUsers == 0) {
                        $roleId = $role->getId();
                        $entityManager->remove($role);
                        $entityManager->flush();

                        $roleService->onRoleUpdate($roleId);

                        $resp = [
                            'success' => true,
                            'msg' => 'Le rôle <strong>' . $role->getLabel() . '</strong> a bien été supprimé.'
                        ];
                    }
                }
            }
            return new JsonResponse($resp);
        }
        throw new BadRequestHttpException();
    }

}
