<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\LocationGroup;
use App\Entity\Menu;
use App\Entity\Nature;

use App\Entity\Pack;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\GroupService;
use App\Service\LocationGroupService;
use App\Service\TrackingMovementService;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @Route("/emplacements/groupes")
 */
class LocationGroupController extends AbstractController {

    /**
     * @Route("/api", name="location_group_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_EMPL}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, LocationGroupService $groupService): Response {
        return $this->json($groupService->getDataForDatatable($request->request));
    }

    /**
     * @Route("/creer", name="location_group_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);
        dump($data);

        $locationRepository = $manager->getRepository(Emplacement::class);
        $locationGroupRepository = $manager->getRepository(LocationGroup::class);

        $sameName = $locationGroupRepository->findOneBy(["name" => $data["name"]]);
        if ($sameName) {
            return $this->json([
                "success" => false,
                "msg" => "Un groupe d'emplacement avec le même nom existe déjà",
            ]);
        }

        $locations = $locationRepository->findBy(["id" => $data["locations"]]);

        $group = (new LocationGroup())
            ->setName($data["name"])
            ->setDescription($data["description"] ?? null)
            ->setLocations($locations);

        $manager->persist($group);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le groupe d'emplacements {$group->getName()} a bien été créé",
        ]);
    }

    /**
     * @Route("/api-modifier", name="location_group_edit_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $locationGroupRepository = $manager->getRepository(LocationGroup::class);
            $group = $locationGroupRepository->find($data['id']);

            return $this->json($this->renderView("location_group/edit_content.html.twig", [
                'group' => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="location_group_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);
        dump($data);

        $locationRepository = $manager->getRepository(Emplacement::class);
        $locationGroupRepository = $manager->getRepository(LocationGroup::class);

        $group = $locationGroupRepository->find($data["id"]);
        if ($group) {
            $locations = $locationRepository->findBy(["id" => $data["locations"]]);

            $group->setName($data["name"])
                ->setDescription($data["description"] ?? null)
                ->setLocations($locations);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Le groupe d'emplacements {$group->getName()} a bien été modifié",
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/api-supprimer", name="location_group_delete_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::TRACA, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $packRepository = $manager->getRepository(LocationGroup::class);
            $group = $packRepository->find($data["id"]);

            return $this->json($this->renderView("location_group/delete_content.html.twig", [
                "group" => $group
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="location_group_delete", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        $locationGroupRepository = $manager->getRepository(LocationGroup::class);

        $group = $locationGroupRepository->find($data["id"]);
        if ($group) {
            $manager->remove($group);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Groupe d'emplacements supprimé avec succès",
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/csv", name="export_location_groups", options={"expose"=true}, methods={"GET"})
     * @HasPermission({Menu::REFERENTIEL, Action::EXPORT})
     */
    public function exportGroups(Request $request,
                                 CSVExportService $CSVExportService,
                                 EntityManagerInterface $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        $csvHeader = [
            "Nom",
            "Description",
            "Emplacements",
        ];

        $locationGroupRepository = $entityManager->getRepository(LocationGroup::class);
        $groups = $locationGroupRepository->getGroupsByDates($dateTimeMin, $dateTimeMax);

        return $CSVExportService->streamResponse(function($output) use ($CSVExportService, $groups) {
            foreach ($groups as $groupData) {
                /** @var LocationGroup $group */
                $group = $groupData["group"];

                $CSVExportService->putLine($output, [
                    $group->getName(),
                    $group->getDescription(),
                    $group->getLocations()->count(),
                ]);
            }
        }, "export_groupes_emplacements.csv", $csvHeader);
    }

}
