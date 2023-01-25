<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
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
use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\EmplacementDataService;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Route("/emplacement")
 */
class LocationController extends AbstractController {

    /** @Required */
    public UserService $userService;

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_EMPL}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, EmplacementDataService $emplacementDataService): Response {
        return $this->json($emplacementDataService->getEmplacementDataByParams($request->request));
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_EMPL})
     */
    public function index(EntityManagerInterface $entityManager): Response {
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $allNatures = $natureRepository->findAll();

        $filterStatus = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, EmplacementDataService::PAGE_EMPLACEMENT, $this->getUser());
        $active = $filterStatus ? $filterStatus->getValue() : false;

        $typeRepository = $entityManager->getRepository(Type::class);
        $deliveryTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $collectTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);
        $temperatures = $entityManager->getRepository(TemperatureRange::class)->findBy([]);

        return $this->render("emplacement/index.html.twig", [
            "active" => $active,
            "natures" => $allNatures,
            "deliveryTypes" => $deliveryTypes,
            "collectTypes" => $collectTypes,
            "temperatures" => $temperatures,
            "newZone" => new Zone()
        ]);
    }

    /**
     * @Route("/creer", name="emplacement_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $naturesRepository = $entityManager->getRepository(Nature::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

            $errorResponse = $this->checkLocationLabel($entityManager, $data["Label"] ?? null);
            if ($errorResponse) {
                return $errorResponse;
            }

            $dateMaxTime = !empty($data['dateMaxTime']) ? $data['dateMaxTime'] : null;
            $errorResponse = $this->checkMaxTime($dateMaxTime);
            if ($errorResponse) {
                return $errorResponse;
            }

            $signatory = !empty($data['signatory']) ? $userRepository->find($data['signatory']) : null;
            $email = $data['email'] ?? null;
            if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'adresse email renseignée est invalide.",
                ]);
            }
            $emplacement = new Emplacement();
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"])
                ->setIsActive(true)
                ->setDateMaxTime($dateMaxTime)
                ->setIsDeliveryPoint($data["isDeliveryPoint"])
                ->setIsOngoingVisibleOnMobile($data["isDeliveryPoint"])
                ->setAllowedDeliveryTypes($typeRepository->findBy(["id" => $data["allowedDeliveryTypes"]]))
                ->setAllowedCollectTypes($typeRepository->findBy(["id" => $data["allowedCollectTypes"]]))
                ->setSignatory($signatory)
                ->setEmail($email);

            if (!empty($data['allowed-natures'])) {
                foreach ($data['allowed-natures'] as $allowedNatureId) {
                    $emplacement
                        ->addAllowedNature($naturesRepository->find($allowedNatureId));
                }
            }

            if (!empty($data['allowedTemperatures'])) {
                foreach ($data['allowedTemperatures'] as $allowedTemperatureId) {
                    $emplacement
                        ->addTemperatureRange($temperatureRangeRepository->find($allowedTemperatureId));
                }
            }

            $entityManager->persist($emplacement);
            $entityManager->flush();

            $label = $emplacement->getLabel();
            return $this->json([
                'success' => true,
                'msg' => "L'emplacement <strong>$label</strong> a bien été créé"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="emplacement_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $natureRepository = $entityManager->getRepository(Nature::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $allNatures = $natureRepository->findAll();
            $emplacement = $emplacementRepository->find($data['id']);
            $deliveryTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
            $collectTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);
            $temperatures = $entityManager->getRepository(TemperatureRange::class)->findBy([]);

            return $this->json($this->renderView("emplacement/modalEditEmplacementContent.html.twig", [
                "location" => $emplacement,
                "natures" => $allNatures,
                "deliveryTypes" => $deliveryTypes,
                "collectTypes" => $collectTypes,
                "temperatures" => $temperatures
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/edit", name="emplacement_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $naturesRepository = $entityManager->getRepository(Nature::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

            $errorResponse = $this->checkLocationLabel($entityManager, $data["Label"] ?? null, $data['id']);
            if ($errorResponse) {
                return $errorResponse;
            }

            $dateMaxTime = !empty($data['dateMaxTime']) ? $data['dateMaxTime'] : null;
            $errorResponse = $this->checkMaxTime($dateMaxTime);
            if ($errorResponse) {
                return $errorResponse;
            }

            $signatory = !empty($data['signatory']) ? $userRepository->find($data['signatory']) : null;
            $email = $data['email'] ?? null;
            if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'adresse email renseignée est invalide.",
                ]);
            }
            $emplacement = $emplacementRepository->find($data['id']);
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"])
                ->setIsDeliveryPoint($data["isDeliveryPoint"])
                ->setIsOngoingVisibleOnMobile($data["isOngoingVisibleOnMobile"])
                ->setDateMaxTime($dateMaxTime)
                ->setIsActive($data['isActive'])
                ->setAllowedDeliveryTypes($typeRepository->findBy(["id" => $data["allowedDeliveryTypes"]]))
                ->setAllowedCollectTypes($typeRepository->findBy(["id" => $data["allowedCollectTypes"]]))
                ->setSignatory($signatory)
                ->setEmail($email);

            $emplacement->getAllowedNatures()->clear();

            if (!empty($data['allowed-natures'])) {
                foreach ($data['allowed-natures'] as $allowedNatureId) {
                    $emplacement
                        ->addAllowedNature($naturesRepository->find($allowedNatureId));
                }
            }

            $emplacement->getTemperatureRanges()->clear();

            if (!empty($data['allowedTemperatures'])) {
                foreach ($data['allowedTemperatures'] as $allowedTemperatureId) {
                    $emplacement
                        ->addTemperatureRange($temperatureRangeRepository->find($allowedTemperatureId));
                }
            }

            $entityManager->flush();

            $label = $emplacement->getLabel();
            return $this->json([
                'success' => true,
                'msg' => "L'emplacement <strong>$label</strong> a bien été modifié"
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="emplacement_check_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DISPLAY_EMPL}, mode=HasPermission::IN_JSON)
     */
    public function checkEmplacementCanBeDeleted(Request $request, EntityManagerInterface $manager): Response {
        if ($emplacementId = json_decode($request->getContent(), true)) {
            $isUsedBy = $this->isEmplacementUsed($manager, $emplacementId);
            if (empty($isUsedBy)) {
                $delete = true;
                $html = $this->renderView('emplacement/modalDeleteEmplacementRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('emplacement/modalDeleteEmplacementWrong.html.twig', [
                    'delete' => false,
                    'isUsedBy' => $isUsedBy
                ]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    private function isEmplacementUsed(EntityManagerInterface $entityManager, int $emplacementId): array {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $collecteRepository = $entityManager->getRepository(Collecte::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $transferRequestRepository = $entityManager->getRepository(TransferRequest::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $usedBy = [];

        $demandes = $demandeRepository->countByEmplacement($emplacementId);
        if ($demandes > 0) $usedBy[] = 'demandes';

        $dispatches = $dispatchRepository->countByEmplacement($emplacementId);
        if ($dispatches > 0) $usedBy[] = 'acheminements';

        $livraisons = $livraisonRepository->countByEmplacement($emplacementId);
        if ($livraisons > 0) $usedBy[] = 'livraisons';

        $collectes = $collecteRepository->countByEmplacement($emplacementId);
        if ($collectes > 0) $usedBy[] = 'collectes';

        $mouvementsStock = $mouvementStockRepository->countByEmplacement($emplacementId);
        if ($mouvementsStock > 0) $usedBy[] = 'mouvements de stock';

        $trackingMovements = $trackingMovementRepository->countByEmplacement($emplacementId);
        if ($trackingMovements > 0) $usedBy[] = 'mouvements de traçabilité';

        $refArticle = $referenceArticleRepository->countByEmplacement($emplacementId);
        if ($refArticle > 0) $usedBy[] = 'références article';

        $articles = $articleRepository->countByEmplacement($emplacementId);
        if ($articles > 0) $usedBy[] = 'articles';

        //can't delete request if there's order so there is no need to count orders
        $transferRequests = $transferRequestRepository->countByLocation($emplacementId);
        if ($transferRequests > 0) $usedBy[] = 'demandes de transfert';

        $round = $locationRepository->countRound($emplacementId);
        if ($round > 0) $usedBy[] = 'tournées';

        return $usedBy;
    }

    /**
     * @Route("/supprimer", name="emplacement_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::REFERENTIEL, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $response = [];

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            if ($emplacementId = (int)$data['emplacement']) {
                $emplacement = $emplacementRepository->find($emplacementId);

                if ($emplacement) {
                    $usedEmplacement = $this->isEmplacementUsed($entityManager, $emplacementId);

                    if (!empty($usedEmplacement)) {
                        $emplacement->setIsActive(false);
                    } else {
                        $entityManager->remove($emplacement);
                        $response['delete'] = $emplacementId;
                    }
                    $entityManager->flush();
                }
            }

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete", name="get_emplacement", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getRefArticles(Request $request, EntityManagerInterface $entityManager) {

        $search = $request->query->get('term');

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $emplacement = $emplacementRepository->getIdAndLabelActiveBySearch($search);
        return new JsonResponse(['results' => $emplacement]);
    }

    /**
     * @Route("/etiquettes", name="print_locations_bar_codes", options={"expose"=true}, methods={"GET"})
     */
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

    /**
     * @Route("/{location}/etiquette", name="print_single_location_bar_code", options={"expose"=true}, methods={"GET"})
     */
    public function printSingleLocationBarCode(Emplacement $location,
                                               PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $barCodeConfigs = [['code' => $location->getLabel()]];

        $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
            $fileName
        );
    }

    private function checkLocationLabel(EntityManagerInterface $entityManager, ?string $label, $locationId = null) {
        $labelTrimmed = $label ? trim($label) : null;
        if (!empty($labelTrimmed)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $emplacementAlreadyExist = $emplacementRepository->countByLabel($label, $locationId);
            if ($emplacementAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Ce nom d'emplacement existe déjà. Veuillez en choisir un autre."
                ]);
            }
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => "Vous devez donner un nom valide."
            ]);
        }
        return null;
    }

    private function checkMaxTime(?string $dateMaxTime) {
        if (!empty($dateMaxTime)) {
            $matchHours = '\d+';
            $matchMinutes = '([0-5][0-9])';
            $matchHoursMinutes = "$matchHours:$matchMinutes";
            $resultFormat = preg_match(
                "/^$matchHoursMinutes$/",
                $dateMaxTime
            );
            if (empty($resultFormat)) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le délai saisi est invalide."
                ]);
            }
        }
    }

    /**
     * @Route("/autocomplete-locations-by-type", name="get_locations_by_type", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     */
    public function getLocationsByType(Request $request, EntityManagerInterface $entityManager) {
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
