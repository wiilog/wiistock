<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Inventory\InventoryEntry;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\FormException;
use WiiCommon\Helper\Stream;
use App\Service\CSVExportService;
use App\Service\InventoryEntryService;
use App\Service\InventoryService;
use App\Service\InvMissionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/inventaire/mission")
 */
class InventoryMissionController extends AbstractController
{

    /**
     * @Route("/", name="inventory_mission_index")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE})
     */
    public function index(EntityManagerInterface $entityManager): Response {

        return $this->render('inventaire/index.html.twig', [
            'types' => Stream::from(InventoryMission::INVENTORY_TYPES)
                ->map(fn(String $type) => [
                    'id' => $type,
                    'label' => $type
                ])
                ->toArray(),
        ]);
    }

    /**
     * @Route("/api", name="inv_missions_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        InvMissionService $invMissionService): Response
    {
        $data = $invMissionService->getDataForMissionsDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="mission_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if ($data['startDate'] > $data['endDate'])
                return new JsonResponse([
                    'success' => false,
                    'msg' => "La date de début doit être antérieure à celle de fin."
                ]);

            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate(DateTime::createFromFormat('Y-m-d', $data['startDate']))
                ->setEndPrevDate(DateTime::createFromFormat('Y-m-d', $data['endDate']))
                ->setCreatedAt(new DateTime('now'))
                ->setName($data['name']);

            $requesterId = $data['requester'] ?? null;
            if ($requesterId) {
                $userRepository = $em->getRepository(Utilisateur::class);
                $requester = $userRepository->find($requesterId);
                $mission->setRequester($requester);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Veuillez sélectionner un demandeur."
                ]);
            }

            if (isset($data['missionType'])) {
                $mission->setType($data['missionType']);
            }

            if (isset($data['description'])) {
                $mission->setDescription($data['description']);
            }

            $em->persist($mission);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La mission d\'inventaire a bien été créée.',
                "redirect" => $this->generateUrl('inventory_mission_show', ["id" => $mission->getId()])
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="mission_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkMissionCanBeDeleted(Request $request,
                                             EntityManagerInterface $entityManager): Response
    {
        if ($missionId = json_decode($request->getContent(), true)) {
            $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);

            $mission = $inventoryMissionRepository->find($missionId);
            $missionArt = $articleRepository->countByMission($missionId);
            $missionRef = $referenceArticleRepository->countByMission($missionId);
            $missionEntries = $inventoryEntryRepository->count(['mission' => $mission]);
            $missionLocationsDone = $inventoryLocationMissionRepository->count(['inventoryMission' => $mission, 'done' => true]);

            $canBeDeleted = true;
            $message = "";
            if (intval($missionArt) + intval($missionRef) + $missionEntries > 0) {
                $canBeDeleted = false;
                $message = "Cette mission est liée à des références articles ou articles.";
            } elseif ($missionLocationsDone > 0) {
                $canBeDeleted = false;
                $message = "Cette mission a été commencée.";
            }

            if (!$canBeDeleted) {
                $delete = false;
                $html = $this->renderView('inventaire/modalDeleteMissionWrong.html.twig', [
                    'message' => $message
                ]);
            } else {
                $delete = true;
                $html = $this->renderView('inventaire/modalDeleteMissionRight.html.twig');
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="mission_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $mission = $inventoryMissionRepository->find($data['missionId']);
        $missionLocations = $mission->getInventoryLocationMissions();

        foreach ($missionLocations as $missionLocation) {
            foreach ($missionLocation->getInventoryLocationMissionReferenceArticles() as $missionLocationRef) {
                $entityManager->remove($missionLocationRef);
            }
            $entityManager->remove($missionLocation);
        }
        $entityManager->remove($mission);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "La mission d'inventaire a bien été supprimée."
        ]);
    }

    /**
     * @Route("/voir/{id}", name="inventory_mission_show", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE})
     */
    public function show(InventoryMission $mission): Response {
        return $this->render('inventaire/show.html.twig', [
            'missionId' => $mission->getId(),
            'typeLocation' => $mission->getType() === InventoryMission::LOCATION_TYPE,
            'locationsAlreadyAdded' => !$mission->getInventoryLocationMissions()->isEmpty(),
            'done' => $mission->isDone(),
        ]);
    }

    #[Route("/{mission}/mission-location-ref-api", name: "mission_location_ref_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_INVE], mode: HasPermission::IN_JSON)]
    public function getMissionLocationRefApi(EntityManagerInterface  $entityManager,
                                             InventoryMission        $mission,
                                             Request                 $request,
                                             InvMissionService       $invMissionService): JsonResponse {
        $result = $invMissionService->getDataForOneLocationMissionDatatable($entityManager, $mission,  $request->request);
        return new JsonResponse($result);
    }

