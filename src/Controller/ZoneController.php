<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\PurchaseRequestScheduleRule;
use App\Entity\Zone;
use App\Service\ZoneService;
use App\Exceptions\FormException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * @Route("/zones")
 */
class ZoneController extends AbstractController
{
    #[Route("/creer", name: "zone_new", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $zoneRepository = $manager->getRepository(Zone::class);

        $sameName = $zoneRepository->findOneBy(["name" => $request->request->get("name")]);
        if ($sameName) {
            return $this->json([
                "success" => false,
                "msg" => "Une zone avec le même nom existe déjà",
            ]);
        }

        $zone = (new Zone())
            ->setName($request->request->get("name"))
            ->setDescription($request->request->get("description"))
            ->setInventoryIndicator($request->request->get("inventoryIndicator") ?? null)
            ->setActive($request->request->getBoolean("active"));

        $manager->persist($zone);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La zone {$zone->getName()} a bien été créée",
        ]);
    }

    #[Route("/api", name: "zones_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function api(Request $request, ZoneService $zoneService): Response
    {
        $data = $zoneService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    #[Route("/api-modifier", name: "zone_edit_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $zoneRepository = $manager->getRepository(Zone::class);
            $zone = $zoneRepository->find($data['id']);

            return $this->json($this->renderView("zone/form.html.twig", [
                'zone' => $zone
            ]));
        }

        throw new BadRequestHttpException();
    }

    #[Route("/modifier", name: "zone_edit", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request, EntityManagerInterface $manager): Response {
        $data = $request->request->all();

        $zoneRepository = $manager->getRepository(Zone::class);
        $zone = $zoneRepository->find($data["id"]);
        if ($zone) {
            if (!$data['active']) {
                $locationRepository = $manager->getRepository(Emplacement::class);
                $purchaseRequestScheduleRuleRepository = $manager->getRepository(PurchaseRequestScheduleRule::class);
                $issue = ($locationRepository->isLocationInNotDoneInventoryMission($zone) ? ' une mission d’inventaire en cours, ' : '')
                    . ($locationRepository->isLocationInZoneInventoryMissionRule($zone) ? ' une planification d’inventaire, ' : '')
                    . ( $purchaseRequestScheduleRuleRepository->isZoneInPurchaseRequestScheduleRule($zone) ? ' une planification de demande d’achat, ' : '');

                if ($issue) {
                    throw new FormException("La zone ou ses emplacements sont contenus dans" . $issue . "vous ne pouvez donc pas la désactiver");
                }
            }

            if ($data["name"] != $zone->getName() && $zone === $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME])) {
                throw new FormException("Vous ne pouvez pas renommer la zone " . $zone->getName());
            }

            $zone
                ->setName($data["name"])
                ->setDescription($data["description"])
                ->setInventoryIndicator($data["inventoryIndicator"] ?? null)
                ->setActive($data['active']);

            $manager->persist($zone);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La zone {$zone->getName()} a bien été modifiée",
            ]);
        }

        throw new NotFoundHttpException();
    }

    #[Route("/{zone}/delete", name: "zone_delete", options: ["expose" => true], methods: "DELETE", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Zone                   $zone,
                           EntityManagerInterface $entityManager): Response {
        $purchaseRequestScheduleRuleRepository = $entityManager->getRepository(PurchaseRequestScheduleRule::class);

        if (!$zone->getLocations()->isEmpty()){
            throw new FormException("Vous ne pouvez pas supprimer cette zone car elle est liée à un ou plusieurs emplacements. Vous pouvez la rendre inactive en modifiant la zone.");
        }

        $purchaseRequestCounter = $purchaseRequestScheduleRuleRepository->count(['zones' => $zone]);
        if ($purchaseRequestCounter > 0){
            throw new FormException("Vous ne pouvez pas supprimer cette zone car elle est lié à une ou plusieurs planifications de demandes d'achat. Vous pouvez la rendre inactive en modifiant la zone.");
        }

        $entityManager->remove($zone);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "La zone a bien été supprimée."
        ]);
    }
}
