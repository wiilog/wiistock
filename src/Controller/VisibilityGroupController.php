<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\VisibilityGroup;
use App\Service\VisibilityGroupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/groupes-visibilite")
 */
class VisibilityGroupController extends AbstractController
{
    /**
     * @Route("/liste", name="visibility_group_index", methods="GET")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function index(): Response {
        return $this->render('visibility_group/index.html.twig');
    }

    /**
     * @Route("/api", name="visibility_group_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function api(EntityManagerInterface $entityManager,
                        VisibilityGroupService $visibilityGroupService,
                        Request $request): JsonResponse {
        $visibilityGroups = $visibilityGroupService->getDataForDatatable($entityManager, $request->request);
        return $this->json($visibilityGroups);
    }

    /**
     * @Route("/verification", name="visibility_group_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function checkDelete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($groupID = json_decode($request->getContent(), true)) {
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
            $group = $visibilityGroupRepository->find($groupID);
            $safe = $group->getArticleReferences()->isEmpty() && $group->getUsers()->isEmpty();
            if ($safe) {
                $html = $this->renderView('visibility_group/modals/delete.correct.html.twig');
            } else {
                $html = $this->renderView('visibility_group/modals/delete.incorrect.html.twig');
            }

            return new JsonResponse(['delete' => $safe, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="visibility_group_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
            $visibilityGroupID = $data['group'] ?? null;
            if ($visibilityGroupID) {
                $visibilityGroup = $visibilityGroupRepository->find($visibilityGroupID);
                $entityManager->remove($visibilityGroup);
                $entityManager->flush();
            }
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="visibility_group_new", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $visibilityGroup = new VisibilityGroup();
            $visibilityGroup
                ->setLabel($data['label'])
                ->setActive(true)
                ->setDescription($data['description']);
            $entityManager->persist($visibilityGroup);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="visibility_group_edit", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS})
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);

            $visibilityGroup = $visibilityGroupRepository->find($data['id']);
            $visibilityGroup
                ->setLabel($data['label'])
                ->setActive($data['active'])
                ->setDescription($data['description']);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="visibility_group_api_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_VISIBILITY_GROUPS}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);

            $visibilityGroup = $visibilityGroupRepository->find($data['id']);
            $json = $this->renderView('visibility_group/modals/form.content.html.twig', [
                'visibilityGroup' => $visibilityGroup,
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }
}