    /**
     * @Route("/donnees_article/api/{id}", name="inv_entry_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function entryApiArticle(InventoryMission $mission,
                                    InvMissionService $invMissionService,
                                    Request $request): Response
    {
        $data = $invMissionService->getDataForOneMissionDatatable($mission, $request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/donnees_reference_article/api/{id}", name="inv_entry_reference_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function entryApiReferenceArticle(InventoryMission $mission,
                                             InvMissionService $invMissionService,
                                             Request $request): Response
    {
        $data = $invMissionService->getDataForOneMissionDatatable($mission, $request->request, false);
        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter", name="add_to_mission", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function addToMission(Request $request,
                                 InventoryService $inventoryService,
                                 EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $refArtRepository = $entityManager->getRepository(ReferenceArticle::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);

            $mission = $inventoryMissionRepository->find($data['missionId']);
            $barcodeErrors = [];
            $refOrArtToAdd = [];
            $artWithUL = [];

            /* Management of each barcode entered by the user */
            Stream::explode([",", " ", ";", "\t"], $data['articles'])
                ->filterMap(function($barcode) use ($articleRepository, $refArtRepository, $mission, $inventoryService) {
                    $barcode = trim($barcode);
                    /* The barcode must be from an article or an article reference */
                    if($article = $articleRepository->findOneBy(["barCode" => $barcode])) {
                        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
                        $checkForArt = $article instanceof Article
                            && $referenceArticle->getStatut()?->getCode() === ReferenceArticle::STATUT_ACTIF
                            && !$inventoryService->isInMissionInSamePeriod($article, $mission, false)
                            && $article->getStatut()?->getCode() !== Article::STATUT_INACTIF;

                        /* If the article/ref article does not meet the conditions, the filterMap returns a string */
                        return $checkForArt ? $article : $article->getBarCode();
                    } else if($reference = $refArtRepository->findOneBy(["barCode" => $barcode])) {

                        $checkForRef = $reference instanceof ReferenceArticle
                            && $reference->getStatut()?->getCode() === ReferenceArticle::STATUT_ACTIF
                            && !$inventoryService->isInMissionInSamePeriod($reference, $mission, true);

                        return $checkForRef ? $reference : $reference->getBarCode();
                    } else {
                        return $barcode;
                    }
                })
                ->each(function($refOrArt) use ($mission, &$barcodeErrors, $data, &$artWithUL, &$refOrArtToAdd) {

                    if ($refOrArt instanceof ReferenceArticle || $refOrArt instanceof Article) {
                        /* If the filterMap returns an object, we check if it's associated with a LU that has several articles */
                        if ($refOrArt instanceof Article && $refOrArt->getCurrentLogisticUnit() && count($refOrArt->getCurrentLogisticUnit()->getChildArticles()) > 1) {
                            $artWithUL[] = $refOrArt->getBarCode();
                        } else {
                            $refOrArtToAdd[] = $refOrArt;
                        }
                    } else {
                        /* If the filterMap returns a string with a barcode, it's an error */
                        $barcodeErrors[] = $refOrArt;
                    }
                });

            if (!empty($artWithUL)) {
                /* The article is associated with a LU which has several articles : an error is returned to display a confirmation modal */
                $errorMsg = '<span class="text-danger pl-2">Les articles suivants sont contenus dans une unité logistique :</span><ul class="list-group my-2">';
                foreach ($artWithUL as $articleCode) {
                    $errorMsg .= '<li class="text-danger list-group-item">' . $articleCode . '</li>';
                }
                $errorMsg .= '</ul><span class="text-danger pl-2">L\'ensemble des articles des unités logistiques associées va être ajouté à la mission d\'inventaire.</span>';

                /* We return barcodes and not objects */
                $barcodesWithoutUL = Stream::from($refOrArtToAdd)
                                ->filterMap(fn($refOrArtWithoutUL) => ($refOrArtWithoutUL->getBarCode()))
                                ->toArray();

                return new JsonResponse([
                    'success' => false,
                    'msg' => $errorMsg,
                    'data' => [
                        'barcodesUL' => $artWithUL, //barcodes that we stock in the input hidden
                        'barcodesToAdd' => array_merge($barcodesWithoutUL, $barcodeErrors), //barcodes that we will put again in the input texte
                    ]
                ]);
            } else {
                /* If the text input does not contain articles associated with an LU */
                $articlesWithULToAdd = [];
                $barcodesWithUL = $data['barcodesWithUL'];
                if (isset($barcodesWithUL) && !empty($barcodesWithUL)) {
                    /* We retrieve the articles associated with LUs */
                    Stream::explode([",", " ", ";", "\t"], $barcodesWithUL)
                        ->each(function($barcode) use ($articleRepository, &$barcodeErrors, &$articlesWithULToAdd) {
                            $barcode = trim($barcode);

                            if($article = $articleRepository->findOneBy(["barCode" => $barcode])) {
                                $articlesWithULToAdd[] = $article;
                            } else {
                                $barcodeErrors[] = $article;
                            }
                        });
                }

                /* Adding the mission to all the articles from the LU related */
                if (!empty($articlesWithULToAdd)) {
                    foreach ($articlesWithULToAdd as $article) {
                        foreach ($article->getCurrentLogisticUnit()->getChildArticles() as $articleFromPack) {
                            $articleFromPack->addInventoryMission($mission);
                        }
                    }
                }

                /* Adding the mission to each reference or article that meets the conditions */
                foreach ($refOrArtToAdd as $refOrArt) {
                    $refOrArt->addInventoryMission($mission);
                }
            }

