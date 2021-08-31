<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Service\VisibilityGroupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
}
