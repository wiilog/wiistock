<?php

namespace App\Controller\DeliveryStation;

use App\Controller\AbstractController;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
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

    #[Route("/", name: "delivery_station_index", options: ["expose" => true])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $type = $entityManager->getRepository(Type::class)->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Silicium'])[0];  // TODO Appliquer le paramétrage
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->findBy([])[0] ?? null; // TODO Appliquer le paramétrage

        $rawMessage = "Demande de livraison simplifiée sur le flux @typelivraison et groupe de visibilité @groupevisibilite";
        $homeMessage = str_replace('@typelivraison', "<strong>{$type->getLabel()}</strong>", str_replace('@groupevisibilite', "<strong>{$visibilityGroup->getLabel()}</strong>", $rawMessage));

        return $this->render('delivery_station/home.html.twig', [
            'homeMessage' => $homeMessage,
            'type' => $type->getId(),
            'visibilityGroup' => $visibilityGroup?->getId(),
        ]);
    }

    #[Route("/login/{mobileLoginKey}", name: "delivery_station_login", options: ["expose" => true], methods: "POST")]
    public function login(string $mobileLoginKey, EntityManagerInterface $entityManager): JsonResponse
    {
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->findBy([])[0] ?? null; // TODO Appliquer le paramétrage3

        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $mobileLoginKey]);
        if($user) {
            if($user->getVisibilityGroups()->contains($visibilityGroup)) {
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
    public function form(EntityManagerInterface $entityManager): Response
    {
        $type = $entityManager->getRepository(Type::class)->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Silicium'])[0]; // TODO Appliquer le paramétrage
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->findBy([])[0] ?? null; // TODO Appliquer le paramétrage

        $filterFields = []; // TODO remplacer par les champs filtres du paramétrage
        return $this->render('delivery_station/form.html.twig', [
            'filterFields' => $filterFields,
            'form' => true,
            'type' => $type->getLabel(),
            'visibilityGroup' => $visibilityGroup->getLabel(),
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

        if ($barcode) {
            if (str_starts_with($barcode, Article::BARCODE_PREFIX)) {
                $article = $entityManager->getRepository(Article::class)->findOneBy(['barCode' => $barcode]);
                if ($article) {
                    if ($article->isAvailable()) {
                        if ($article->getReferenceArticle()->getId() === $initialReference->getId()) {
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
            ];
        }

        return $this->json([
            'success' => true,
            'values' => $values,
        ]);
    }

    #[Route("/get-free-fields", name: "delivery_station_get_free_fields", options: ["expose" => true], methods: "GET")]
    public function getFreeFields(EntityManagerInterface $entityManager): JsonResponse
    {
        $type = $entityManager->getRepository(Type::class)->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Silicium'])[0]; // TODO A remplacer par le paramétrage
        $freeFields = $entityManager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON); // TODO Appliquer le paramétrage

        return $this->json([
            'empty' => empty($freeFields),
            'template' => !empty($freeFields) ?
                $this->renderView('free_field/freeFieldsEdit.html.twig', [
                    'freeFields' => $freeFields,
                    'freeFieldValues' => null,
                    'colType' => "col-6",
                    'actionType' => "new",
                    'disabledNeeded' => true,
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

        $typeRepository = $entityManager->getRepository(Type::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $type = $typeRepository->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Silicium'])[0]; // TODO A remplacer par le paramétrage
        $location = $locationRepository->findOneBy(['label' => 'SI BUREAU PLANNING']); // TODO A remplacer par le paramétrage

        $data = [
            'type' => $type->getId(),
            'demandeur' => $this->getUser(), // TODO A remplacer par le paramétrage
            'destination' => $location->getId(),
            'disabledFieldChecking' => true,
        ];
        $deliveryRequest = $deliveryRequestService->newDemande($data + $freeFields, $entityManager, $freeFieldService);
        $entityManager->persist($deliveryRequest);

        foreach ($references as $reference) {
            $pickedQuantity = intval($reference['pickedQuantity']);
            $barcode = $reference['barcode'];
            if ($reference['isReference']) {
                $reference = $referenceRepository->findOneBy(['barCode' => $barcode]);

                if ($pickedQuantity > $reference->getQuantiteDisponible()) {
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

                if ($pickedQuantity > $article->getQuantite()) {
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
            $articlesNotPicked = $preparationOrderService->createMouvementsPrepaAndSplit($preparation, $this->getUser(), $entityManager);
            $deliveryOrder = $deliveryOrderService->createLivraison($date, $preparation, $entityManager, Livraison::STATUT_LIVRE);

            $locationEndPreparation = $deliveryRequest->getDestination();

            $preparationOrderService->treatPreparation($preparation, $this->getUser(), $locationEndPreparation, ["articleLinesToKeep" => $articlesNotPicked]);
            $preparationOrderService->closePreparationMouvement($preparation, $date, $locationEndPreparation);

            $movements = $entityManager->getRepository(MouvementStock::class)->findByPreparation($preparation);

            foreach ($movements as $movement) {
                $movement = $preparationOrderService->createMovementLivraison(
                    $entityManager,
                    $movement->getQuantity(),
                    $this->getUser(),
                    $deliveryOrder,
                    !empty($movement->getRefArticle()),
                    $movement->getRefArticle() ?? $movement->getArticle(),
                    $preparation,
                    false,
                    $locationEndPreparation
                );

                $stockMovementService->finishMouvementStock($movement, $date, $locationEndPreparation);
            }

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
