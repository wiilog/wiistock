<?php

namespace App\Controller\DeliveryStation;

use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryStationLine;
use App\Entity\FreeField\FreeField;
use App\Entity\FreeField\FreeFieldManagementRule;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Service\DeliveryRequestService;
use App\Service\LivraisonsManagerService;
use App\Service\MouvementStockService;
use App\Service\NotificationService;
use App\Service\PreparationsManagerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/caisse-automatique")]
class DeliveryStationController extends AbstractController
{

    #[Route("/check-mobile-login-key", name: "delivery_station_check_mobile_login_key", options: ["expose" => true], methods: "GET")]
    public function login(Request $request, EntityManagerInterface $entityManager): Response
    {
        $mobileLoginKey = $request->query->get('mobileLoginKey');
        $token = $request->query->get('token');

        $line = $entityManager->getRepository(DeliveryStationLine::class)->findOneBy(['token' => $token]);
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $mobileLoginKey]);
        if($user) {
            if($user->getVisibilityGroups()->isEmpty() || $user->getVisibilityGroups()->contains($line->getVisibilityGroup())) {
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
                'msg' => "Aucun utilisateur n'est associé à cette clé de connexion nomade."
            ]);
        }
    }

    #[Route("/formulaire/{token}", name: "delivery_station_form", options: ["expose" => true])]
    public function form(string $token, Request $request, EntityManagerInterface $entityManager): Response {
        if(!$this->getUser()) {
            return $this->redirectToRoute("login");
        }

        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $freeFieldManagementRuleRepository = $entityManager->getRepository(FreeFieldManagementRule::class);
        $deliveryFreeFieldManagementRules = $freeFieldManagementRuleRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_LIVRAISON]);

        $mobileLoginKey = $request->query->get('mobileLoginKey');
        $line = $entityManager->getRepository(DeliveryStationLine::class)->findOneBy(['token' => $token]);

        if($line) {
            $filterFields = Stream::from($line->getFilters())
                ->map(static fn(string $filterField) => intval($filterField)
                    ? (new FreeFieldManagementRule())
                    ->setFreeField($freeFieldRepository->find($filterField))
                    ->setDisplayedCreate(true)
                    : $filterField
                )
                ->toArray();

            $homeMessage = str_replace("@groupevisibilite", "<strong>{$line->getVisibilityGroup()->getLabel()}</strong>",
                str_replace("@typelivraison", "<strong>{$line->getDeliveryType()->getLabel()}</strong>", $line->getWelcomeMessage()));

            return $this->render('delivery_station/form.html.twig', [
                'freeFieldManagementRules' => $deliveryFreeFieldManagementRules,
                'filterFields' => $filterFields,
                'form' => true,
                'line' => $line,
                'mobileLoginKey' => $mobileLoginKey,
                'homeMessage' => $homeMessage,
            ]);
        } else {
            return $this->render('delivery_station/invalidToken.html.twig', [
                'invalidToken' => true,
            ]);
        }
    }

    #[Route("/get-informations", name: "delivery_station_get_informations", options: ["expose" => true], methods: "GET")]
    public function getReferenceInformations(Request                $request,
                                             EntityManagerInterface $entityManager): JsonResponse
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $initialReference = $referenceArticleRepository->find($request->query->get('reference'));
        $barcode = $request->query->get('barcode');
        $pickedQuantity = $request->query->get('pickedQuantity');
        $isScannedBarcode = $request->query->getBoolean('isScannedBarcode');

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
                                'msg' => "L'article renseigné n'est pas lié à la référence sélectionnée.",
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
                        if($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
                            return $this->json([
                                'success' => false,
                                'msg' => "Cette référence est gérée à l'article, vous ne pouvez pas l'ajouter dans la demande. Sélectionnez un article disponible pour continuer.",
                            ]);
                        }

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
            if ($isReferenceByArticle) {
                $articleRepository = $entityManager->getRepository(Article::class);
                $article = $articleRepository->findOneByReferenceAndStockManagement($initialReference);
                $location = $article?->getEmplacement();
            } else {
                $location = $initialReference->getEmplacement();
            }

            $location = $location ? $this->formatService->location($location) : null;
            $values = [
                'id' => $initialReference->getId(),
                'reference' => $initialReference->getReference(),
                'label' => $initialReference->getLibelle(),
                'stockQuantity' => $initialReference->getQuantiteDisponible(),
                'barcode' => $initialReference->getBarCode(),
                'image' => $initialReference->getImage()
                    ? "{$initialReference->getImage()->getFullPath()}"
                    : "",
                'suppliers' => !$isReferenceByArticle
                    ? Stream::from($initialReference->getArticlesFournisseur())
                        ->map(static fn(ArticleFournisseur $supplierArticle) => $supplierArticle->getFournisseur()->getCodeReference())
                        ->join(',')
                    : '',
                'isReferenceByArticle' => $isReferenceByArticle,
                'location' => $location,
            ];
        }

        return $this->json([
            'success' => true,
            'values' => $values,
            'prefill' => $isScannedBarcode && $initialReference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE,
        ]);
    }

    #[Route("/get-free-fields", name: "delivery_station_get_free_fields", options: ["expose" => true], methods: "GET")]
    public function getFreeFields(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $deliveryStationLineRepository = $entityManager->getRepository(DeliveryStationLine::class);

        $line = $deliveryStationLineRepository->findOneBy(['token' => $request->query->get('token')]);
        $deliveryFreeFieldManagementRules = $line?->getDeliveryType()?->getFreeFieldManagementRules();

        return $this->json([
            'empty' => empty($deliveryFreeFieldManagementRules),
            'template' => !empty($deliveryFreeFieldManagementRules) ?
                $this->renderView('free_field/freeFieldsEdit.html.twig', [
                    'freeFieldManagementRules' => $deliveryFreeFieldManagementRules,
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
                                  NotificationService        $notificationService,
                                  PreparationsManagerService $preparationOrderService,
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
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $deliveryStationLine = $deliveryStationLineRepository->findOneBy(['token' => $values['token']]);
        $user = $userRepository->findOneBy(['mobileLoginKey' => $values['mobileLoginKey']]);

        if(!$user) {
            return $this->json([
                'success' => false,
                'msg' => "Une erreur est survenue lors de l'enregistrement, la clé de connexion nomade n'est pas valide.",
            ]);
        }

        $data = [
            'type' => $deliveryStationLine->getDeliveryType()->getId(),
            'demandeur' => $user,
            'destination' => $deliveryStationLine->getDestinationLocation()->getId(),
            'disabledFieldChecking' => true,
            'isFastDelivery' => true,
        ];
        $deliveryRequest = $deliveryRequestService->newDemande($data + $freeFields, $entityManager);
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

                if(!$article->isAvailable()) {
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

        $entityManager->flush();
        $response = $deliveryRequestService->checkDLStockAndValidate(
            $entityManager,
            [
                'demande' => $deliveryRequest,
                'directDelivery' => true,
            ],
            false,
            false,
            true
        );

        if (!$response['success']) {
            $entityManager->remove($request);
            $entityManager->flush();
            return $this->json($response);
        }

        $preparation = $deliveryRequest->getPreparations()->first();
        $deliveryOrder = $deliveryOrderService->createLivraison($date, $preparation, $entityManager);

        $articlesToKeep = $preparationOrderService->createMouvementsPrepaAndSplit($preparation, $user, $entityManager);

        foreach ($deliveryRequest->getArticleLines() as $articleLine) {
            $article = $articleLine->getArticle();
            $outMovement = $preparationOrderService->persistDeliveryMovement(
                $entityManager,
                $articleLine->getPickedQuantity(),
                $user,
                $deliveryOrder,
                false,
                $article,
                $preparation,
                true,
                $article->getEmplacement()
            );

            $stockMovementService->finishStockMovement($outMovement, $date, $deliveryRequest->getDestination());
        }

        foreach ($deliveryRequest->getReferenceLines() as $referenceLine) {
            $reference = $referenceLine->getReference();
            $outMovement = $preparationOrderService->persistDeliveryMovement(
                $entityManager,
                $referenceLine->getPickedQuantity(),
                $user,
                $deliveryOrder,
                true,
                $reference,
                $preparation,
                true,
                $reference->getEmplacement()
            );

            $stockMovementService->finishStockMovement($outMovement, $date, $deliveryRequest->getDestination());
        }

        $newPreparation = $preparationOrderService->treatPreparation($preparation, $user, $deliveryRequest->getDestination(), [
            "entityManager" => $entityManager,
            "changeArticleLocation" => false,
            "articleLinesToKeep" => $articlesToKeep,
        ]);
        $deliveryOrderService->finishLivraison($user, $deliveryOrder, $date, $deliveryRequest->getDestination(), [
            'deliveryStationLineReceivers' => Stream::from($deliveryStationLine->getReceivers())
                ->map(static fn(Utilisateur $receiver) => $receiver->getEmail())
                ->toArray(),
        ]);

        $entityManager->flush(); // need to flush before quantity update
        $preparationOrderService->updateRefArticlesQuantities($preparation, $entityManager);
        $entityManager->flush();

        if ($newPreparation
            && $newPreparation->getDemande()->getType()->isNotificationsEnabled()) {
            $notificationService->toTreat($newPreparation);
        }

        return $this->json([
            'success' => true,
        ]);
    }
}
