<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Nature;

use App\Entity\Pack;
use App\Service\CSVExportService;
use App\Service\GroupService;
use App\Service\TrackingMovementService;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use App\Service\TranslationService;
use Throwable;

/**
 * @Route("/groupes")
 */
class GroupController extends AbstractController {

    /**
     * @Route("/api", name="group_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_PACK}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, GroupService $groupService): Response {
        return $this->json($groupService->getDataForDatatable($request->request));
    }

    /**
     * @Route("/api-modifier", name="group_edit_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $packRepository = $manager->getRepository(Pack::class);
            $natureRepository = $manager->getRepository(Nature::class);
            $group = $packRepository->find($data['id']);

            return $this->json($this->renderView("group/edit_content.html.twig", [
                'natures' => $natureRepository->findBy([], ['label' => 'ASC']),
                'group' => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="group_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        $packRepository = $manager->getRepository(Pack::class);
        $natureRepository = $manager->getRepository(Nature::class);

        $group = $packRepository->find($data["id"]);
        if ($group) {
            $group->setNature($natureRepository->find($data["nature"]))
                ->setWeight($data["weight"] === "" ? null : $data["weight"])
                ->setVolume($data["volume"] === "" ? null : $data["volume"])
                ->setComment($data["comment"]);

            $manager->persist($group);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Le groupe {$group->getCode()} a bien été modifié",
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/api-degrouper", name="group_ungroup_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function ungroupApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $packRepository = $manager->getRepository(Pack::class);
            $group = $packRepository->find($data['id']);

            return $this->json($this->renderView("group/ungroup_content.html.twig", [
                "group" => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/degrouper", name="group_ungroup", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function ungroup(Request $request,
                            EntityManagerInterface $manager,
                            GroupService $groupService): Response {
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

    /**
     * @Route("/csv", name="export_groups", options={"expose"=true}, methods={"GET"})
     * @HasPermission({Menu::TRACA, Action::EXPORT})
     */
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
                $translationService->translate( 'Traçabilité', 'Unités logistiques', 'Onglet "Groupes"', 'Numéro groupe', false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Divers', "Nature d'unité logistique", false),
                $translationService->translate( 'Traçabilité', 'Général', 'Date dernier mouvement', false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Onglet "Groupes"', "Nombre d'UL", false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Divers', "Poids (kg)", false),
                $translationService->translate('Traçabilité', 'Unités logistiques', 'Divers', "Volume (m3)", false),
                $translationService->translate( 'Traçabilité', 'Général', 'Issu de', false),
                $translationService->translate( 'Traçabilité', 'Général', 'Issu de (numéro)', false),
                $translationService->translate( 'Traçabilité', 'Général', 'Emplacement', false),
            ];

            return $CSVExportService->streamResponse(
                function($output) use ($CSVExportService, $entityManager, $dateTimeMin, $dateTimeMax, $trackingMovementService) {
                    $packRepository = $entityManager->getRepository(Pack::class);
                    $groups = $packRepository->getGroupsByDates($dateTimeMin, $dateTimeMax);

                    foreach ($groups as $groupData) {
                        /** @var Pack $group */
                        $group = $groupData['group'];
                        $trackingData = $trackingMovementService->getFromColumnData($group->getLastTracking());
                        $trackingLocation = $group->getLastTracking() ? $group->getLastTracking()->getEmplacement() : null;
                        $trackingDate = $group->getLastTracking() ? $group->getLastTracking()->getDatetime() : null;

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
        }
        else {
            throw new InvalidArgumentException('Date should be set.');
        }
    }

}