            $entityManager->flush();
            $success = count($barcodeErrors) === 0;
            $errorMsg = "";

            /* Creation of the error message if articles do not meet the conditions */
            if (!$success) {
                $errorMsg = '<span class="pl-2">Les codes-barres suivants sont en erreur :</span><ul class="list-group my-2">';
                $errorMsg .= (
                    Stream::from($barcodeErrors)
                    ->map(function(string $barcode) {
                        return '<li class="list-group-item">' . $barcode . '</li>';
                    })
                    ->join("") . "</ul><span class='text-dark pl-2'>Les autres codes-barres ont bien été ajoutés à la mission.</span>"
                );
            }

            return new JsonResponse([
                'success' => $success,
                'msg' => $errorMsg
            ]);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/ajouter-emplacements", name="add_location_to_mission", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function addLocationToMission(Request $request,
                                 InventoryService $inventoryService,
                                 EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
            $locationRepository = $entityManager->getRepository(Emplacement::class);

            $mission = $inventoryMissionRepository->find($data['missionId']);
            $barcodeErrors = [];
            Stream::explode([",", " ", ";", "\t"], $data['emplacements'])
                ->filterMap(function($locationId) use ($mission, $inventoryService, $locationRepository) {
                    if($location = $locationRepository->find($locationId)) {
                        return $location;
                    } else {
                        return null;
                    }
                })
                ->each(function($location) use ($mission, &$barcodeErrors) {
                    if (isset($location)) {
                        foreach ($location->getArticles() as $article) {
                            $article->addInventoryMission($mission);
                        }
                        foreach ($location->getReferenceArticles() as $refArticle) {
                            $refArticle->addInventoryMission($mission);
                        }

                    } else {
                        $barcodeErrors[] = $location;
                    }
                });

            $entityManager->flush();
            $success = count($barcodeErrors) === 0;
            $errorMsg = "";
            if (!$success) {
                $errorMsg = '<span class="pl-2">Les emplacements suivants n\'existent pas :</span><ul class="list-group my-2">';
                $errorMsg .= (
                    Stream::from($barcodeErrors)
                        ->map(function(Emplacement $location) {
                            return '<li class="list-group-item">' . $location->getLabel() . '</li>';
                        })
                        ->join("") . "</ul><span class='text-dark pl-2'>Les autres emplacements ont bien été ajoutés à la mission.</span>"
                );
            }
            return new JsonResponse([
                'success' => $success,
                'msg' => $errorMsg
            ]);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/{mission}/csv", name="get_inventory_mission_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getInventoryMissionCSV(InventoryEntryService $inventoryEntryService,
                                           CSVExportService $CSVExportService,
                                           InventoryMission $mission): Response {

        $headers = [
            'Libellé',
            'Référence',
            'Code barre',
            'Quantité',
            'Emplacement',
            'Date dernier inventaire',
            'Anomalie'
        ];

        $missionStartDate = $mission->getStartPrevDate();
        $missionEndDate = $mission->getEndPrevDate();

        $inventoryEntries = Stream::from($mission->getEntries()->toArray())
            ->reduce(function (array $carry, InventoryEntry $entry) {
                $article = $entry->getArticle();
                $refArticle = $entry->getRefArticle();

                if (isset($article)) {
                    $barcode = $article->getBarCode();
                    $carry[$barcode] = $entry;
                }

                if (isset($refArticle)) {
                    $barcode = $refArticle->getBarCode();
                    $carry[$barcode] = $entry;
                }
                return $carry;
            }, []);

        $missionStartDateStr = $missionStartDate->format('d-m-Y');
        $missionEndDateStr = $missionEndDate->format('d-m-Y');

        return $CSVExportService->streamResponse(
            function ($output) use ($mission, $inventoryEntries, $CSVExportService, $inventoryEntryService, $missionStartDate, $missionEndDate) {
                $articles = $mission->getArticles();
                $refArticles = $mission->getRefArticles();
                /** @var Article $article */
                foreach ($articles as $article) {
                    $barcode = $article->getBarCode();
                    $inventoryEntryService->putMissionEntryLine($article, $inventoryEntries[$barcode] ?? null, $output);
                }

                /** @var ReferenceArticle $refArticle */
                foreach ($refArticles as $refArticle) {
                    $barcode = $refArticle->getBarCode();
                    $inventoryEntryService->putMissionEntryLine($refArticle, $inventoryEntries[$barcode] ?? null, $output);
                }
            },
            "Export_Mission_Inventaire_${missionStartDateStr}_${missionEndDateStr}.csv",
            [
                ['MISSION DU ' . $missionStartDate->format('d/m/Y') . ' AU ' . $missionEndDate->format('d/m/Y')],
                $headers
            ]
        );
    }

