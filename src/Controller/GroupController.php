<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Group;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;

use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\GroupService;
use App\Service\PackService;
use App\Service\TrackingMovementService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
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
            $groupRepository = $manager->getRepository(Group::class);
            $natureRepository = $manager->getRepository(Nature::class);
            $group = $groupRepository->find($data['id']);

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

        $groupRepository = $manager->getRepository(Group::class);
        $natureRepository = $manager->getRepository(Nature::class);

        $group = $groupRepository->find($data["id"]);
        if ($group) {
            $group->setNature($natureRepository->find($data["nature"]))
                ->setWeight($data["weight"])
                ->setVolume($data["volume"])
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
            $groupRepository = $manager->getRepository(Group::class);
            $group = $groupRepository->find($data['id']);

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
                            TrackingMovementService $trackingMovementService): Response {
        $data = json_decode($request->getContent(), true);

        $groupRepository = $manager->getRepository(Group::class);
        $locationRepository = $manager->getRepository(Emplacement::class);

        $group = $groupRepository->find($data["id"]);
        if ($group) {
            $location = $locationRepository->find($data["location"]);

            foreach ($group->getPacks() as $pack) {
                $pack->setGroup(null);

                $deposit = $trackingMovementService->createTrackingMovement(
                    $pack,
                    $location,
                    $this->getUser(),
                    new DateTime(),
                    false,
                    null,
                    TrackingMovement::TYPE_DEPOSE,
                    []
                );

                $ungroup = $trackingMovementService->createTrackingMovement(
                    $pack,
                    $location,
                    $this->getUser(),
                    new DateTime(),
                    false,
                    null,
                    TrackingMovement::TYPE_UNGROUP,
                    []
                );

                $manager->persist($deposit);
                $manager->persist($ungroup);
            }

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
    public function exportGroups(Request $request,
                                 CSVExportService $CSVExportService,
                                 TrackingMovementService $trackingMovementService,
                                 TranslatorInterface $translator,
                                 EntityManagerInterface $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                'Numéro groupe',
                $translator->trans('natures.Nature de colis'),
                'Date du dernier mouvement',
                'Nombre de colis',
                'Poids',
                'Volume',
                'Issu de',
                'Issu de (numéro)',
                'Emplacement',
            ];

            return $CSVExportService->streamResponse(
                function($output) use ($CSVExportService, $translator, $entityManager, $dateTimeMin, $dateTimeMax, $trackingMovementService) {
                    $groupRepository = $entityManager->getRepository(Group::class);
                    $groups = $groupRepository->getByDates($dateTimeMin, $dateTimeMax);
                    $trackingMouvementRepository = $entityManager->getRepository(TrackingMovement::class);

                    foreach ($groups as $group) {
                        $trackingMouvment = $trackingMouvementRepository->find($group['fromTo']);
                        $mvtData = $trackingMovementService->getFromColumnData($trackingMouvment);
                        $group['fromLabel'] = $translator->trans($mvtData['fromLabel']);
                        $group['fromTo'] = $mvtData['from'];
                        $this->putPackLine($output, $CSVExportService, $group);
                    }
                }, 'export_groupes.csv',
                $csvHeader
            );
        }
    }

    private function putPackLine($handle, CSVExportService $csvService, array $group) {
        $csvService->putLine($handle, [
            $group["code"],
            $group["nature"],
            FormatHelper::datetime($group["lastMvtDate"]),
            $group["packs"],
            $group["weight"],
            $group["volume"],
            $group["fromLabel"],
            $group["fromTo"],
            $group["location"]
        ]);
    }

}
