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
    #[Route("/api-degrouper", name: "ungroup_api", options: ['expose' => true], methods: [self::POST, self::GET])]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function ungroupApi(Request                $request,
                               EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $packRepository = $manager->getRepository(Pack::class);
            $group = $packRepository->find($data['id']);

            return $this->json($this->renderView("group/ungroup_content.html.twig", [
                "group" => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    #[Route("/degrouper", name: "ungroup", options: ['expose' => true], methods: [self::POST], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::TRACA, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function ungroup(Request                $request,
                            EntityManagerInterface $manager,
                            GroupService           $groupService): Response {
        $data = json_decode($request->getContent(), true);

        $packRepository = $manager->getRepository(Pack::class);
        $locationRepository = $manager->getRepository(Emplacement::class);

        $group = $packRepository->find($data["id"]);
        if ($group) {
            $location = $locationRepository->find($data["location"]);

            $groupService->ungroup($manager, $group, $location);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Groupe dégrouppé avec succès",
            ]);
        }

        throw new NotFoundHttpException();
    }

    #[Route("/csv", name: "export", options: ['expose' => true], methods: [self::GET])]
    #[HasPermission([Menu::TRACA, Action::EXPORT])]
    public function exportGroups(Request                 $request,
                                 CSVExportService        $CSVExportService,
                                 TrackingMovementService $trackingMovementService,
                                 TranslationService      $translationService,
                                 EntityManagerInterface  $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $csvHeader = [
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Groupes"', 'Numéro groupe', false),
                $translationService->translate('Traçabilité', 'Général', 'Nature', false),
                $translationService->translate('Traçabilité', 'Général', 'Date dernier mouvement', false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Groupes"', "Nombre d'UL", false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Général', "Poids (kg)", false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Général', "Volume (m3)", false),
                $translationService->translate('Traçabilité', 'Général', 'Issu de', false),
                $translationService->translate('Traçabilité', 'Général', 'Issu de (numéro)', false),
                $translationService->translate('Traçabilité', 'Général', 'Emplacement', false),
            ];

            return $CSVExportService->streamResponse(
                function ($output) use ($CSVExportService, $entityManager, $dateTimeMin, $dateTimeMax, $trackingMovementService) {
                    $packRepository = $entityManager->getRepository(Pack::class);
                    $groups = $packRepository->getGroupsByDates($dateTimeMin, $dateTimeMax);

                    foreach ($groups as $groupData) {
                        /** @var Pack $group */
                        $group = $groupData['group'];
                        $trackingData = $trackingMovementService->getFromColumnData($group->getLastAction());
                        $trackingLocation = $group->getLastAction() ? $group->getLastAction()->getEmplacement() : null;
                        $trackingDate = $group->getLastAction() ? $group->getLastAction()->getDatetime() : null;

                        $CSVExportService->putLine($output, [
                            $group->getCode(),
                            $this->getFormatter()->nature($group->getNature()),
                            $this->getFormatter()->datetime($trackingDate),
                            $groupData['packCounter'],
                            $group->getWeight(),
                            $group->getVolume(),
                            $trackingData['fromLabel'],
                            $trackingData["from"],
                            $this->getFormatter()->location($trackingLocation),
                        ]);
                    }
                }, 'export_groupes.csv',
                $csvHeader
            );
        } else {
            throw new InvalidArgumentException('Date should be set.');
        }
    }

}