    /**
     * @Route("/remove_reference_from_inventory/", name="mission_remove_ref", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function removeReferenceFromInventoryMission(Request                $request,
                                                        EntityManagerInterface $entityManager): Response
    {

        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $data = json_decode($request->getContent(), true);
        $missionData = Stream::explode(";", $data['missionData'])
            ->keymap(fn($data) => explode(":", $data))
            ->toArray();

        $mission = $inventoryMissionRepository->find($missionData['missionId']);
        $referenceArticle = $referenceArticleRepository->find($missionData['referenceId']);

        if (isset($missionData['inventoryEntryId'])) {
            $inventoryEntry = $inventoryEntryRepository->find($missionData['inventoryEntryId']);
            $mission->removeEntry($inventoryEntry);
        }
        $mission->removeRefArticle($referenceArticle);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "La référence a bien été supprimée de l'inventaire"
        ]);
    }

    /**
     * @Route("/ajouter-emplacements-zones-datatable", name="add_locations_or_zones_to_mission_datatable", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function addLocationsOrZonesToMissionDatatable(Request $request, EntityManagerInterface $entityManager){
        $data = $request->query->all();

        $dataToDisplay = [];
        if(isset($data['dataIdsToDisplay'])){
            if ($data['buttonType'] === 'zones'){
                $zoneRepository = $entityManager->getRepository(Zone::class);
                $zones = $zoneRepository->findBy(['id' => $data['dataIdsToDisplay']]);
                $dataToDisplay = Stream::from($zones)
                    ->map(function(Zone $zone) {
                        return Stream::from($zone->getLocations())
                            ->map(fn(Emplacement $location) => [
                                'zone' => $this->formatService->zone($zone),
                                'location' => $this->formatService->location($location),
                                'id' => $location->getId()
                            ])
                            ->toArray();
                    })
                    ->toArray();
            } else if($data['buttonType'] === 'locations'){
                $locationRepository = $entityManager->getRepository(Emplacement::class);
                $locations = $locationRepository->findBy(['id' => $data['dataIdsToDisplay']]);

                $dataToDisplay = Stream::from($locations)
                    ->map(fn(Emplacement $location)  => [
                        'zone' => $this->formatService->zone($location->getZone()),
                        'location' => $this->formatService->location($location),
                        'id' => $location->getId()
                    ])
                    ->toArray();
            }
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => "Veuillez rensigner des emplacements à ajouter à la missions."
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'msg' => "Emplacements ajoutés dans le tableau",
            'data' => $dataToDisplay
        ]);
    }

    /**
     * @Route("/ajouter-emplacements-zones", name="add_locations_or_zones_to_mission", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function addLocationsOrZonesToMission(Request $request, EntityManagerInterface $entityManager){
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $locationIdsStr = $request->request->get('locations');
        $locationIds = $locationIdsStr
            ? Stream::explode(',', $locationIdsStr)
                ->map('trim')
                ->filter()
                ->toArray()
            : [];
        $inventoryMission = $inventoryMissionRepository->find($request->query->get('mission'));
        $locations = !empty($locationIds)
            ? $locationRepository->findBy(['id' => $locationIds])
            : [];

        if(empty($locations)){
            throw new FormException("Veuillez renseigner des emplacements à ajouter.");
        }

        foreach ($locations as $location){
            $inventoryLocationMission = (new InventoryLocationMission())
                ->setInventoryMission($inventoryMission)
                ->setLocation($location);
            $entityManager->persist($inventoryLocationMission);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => "Emplacements ajoutés avec succès."
        ]);
    }
}
