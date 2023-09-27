<?php

namespace App\Controller\DeliveryStation;

use App\Controller\AbstractController;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryStationLine;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\DeliveryRequestService;
use App\Service\FreeFieldService;
use App\Service\LivraisonsManagerService;
use App\Service\MouvementStockService;
use App\Service\PreparationsManagerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Article;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route("/caisse-automatique")]
class DeliveryStationController extends AbstractController
{

    #[Route("/index/{line}", name: "delivery_station_index", options: ["expose" => true])]
    public function index(DeliveryStationLine $line): Response
    {
        $type = $line->getDeliveryType();
        $visibilityGroup = $line->getVisibilityGroup();
        $message = str_replace("@groupevisibilite", "<strong>{$visibilityGroup->getLabel()}</strong>",
            str_replace("@typelivraison", "<strong>{$type->getLabel()}</strong>", $line->getWelcomeMessage()));

        return $this->render('delivery_station/home.html.twig', [
            'homeMessage' => $message,
            'line' => $line->getId(),
        ]);
    }

    #[Route("/login/{mobileLoginKey}", name: "delivery_station_login", options: ["expose" => true], methods: "POST")]
    public function login(string $mobileLoginKey, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $line = $entityManager->find(DeliveryStationLine::class, $request->query->get('line'));
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $mobileLoginKey]);

        if($user) {
            if($user->getVisibilityGroups()->contains($line->getVisibilityGroup())) {
                return $this->json([
                    'success' => true,
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => "L'utilisateur ne dispose pas du groupe de visibilité requis."
                ]);
            }
        } else {
            return $this->json([
                'success' => false,
                'msg' => "Aucun utilisateur n'est lié à cette clé de connexion nomade."
            ]);
        }
    }

    #[Route("/formulaire", name: "delivery_station_form", options: ["expose" => true])]
    public function form(Request $request, EntityManagerInterface $entityManager): Response
    {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $line = $entityManager->find(DeliveryStationLine::class, $request->query->get('line'));
        $filterFields = Stream::from($line->getFilters())
            ->map(static fn(string $filterField) => intval($filterField) ? $freeFieldRepository->find($filterField) : $filterField)
            ->toArray();
        return $this->render('delivery_station/form.html.twig', [
            'filterFields' => $filterFields,
            'form' => true,
            'line' => $line,
        ]);
    }

    #[Route("/get-informations", name: "delivery_station_get_informations", options: ["expose" => true], methods: "GET")]
    public function getReferenceInformations(Request                $request,
                                             EntityManagerInterface $entityManager): JsonResponse
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $initialReference = $referenceArticleRepository->find($request->query->get('reference'));
        $barcode = $request->query->has('barcode')
            ? $request->query->get('barcode')
            : null;
        $pickedQuantity = $request->query->has('pickedQuantity')
            ? $request->query->get('pickedQuantity')
            : null;

        if ($barcode) {
            if (str_starts_with($barcode, Article::BARCODE_PREFIX)) {
                $article = $entityManager->getRepository(Article::class)->findOneBy(['barCode' => $barcode]);
                if ($article) {
                    if ($article->isAvailable()) {
                        if ($article->getReferenceArticle()->getId() === $initialReference->getId()) {
                            if($pickedQuantity) {
                                $isAvailable = $pickedQuantity <= $article->getQuantite();
                                return $this->json([
                                    'success' => $isAvailable,
                                    'msg' => !$isAvailable ? "La quantité prise pour l'article <strong>{$article->getLabel()}</strong> excède la quantité en stock." : null,
                                ]);
                            }

                            $values = [
                                'location' => $this->formatService->location($article->getEmplacement()),
                                'suppliers' => $article->getArticleFournisseur()?->getFournisseur()?->getCodeReference() ?: '-',
                                'isReference' => false,
                            ];
                        } else {
                            return $this->json([
                                'success' => false,
                                'msg' => "L'article renseigné n'est pas lié à la référence sélectionée.",
                            ]);
                        }
                    } else {
                        return $this->json([
                            'success' => false,
                            'msg' => "L'article sélectionné n'est pas disponible.",
                        ]);
                    }
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => "L'article renseigné n'existe pas.",
                    ]);
                }
            } elseif (str_starts_with($barcode, ReferenceArticle::BARCODE_PREFIX)) {
                $reference = $referenceArticleRepository->findOneBy(['barCode' => $barcode]);
                if ($reference) {
                    if ($reference->getId() === $initialReference->getId()) {
                        if($pickedQuantity) {
                            $isAvailable = $pickedQuantity <= $reference->getQuantiteDisponible();
                            return $this->json([
                                'success' => $isAvailable,
                                'msg' => !$isAvailable ? "La quantité prise pour la référence <strong>{$reference->getReference()}</strong> excède la quantité disponible." : null,
                            ]);
                        }

                        $values = [
                            'location' => $this->formatService->location($reference->getEmplacement()),
                            'suppliers' => Stream::from($reference->getArticlesFournisseur())
                                ->map(static fn(ArticleFournisseur $supplierArticle) => $supplierArticle->getFournisseur()->getCodeReference())
                                ->join(','),
                            'isReference' => true,
                        ];
                    } else {
                        return $this->json([
                            'success' => false,
                            'msg' => "La référence renseignée doit être identique à celle sélectionnée au début du processus.",
                        ]);
                    }
                } else {
                    return $this->json([
                        'success' => false,
                        'msg' => "La référence renseignée n'existe pas.",
                    ]);
                }
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => "Le code barre renseigné ne correspond à aucun article ou référence.",
                ]);
            }
        } else {
            $isReferenceByArticle = $initialReference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE;
            $locationByStockManagement = null;
            if($isReferenceByArticle && $initialReference->getStockManagement()) {
                $articleRepository = $entityManager->getRepository(Article::class);
                $locationByStockManagement = $articleRepository->findOneByReferenceAndStockManagement($initialReference)?->getEmplacement()->getLabel();
            }

            $values = [
                'id' => $initialReference->getId(),
                'reference' => $initialReference->getReference(),
                'label' => $initialReference->getLibelle(),
                'stockQuantity' => $initialReference->getQuantiteDisponible(),
                'barcode' => $initialReference->getBarCode(),
                'image' => $initialReference->getImage()
                    ? "{$initialReference->getImage()->getFullPath()}"
                    : "",
                'isReferenceByArticle' => $initialReference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                'location' => $locationByStockManagement,
            ];
        }

        return $this->json([
            'success' => true,
            'values' => $values,
        ]);
    }

    #[Route("/get-free-fields", name: "delivery_station_get_free_fields", options: ["expose" => true], methods: "GET")]
    public function getFreeFields(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $line = $entityManager->find(DeliveryStationLine::class, $request->query->get('line'));
        $freeFields = $entityManager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($line->getDeliveryType(), CategorieCL::DEMANDE_LIVRAISON);

        return $this->json([
            'empty' => empty($freeFields),
            'template' => !empty($freeFields) ?
                $this->renderView('free_field/freeFieldsEdit.html.twig', [
                    'freeFields' => $freeFields,
                    'freeFieldValues' => [],
                    'colType' => "col-6",
                    'actionType' => "new",
                    'requiredType' => "requiredCreate",
                ]) : "",
        ]);
    }

    #[Route("/submit-request", name: "delivery_station_submit_request", options: ["expose" => true], methods: "POST")]
    public function submitRequest(Request                    $request,
                                  EntityManagerInterface     $entityManager,
                                  DeliveryRequestService     $deliveryRequestService,
                                  LivraisonsManagerService   $deliveryOrderService,
                                  PreparationsManagerService $preparationOrderService,
                                  FreeFieldService           $freeFieldService,
                                  MouvementStockService      $stockMovementService): JsonResponse
    {
        $values = $request->query->all();
        $references = json_decode($values['references'], true);
        $freeFields = Stream::from(json_decode($values['freeFields'], true))
            ->flatten(true)
            ->toArray();
        $date = new DateTime();

        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $deliveryStationLineRepository = $entityManager->getRepository(DeliveryStationLine::class);

        $line = $deliveryStationLineRepository->find($values['line']);
        $data = [
            'type' => $line->getDeliveryType()->getId(),
            'demandeur' => $this->getUser(),
            'destination' => $line->getDestinationLocation()->getId(),
            'disabledFieldChecking' => true,
        ];
        $deliveryRequest = $deliveryRequestService->newDemande($data + $freeFields, $entityManager, $freeFieldService);
        $entityManager->persist($deliveryRequest);

        foreach ($references as $reference) {
            $pickedQuantity = intval($reference['pickedQuantity']);
            $barcode = $reference['barcode'];
            if ($reference['isReference']) {
                $reference = $referenceRepository->findOneBy(['barCode' => $barcode]);

                if($reference->getStatut()->getCode() === ReferenceArticle::STATUT_INACTIF) {
                    return $this->json([
                        'success' => false,
                        'msg' => "La référence <strong>{$reference->getReference()}</strong> est inactive.",
                    ]);
                } else if ($pickedQuantity > $reference->getQuantiteDisponible()) {
                    return $this->json([
                        'success' => false,
                        'msg' => "La quantité prise pour la référence <strong>{$reference->getReference()}</strong> excède la quantité disponible.",
                    ]);
                } else {
                    $line = (new DeliveryRequestReferenceLine())
                        ->setRequest($deliveryRequest)
                        ->setPickedQuantity($pickedQuantity)
                        ->setQuantityToPick($pickedQuantity)
                        ->setReference($reference);
                }
            } else {
                $article = $articleRepository->findOneBy(['barCode' => $barcode]);

                if($article->isAvailable()) {
                    return $this->json([
                        'success' => false,
                        'msg' => "L'article <strong>{$article->getLabel()}</strong> n'est pas disponible.",
                    ]);
                } else if ($pickedQuantity > $article->getQuantite()) {
                    return $this->json([
                        'success' => false,
                        'msg' => "La quantité prise pour l'article <strong>{$article->getLabel()}</strong> excède la quantité en stock.",
                    ]);
                } else {
                    $line = (new DeliveryRequestArticleLine())
                        ->setRequest($deliveryRequest)
                        ->setPickedQuantity($pickedQuantity)
                        ->setQuantityToPick($pickedQuantity)
                        ->setArticle($article);
                }
            }

            $entityManager->persist($line);
        }

        $response = $deliveryRequestService->validateDLAfterCheck(
            $entityManager,
            $deliveryRequest,
            false,
            true,
            true,
            false,
            ['sendNotification' => false]
        );

        if ($response['success']) {
            $preparation = $deliveryRequest->getPreparations()->first();
            $deliveryOrder = $deliveryOrderService->createLivraison($date, $preparation, $entityManager);

            foreach ($deliveryRequest->getArticleLines() as $articleLine) {
                $article = $articleLine->getArticle();
                $outMovement = $preparationOrderService->createMovementLivraison(
                    $entityManager,
                    $article->getQuantite(),
                    $this->getUser(),
                    $deliveryOrder,
                    false,
                    $article,
                    $preparation,
                    true,
                    $article->getEmplacement()
                );

                $stockMovementService->finishMouvementStock($outMovement, $date, $deliveryRequest->getDestination());
            }

            $preparationOrderService->treatPreparation($preparation, $this->getUser(), $deliveryRequest->getDestination());

            $preparationOrderService->updateRefArticlesQuantities($preparation, $entityManager);
            $deliveryOrderService->finishLivraison($this->getUser(), $deliveryOrder, $date, $deliveryRequest->getDestination());

            $entityManager->flush();
        } else {
            return $this->json([
                'success' => false,
            ]);
        }

        return $this->json([
            'success' => true,
        ]);
    }
}
