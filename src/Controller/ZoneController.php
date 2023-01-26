<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Urgence;
use App\Entity\Zone;
use App\Service\CSVExportService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UrgenceService;
use App\Service\UserService;
use App\Service\ZoneService;
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
            ->setInventoryIndicator($request->request->get("inventoryIndicator") ?? null);

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
            $zone
                ->setName($data["name"])
                ->setDescription($data["description"])
                ->setInventoryIndicator($data["inventoryIndicator"] ?? null);

            $manager->persist($zone);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La zone {$zone->getName()} a bien été modifiée",
            ]);
        }

        throw new NotFoundHttpException();
    }

    #[Route("/api-supprimer", name: "zone_delete_api", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function deleteApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $zoneRepository = $manager->getRepository(Zone::class);
            $zone = $zoneRepository->find($data["id"]);

            return $this->json($this->renderView("zone/delete_content.html.twig", [
                "zone" => $zone
            ]));
        }

        throw new BadRequestHttpException();
    }

    #[Route("/supprimer", name: "zone_delete", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        $data = $request->request->all();
        $zoneRepository = $entityManager->getRepository(Zone::class);
        $zone = $zoneRepository->find($data['id']);

        foreach ($zone->getLocations() as $location){
            $location->setZone(null);
        }
        $entityManager->remove($zone);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "La zone a bien été supprimée."
        ]);
    }
}
