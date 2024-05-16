<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Arrivage;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\Inventory\InventoryLocationMission;
use App\Entity\Livraison;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Nature;
use App\Entity\ReferenceArticle;

use App\Entity\TransferRequest;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Zone;
use App\Exceptions\FormException;
use App\Service\PDFGeneratorService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use App\Service\UserService;
use App\Service\EmplacementDataService;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

#[Route('/emplacement')]
class LocationController extends AbstractController {

    #[Required]
    public UserService $userService;

    #[Required]
    public TranslationService $translation;

    #[Route("/api", name: "emplacement_api", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_LOCATION], mode: HasPermission::IN_JSON)]
    public function api(Request $request, EmplacementDataService $emplacementDataService): Response {
        return $this->json($emplacementDataService->getEmplacementDataByParams($request->request));
    }

    #[Route("/index", name: "emplacement_index", methods: ["GET"])]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_LOCATION])]
    public function index(EntityManagerInterface $entityManager): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $filterStatus = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, EmplacementDataService::PAGE_EMPLACEMENT, $this->getUser());
        $active = $filterStatus ? $filterStatus->getValue() : false;

        return $this->render("emplacement/index.html.twig", [
            "newZone" => new Zone(),
            "active" => $active,
        ]);
    }

    #[Route("/creer", name: "emplacement_new", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        EmplacementDataService $emplacementDataService): Response {
        $data = $request->request;

        $dateMaxTime = $data->get(FixedFieldEnum::maximumTrackingDelay->name);
        $this->checkLocationLabel($entityManager, $data->get(FixedFieldEnum::name->name));
        $this->checkMaxTime($dateMaxTime);

        $email = $data->get(FixedFieldEnum::email->name);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new FormException("L'adresse email renseignée est invalide.");
        }

        $emplacement = $emplacementDataService->persistLocation($data->all(), $entityManager);
        $entityManager->flush();

        $label = $emplacement->getLabel();
        return $this->json([
            'success' => true,
            'msg' => "L'emplacement <strong>$label</strong> a bien été créé"
        ]);
    }

    #[Route(["/form/{location}", "/form"], name: "location_get_form", options: ["expose" => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiEdit(EntityManagerInterface $entityManager,
                            ?Emplacement           $location): Response {

        $location = $location ?: new Emplacement();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $zoneRepository = $entityManager->getRepository(Zone::class);

        $allNatures = $natureRepository->findAll();
        $deliveryTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $collectTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);
        $temperatures = $entityManager->getRepository(TemperatureRange::class)->findBy([]);
        $zonesCount = $zoneRepository->count([]);

        if (!$location->getId() && $zonesCount === 1) {
            $location->setProperty("zone", $zoneRepository->findOneBy([]));
        }

        return $this->json([
            'success' => true,
            'html' => $this->renderView("emplacement/form/form.html.twig", [
                "location" => $location,
                "natures" => $allNatures,
                "deliveryTypes" => $deliveryTypes,
                "collectTypes" => $collectTypes,
                "temperatures" => $temperatures,
                "zonesCount" => $zonesCount,
            ]),
        ]);
    }

    #[Route("/edit", name: "emplacement_edit", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response {
        $data = $request->request;
        if ($data->count() > 0) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $naturesRepository = $entityManager->getRepository(Nature::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $zoneRepository = $entityManager->getRepository(Zone::class);
            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

            $dateMaxTime = $data->get(FixedFieldEnum::maximumTrackingDelay->name);
            $locationId = $data->getInt('id');

            $this->checkLocationLabel($entityManager, $data->get(FixedFieldEnum::name->name), $locationId);
            $this->checkMaxTime($dateMaxTime);

            $zone = $zoneRepository->find($data->getInt(FixedFieldEnum::zone->name));

            $signatoryIds = Stream::explode(',', $data->get(FixedFieldEnum::signatories->name))
                    ->filterMap(fn(string $id) => trim($id) ?: null)
                    ->toArray();

            $signatories = !empty($signatoryIds)
                ? $userRepository->findBy(['id' => $signatoryIds])
                : [];
            $email = $data->get(FixedFieldEnum::email->name);
            if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new FormException("L'adresse email renseignée est invalide.");
            }

            $emplacement = $emplacementRepository->find($locationId);
            $emplacement
                ->setLabel($data->get(FixedFieldEnum::name->name))
                ->setDescription($data->get(FixedFieldEnum::description->name))
                ->setIsDeliveryPoint($data->getBoolean(FixedFieldEnum::isDeliveryPoint->name))
                ->setIsOngoingVisibleOnMobile($data->getBoolean(FixedFieldEnum::isOngoingVisibleOnMobile->name))
                ->setSendEmailToManagers($data->getBoolean(FixedFieldEnum::sendEmailToManagers->name))
                ->setDateMaxTime($dateMaxTime)
                ->setIsActive($data->getBoolean(FixedFieldEnum::status->name))
                ->setAllowedDeliveryTypes($typeRepository->findBy(["id" => explode(',', $data->get(FixedFieldEnum::allowedDeliveryTypes->name))]))
                ->setAllowedCollectTypes($typeRepository->findBy(["id" => explode(',', $data->get(FixedFieldEnum::allowedCollectTypes->name))]))
                ->setAllowedNatures($naturesRepository->findBy(["id" => explode(',', $data->get(FixedFieldEnum::allowedNatures->name))]))
                ->setTemperatureRanges($temperatureRangeRepository->findBy(["id" => explode(',', $data->get(FixedFieldEnum::allowedTemperatures->name))]))
                ->setManagers($userRepository->findBy(["id" => explode(',', $data->get(FixedFieldEnum::managers->name))]))
                ->setSignatories($signatories ?? [])
                ->setEmail($email)
                ->setProperty(FixedFieldEnum::zone->name, $zone);

            $entityManager->flush();

            $label = $emplacement->getLabel();
            return $this->json([
                'success' => true,
                'msg' => "L'emplacement <strong>$label</strong> a bien été modifié"
            ]);
        }
        throw new BadRequestHttpException();
    }

    private function isLocationUsed(EntityManagerInterface $entityManager,
                                    int                    $locationId): array {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $stockMovementRepository = $entityManager->getRepository(MouvementStock::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $collectRepository = $entityManager->getRepository(Collecte::class);
        $deliveryOrderRepository = $entityManager->getRepository(Livraison::class);
        $deliveryRequestRepository = $entityManager->getRepository(Demande::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $transferRequestRepository = $entityManager->getRepository(TransferRequest::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $inventoryLocationMissionRepository = $entityManager->getRepository(InventoryLocationMission::class);
        $arrivalRepository = $entityManager->getRepository(Arrivage::class);

        $location = $locationRepository->find($locationId);

        $usedBy = [];
        $deliveryRequests = $deliveryRequestRepository->countByLocation($location);
        if ($deliveryRequests > 0) {
            $usedBy[] = 'demandes';
        }

        $dispatches = $dispatchRepository->countByLocation($location);
        if ($dispatches > 0) {
            $usedBy[] = 'acheminements';
        }

        $deliveryOrders = $deliveryOrderRepository->countByLocation($location);
        if ($deliveryOrders > 0) {
            $usedBy[] = mb_strtolower($this->translation->translate("Ordre", "Livraison", "Livraison", false)) . 's';
        }

        $collects = $collectRepository->countByLocation($location);
        if ($collects > 0) {
            $usedBy[] = 'collectes';
        }

        $stockMovements = $stockMovementRepository->countByLocation($location);
        if ($stockMovements > 0) {
            $usedBy[] = 'mouvements de stock';
        }

        $trackingMovements = $trackingMovementRepository->countByLocation($location);
        if ($trackingMovements > 0) {
            $usedBy[] = 'mouvements de traçabilité';
        }

        $referenceArticles = $referenceArticleRepository->countByLocation($location);
        if ($referenceArticles > 0) {
            $usedBy[] = 'références article';
        }

        $articles = $articleRepository->countByLocation($location);
        if ($articles > 0) {
            $usedBy[] = 'articles';
        }

        //can't delete request if there's order so there is no need to count orders
        $transferRequests = $transferRequestRepository->countByLocation($locationId);
        if ($transferRequests > 0) {
            $usedBy[] = 'demandes de transfert';
        }

        $rounds = $locationRepository->countRound($locationId);
        if ($rounds > 0) {
            $usedBy[] = 'tournées';
        }

        $inventoryLocationMissions = $inventoryLocationMissionRepository->count(['location' => $location]);
        if ($inventoryLocationMissions > 0) {
            $usedBy[] = "missions d'inventaire";
        }

        $arrivals = $arrivalRepository->countByLocation($location);
        if($arrivals > 0) {
            $usedBy[] = "arrivages";
        }

        return $usedBy;
    }

    #[Route("/supprimer/{location}", name: "emplacement_delete", options: ["expose" => true], methods: ["DELETE"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(EntityManagerInterface $entityManager, Emplacement $location): JsonResponse {
        $isUsedBy = $this->isLocationUsed($entityManager, $location->getId());

        if($isUsedBy){
            throw new FormException("Vous ne pouvez pas supprimer cette emplacement car il est lié à des " . implode(', ', $isUsedBy) . ".");
        }

        $entityManager->remove($location);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => "L'emplacement <strong>{$location->getLabel()}</strong> a bien été supprimé"
        ]);
    }

    #[Route("/autocomplete", name: "get_emplacement", options: ["expose" => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    public function getRefArticles(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {

        $search = $request->query->get('term');

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $emplacement = $emplacementRepository->getIdAndLabelActiveBySearch($search);
        return new JsonResponse(['results' => $emplacement]);
    }

    #[Route("/etiquettes", name: "print_locations_bar_codes", options: ["expose" => true], methods: ["GET"])]
    public function printLocationsBarCodes(Request $request,
                                           EntityManagerInterface $entityManager,
                                           PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $listEmplacements = explode(',', $request->query->get('listEmplacements') ?? '');
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if (!empty($listEmplacements)) {
            $barCodeConfigs = array_map(
                function(Emplacement $location) {
                    return ['code' => $location->getLabel()];
                },
                $emplacementRepository->findBy(['id' => $listEmplacements])
            );

            $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
                $fileName
            );
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    #[Route("/{location}/etiquette", name: "print_single_location_bar_code", options: ["expose" => true], methods: ["GET"])]
    public function printSingleLocationBarCode(Emplacement $location,
                                               PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $barCodeConfigs = [['code' => $location->getLabel()]];

        $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
            $fileName
        );
    }

    private function checkLocationLabel(EntityManagerInterface $entityManager, ?string $label, $locationId = null): void {

        // if $label matches the regex, it's valid
        if (!preg_match("/" . SettingsService::CHARACTER_VALID_REGEX . "/", $label)) {
            throw new FormException("Le nom de l'emplacement doit contenir au maximum 24 caractères, lettres ou chiffres uniquement, pas d’accent");
        }

        $labelTrimmed = $label ? trim($label) : null;
        if (!empty($labelTrimmed)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $emplacementAlreadyExist = $emplacementRepository->countByLabel($label, $locationId);
            if ($emplacementAlreadyExist) {
                throw new FormException("Ce nom d'emplacement existe déjà. Veuillez en choisir un autre.");
            }
        } else {
            throw new FormException("Vous devez donner un nom valide.");
        }
    }

    private function checkMaxTime(?string $dateMaxTime): void {
        if (!empty($dateMaxTime)) {
            $matchHours = '\d+';
            $matchMinutes = '([0-5][0-9])';
            $matchHoursMinutes = "$matchHours:$matchMinutes";
            $resultFormat = preg_match(
                "/^$matchHoursMinutes$/",
                $dateMaxTime
            );
            if (empty($resultFormat)) {
                throw new FormException("Le délai saisi est invalide.");
            }
        }
    }

    #[Route("/autocomplete-locations-by-type", name: "get_locations_by_type", options: ["expose" => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    public function getLocationsByType(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $search = $request->query->get('term');
        $type = $request->query->get('type');

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $restrictResults = $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST);
        $locations = $locationRepository->getLocationsByType($type, $search, $restrictResults);
        return $this->json([
            'results' => $locations
        ]);
    }
}
