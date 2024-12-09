<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Tracking\Pack;
use App\Service\CSVExportService;
use App\Service\GroupService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;


#[Route('/groupes', name: 'group_')]
class GroupController extends AbstractController {
    #[Route("/api-degrouper/{group}", name: "ungroup_api", options: ['expose' => true], methods: [self::POST, self::GET])]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function ungroupApi(Pack                   $group): Response {
        return $this->json([
            "success" => true,
            "html" => $this->renderView("group/ungroup_content.html.twig", [
                "group" => $group
            ]),
        ]);
    }

    #[Route("/degrouper", name: "ungroup", options: ['expose' => true], methods: [self::POST], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function ungroup(Request                $request,
                            EntityManagerInterface $manager,
                            GroupService           $groupService): Response {
        $data =$request->request;

        $packRepository = $manager->getRepository(Pack::class);
        $locationRepository = $manager->getRepository(Emplacement::class);

        $group = $packRepository->find($data->get("id"));
        if ($group) {
            $location = $locationRepository->find($data->get("location"));

            $groupService->ungroup($manager, $group, $location);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Groupe dégrouppé avec succès",
            ]);
        } else {
            throw new NotFoundHttpException();
        }
    }
}
