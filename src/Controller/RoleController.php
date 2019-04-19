<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use App\Repository\ActionRepository;
use App\Repository\MenuRepository;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/role")
 */
class RoleController extends AbstractController
{
    /**
     * @var RoleRepository
     */
    private $roleRepository;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

    /**
     * @var MenuRepository
     */
    private $menuRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(RoleRepository $roleRepository, ActionRepository $actionRepository, MenuRepository $menuRepository, UtilisateurRepository $utilisateurRepository, UserService$userService)
    {
        $this->roleRepository = $roleRepository;
        $this->actionRepository = $actionRepository;
        $this->menuRepository = $menuRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="role_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $menus = $this->menuRepository->findAll();

        return $this->render('role/index.html.twig', ['menus' => $menus]);
    }

    /**
     * @Route("/api", name="role_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest())
        {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $roles = $this->roleRepository->findAll();
            $rows = [];
            foreach ($roles as $role) {
                $url['edit'] = $this->generateUrl('role_api_edit', ['id' => $role->getId()]);

                $rows[] =
                    [
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
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="role_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $this->roleRepository->countByLabel($data['label']);

            if (!$labelExist) {

                $role = new Role();
                $role
                    ->setActive(true)
                    ->setLabel($data['label']);

                unset($data['label']);
                unset($data['elem']);

                foreach ($data as $menuAction => $isChecked) {
                    $menuActionArray = explode('/', $menuAction);
                    $menuCode = $menuActionArray[0];
                    $actionLabel = $menuActionArray[1];

                    $action = $this->actionRepository->findOneByMenuCodeAndLabel($menuCode, $actionLabel);

                    if ($action && $isChecked) {
                        $role->addAction($action);
                    }
                }
                $em->persist($role);
                $em->flush();

                return new JsonResponse();
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="role_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $role = $this->roleRepository->find($data);
            $menus = $this->menuRepository->findAll();

            // on liste les id des actions que possède le rôle
            $actionsIdOfRole = [];
            foreach ($role->getActions() as $action) {
                $actionsIdOfRole[] = $action->getId();
            }

            $json = $this->renderView('role/modalEditRoleContent.html.twig', [
                'role' => $role,
                'menus' => $menus,
                'actionsIdOfRole' => $actionsIdOfRole
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="role_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $role = $this->roleRepository->find($data['id']);

            $role->setLabel($data['label']);
            unset($data['label']);
            unset($data['elem']);
            unset($data['id']);

            foreach ($data as $menuAction => $isChecked) {
                $menuActionArray = explode('/', $menuAction);
                $menuCode = $menuActionArray[0];
                $actionLabel = $menuActionArray[1];

                $action = $this->actionRepository->findOneByMenuCodeAndLabel($menuCode, $actionLabel);

                if ($action) {
                    if ($isChecked) {
                        $role->addAction($action);
                    } else {
                        $role->removeAction($action);
                    }
                }
            }
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="role_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($roleId = (int)$data['role']) {

                $role = $this->roleRepository->find($roleId);

                // on vérifie que le rôle n'est plus attribué à aucun utilisateur
                $usedRole = $this->utilisateurRepository->countByRoleId($roleId);

                if ($usedRole > 0) {
                    return new JsonResponse(false); //TODO gérer retour msg erreur (rôle attribué ne peut pas être supprimé)
                }

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($role);
                $entityManager->flush();
                return new JsonResponse();
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
