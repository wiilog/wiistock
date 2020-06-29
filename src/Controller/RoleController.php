<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\MenuRepository;
use App\Service\RoleService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
     * @var MenuRepository
     */
    private $menuRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(MenuRepository $menuRepository,
                                UserService $userService)
    {
        $this->menuRepository = $menuRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="role_index")
     * @param RoleService $roleService
     * @return RedirectResponse|Response
     */
    public function index(RoleService $roleService)
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_ROLE)) {
            return $this->redirectToRoute('access_denied');
        }

        $templateParameters = $roleService->createFormTemplateParameters();
        return $this->render('role/index.html.twig', $templateParameters);
    }

    /**
     * @Route("/api", name="role_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function api(EntityManagerInterface $entityManager,
                        Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_ROLE)) {
                return $this->redirectToRoute('access_denied');
            }

            $roleRepository = $entityManager->getRepository(Role::class);

            $roles = $roleRepository->findAllExceptNoAccess();
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
     * @param Request $request
     * @param RoleService $roleService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function new(Request $request,
                        RoleService $roleService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $roleRepository = $entityManager->getRepository(Role::class);
            $parametreRepository = $entityManager->getRepository(Parametre::class);

            // on vérifie que le label n'est pas déjà utilisé
            $labelExist = $roleRepository->countByLabel($data['label']);

            if (!$labelExist) {
                $role = new Role();
                $role
                    ->setActive(true)
                    ->setLabel($data['label']);
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

                return new JsonResponse();
            } else {
                return new JsonResponse(false);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="role_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param RoleService $roleService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEdit(Request $request,
                            RoleService $roleService,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $roleRepository = $entityManager->getRepository(Role::class);
            $role = $roleRepository->find($data['id']);

            // on liste les id des actions que possède le rôle
            $actionsIdOfRole = array_map(
                function (Action $action) {
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
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="role_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param RoleService $roleService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function edit(Request $request,
                         RoleService $roleService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $roleRepository = $entityManager->getRepository(Role::class);
            $parametreRoleRepository = $entityManager->getRepository(ParametreRole::class);
            $parametreRepository = $entityManager->getRepository(Parametre::class);

            $role = $roleRepository->find($data['id']);

            $role->setLabel($data['label']);
            unset($data['label']);
            unset($data['elem']);
            unset($data['id']);

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
            return new JsonResponse($role->getLabel());
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="role_check_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function checkRoleCanBeDeleted(Request $request,
                                          EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $roleId = json_decode($request->getContent(), true)) {

            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="role_delete",  options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $roleRepository = $entityManager->getRepository(Role::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $resp = false;

            if ($roleId = (int)$data['role']) {
                $role = $roleRepository->find($roleId);

                if ($role) {
                    $nbUsers = $utilisateurRepository->countByRoleId($roleId);

                    if ($nbUsers == 0) {
                        $entityManager->remove($role);
                        $entityManager->flush();
                        $resp = true;
                    }
                }
            }
            return new JsonResponse($resp);
        }
        throw new NotFoundHttpException("404");
    }
}
