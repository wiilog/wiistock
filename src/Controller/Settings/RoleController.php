<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\RoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage/utilisateurs/roles")
 */
class RoleController extends AbstractController {

    /**
     * @Route("/api", name="settings_role_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES})
     */
    public function api(EntityManagerInterface $entityManager): Response {
        $roleRepository = $entityManager->getRepository(Role::class);

        $roles = $roleRepository->findAllExceptNoAccess();

        return $this->json([
            'data' => Stream::from($roles)
                ->map(fn(Role $role) => [
                    'name' => $role->getLabel() ?: "Non défini",
                    'quantityType' => FormatHelper::quantityTypeLabel($role->getQuantityType()),
                    'isMailSendAccountCreation' => FormatHelper::bool($role->getIsMailSendAccountCreation()),
                    'actions' => $this->renderView('settings/utilisateurs/roles/actions.html.twig', [
                        'role' => $role
                    ]),
                ])
                ->toArray(),
            'recordsTotal' => count($roles),
        ]);
    }

    /**
     * @Route("/creer", name="settings_role_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, RoleService $roleService, EntityManagerInterface $entityManager): JsonResponse {
        $role = new Role();
        $res = $roleService->updateRole($entityManager, $role, $request);

        if ($res["success"]) {
            $entityManager->persist($role);
            $entityManager->flush();

            $roleService->onRoleUpdate($role->getId());

            $res['redirect'] = $this->generateUrl('settings_item', [
                'menu' => SettingsController::MENU_ROLES,
                'submenu' => null,
                'category' => SettingsController::CATEGORY_USERS
            ]);

            $this->addFlash('success', $res["message"]);
        }

        return $this->json($res);
    }

    /**
     * @Route("/creer", name="settings_role_new_form", methods="GET")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES}, mode=HasPermission::IN_JSON)
     */
    public function newForm(EntityManagerInterface $entityManager): Response {
        $menuRepository = $entityManager->getRepository(Menu::class);
        return $this->render('/settings/utilisateurs/roles/form.html.twig', [
            'role' => new Role(),
            'menus' => $menuRepository->findBy([], ["sorting" => "ASC"]),
        ]);
    }

    /**
     * @Route("/modifier/{role}", name="settings_role_edit",  options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         RoleService $roleService,
                         Role $role,
                         EntityManagerInterface $entityManager): JsonResponse {
        $res = $roleService->updateRole($entityManager, $role, $request);

        if ($res["success"]) {
            $entityManager->flush();

            $roleService->onRoleUpdate($role->getId());

            $res['redirect'] = $this->generateUrl('settings_item', [
                'menu' => SettingsController::MENU_ROLES,
                'submenu' => null,
                'category' => SettingsController::CATEGORY_USERS
            ]);

            $this->addFlash('success', $res["message"]);
        }

        return $this->json($res);
    }

    /**
     * @Route("/modifier/{role}", name="settings_role_edit_form", methods="GET")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES}, mode=HasPermission::IN_JSON)
     */
    public function editForm(EntityManagerInterface $entityManager, Role $role): Response {
        $menuRepository = $entityManager->getRepository(Menu::class);

        return $this->render('/settings/utilisateurs/roles/form.html.twig', [
            'role' => $role,
            'menus' => $menuRepository->findBy([], ["sorting" => "ASC"]),
        ]);
    }

    /**
     * @Route("/verification", name="settings_role_check_delete", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES}, mode=HasPermission::IN_JSON)
     */
    public function checkRoleCanBeDeleted(Request $request,
                                          EntityManagerInterface $entityManager): Response {
        if ($roleId = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $nbUsers = $utilisateurRepository->countByRoleId($roleId);

            if ($nbUsers > 0) {
                $delete = false;
                $sUser = $nbUsers > 1 ? 's' : '';
                $html = "
                    <p class='error-msg'>
                        Ce rôle est utilisé par ${nbUsers} utilisateur${sUser}.
                        Vous ne pouvez pas le supprimer.
                    </p>
                ";
            } else {
                $delete = true;
                $html = "<p>Voulez-vous réellement supprimer ce rôle ?</p>";
            }

            return new JsonResponse([
                'delete' => $delete,
                'html' => $html
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="settings_role_delete",  options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROLES}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           RoleService $roleService,
                           EntityManagerInterface $entityManager): Response {
        $id = json_decode($request->getContent())->role;
        $role = $entityManager->find(Role::class, $id);

        $label = $role->getLabel();

        $entityManager->remove($role);
        $entityManager->flush();

        $roleService->onRoleUpdate($id);

        return $this->json([
            'success' => true,
            'msg' => "Le rôle <strong>$label</strong> a bien été supprimé."
        ]);
    }
}
