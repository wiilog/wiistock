<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Menu;
use App\Entity\Cart;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\PurchaseRequest;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\CartService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/panier")
 */
class CartController extends AbstractController
{

    /**
     * @Route("/", name="cart")
     */
    public function cart(EntityManagerInterface $manager,
                         SettingsService        $settingsService): Response {
        $typeRepository = $manager->getRepository(Type::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $settingRepository = $manager->getRepository(Setting::class);
        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

        $deliveryFreeFields = [];
        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

            $deliveryFreeFields[] = [
                "typeLabel" => $type->getLabel(),
                "typeId" => $type->getId(),
                "champsLibres" => $champsLibres,
            ];
        }

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);

        $collectFreeFields = [];
        foreach ($types as $type) {
            $champsLibres = $freeFieldRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);

            $collectFreeFields[] = [
                "typeLabel" => $type->getLabel(),
                "typeId" => $type->getId(),
                "champsLibres" => $champsLibres,
            ];
        }

        $referencesByBuyer = [];
        foreach($currentUser->getCart()->getReferences() as $reference) {
            $buyerId = $reference->getBuyer() ? $reference->getBuyer()->getId() : null;
            if(!isset($referencesByBuyer[$buyerId])) {
                $referencesByBuyer[$buyerId] = [
                    "buyer" => $reference->getBuyer(),
                    "references" => [],
                ];
            }

            $referencesByBuyer[$buyerId]["references"][] = $reference;
        }

        ksort($referencesByBuyer);

        //add no buyer references at the end
        $referencesByBuyer[] = array_shift($referencesByBuyer);

        $defaultDeliveryLocations = $settingsService->getDefaultDeliveryLocationsByTypeId($manager);
        $deliveryULs = [];
        $deliveryArticles = $currentUser->getCart()?->getArticles()?->getValues();
        $project = null;
        foreach ($deliveryArticles as $article) {
            if ($article->getCurrentLogisticUnit()?->getProject()?->getCode() && !$project) {
                $project = $article->getCurrentLogisticUnit()?->getProject()?->getCode();
            }
            if (!in_array($article->getCurrentLogisticUnit()?->getId(), $deliveryULs)) {
                array_push($deliveryULs,$article->getCurrentLogisticUnit()?->getId());
            }
        }
        $deliveryRequests = Stream::from($manager->getRepository(Demande::class)->getDeliveryRequestForSelect($currentUser))
            ->filter(fn(Demande $request) => $request->getType() && $request->getDestination())
            ->map(fn(Demande $request) => [
                "value" => $request->getId(),
                "label" => "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getDestination()->getLabel()} - Créée le {$request->getCreatedAt()->format('d/m/Y H:i')}"
            ]);

        $collectRequests = Stream::from($manager->getRepository(Collecte::class)->getCollectRequestForSelect($currentUser))
            ->filter(fn(Collecte $request) => $request->getType() && $request->getPointCollecte())
            ->map(fn(Collecte $request) => [
                "value" => $request->getId(),
                "label" => "{$request->getNumero()} - {$request->getType()->getLabel()} - {$request->getPointCollecte()->getLabel()} - Créée le {$request->getDate()->format('d/m/Y H:i')}"
            ]);

        $purchaseRequests = Stream::from($manager->getRepository(PurchaseRequest::class)->getPurchaseRequestForSelect($currentUser))
            ->map(fn(PurchaseRequest $request) => [
                "value" => $request->getId(),
                "label" => "{$request->getNumber()} - Créée le {$request->getCreationDate()->format('d/m/Y H:i')}",
                "number" => $request->getNumber(),
                "requester" => $request->getRequester(),
                "buyer" => $request->getBuyer(),
            ]);

        return $this->render("cart/index.html.twig", [
            "project" => $project,
            "deliveryULs" => $deliveryULs,
            "deliveryRequests" => $deliveryRequests,
            "collectRequests" => $collectRequests,
            "purchaseRequests" => $purchaseRequests,
            "defaultDeliveryLocations" => $defaultDeliveryLocations,
            "deliveryFreeFieldsTypes" => $deliveryFreeFields,
            "collectFreeFieldsTypes" => $collectFreeFields,
            "referencesByBuyer" => $referencesByBuyer,
            "deliveryFieldsParam" => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DEMANDE),
            "showTargetLocationPicking" => $settingRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION),
            "restrictedCollectLocations" => $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST),
            "restrictedDeliveryLocations" => $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
        ]);
    }

    /**
     * @Route("/ajouter/{reference}", name="cart_add_reference", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_REFE}, mode=HasPermission::IN_JSON)
     */
    public function addReferenceToCart(ReferenceArticle $reference, EntityManagerInterface $entityManager): JsonResponse {
        $cart = $this->getUser()->getCart();
        if (!$cart->getArticles()->isEmpty()){
            return $this->json([
                'success' => false,
                'msg' => "Le panier contient déjà des articles. Supprimez les pour pouvoir ajouter des références."
            ]);
        }

        if($cart->getReferences()->contains($reference)) {
            $referenceLabel = $reference->getReference();
            return $this->json([
                'success' => false,
                'msg' => "La référence <strong>${referenceLabel}</strong> est déjà présente dans votre panier"
            ]);
        }
        $cart->addReference($reference);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La référence a bien été ajoutée au panier",
            "count" => $cart->getReferences()->count()
        ]);
    }

    /**
     * @Route("/infos/livraison/{request}", name="cart_delivery_data", options={"expose"=true}, methods="GET")
     */
    public function deliveryRequestData(Demande $request): JsonResponse {
        $type = $request->getType();

        return $this->json([
            "success" => true,
            "comment" => $request->getCommentaire(),
            "freeFields" => $this->renderView('free_field/freeFieldsShow.html.twig', [
                'containerClass' => null,
                'freeFields' => $type ? $type->getChampsLibres()->toArray() : [],
                'values' => $request->getFreeFields() ?? [],
                'emptyLabel' => 'Cette demande ne contient aucun champ libre'
            ])
        ]);
    }

    /**
     * @Route("/infos/collecte/{request}", name="cart_collect_data", options={"expose"=true}, methods="GET")
     */
    public function collectRequestData(Collecte $request): JsonResponse {
        $type = $request->getType();
        return $this->json([
            "success" => true,
            "destination" => $request->isDestruct() ? "Destruction" : "Mise en stock",
            "object" => $request->getObjet(),
            "comment" => $request->getCommentaire(),
            "freeFields" => $this->renderView('free_field/freeFieldsShow.html.twig', [
                'containerClass' => null,
                'freeFields' => $type ? $type->getChampsLibres()->toArray() : [],
                'values' => $request->getFreeFields() ?? [],
                'emptyLabel' => 'Cette demande ne contient aucun champ libre'
            ])
        ]);
    }

    /**
     * @Route("/retirer/{reference}", name="cart_remove_reference", options={"expose"=true})
     */
    public function removeReference(EntityManagerInterface $manager, ReferenceArticle $reference): Response {
        $cart = $this->getUser()->getCart();
        $cart->removeReference($reference);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La référence a bien été retirée du panier",
            "count" => $cart->getReferences()->count(),
        ]);
    }

    /**
     * @Route("/validate-cart", name="cart_validate", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     */
    public function validateCart(Request $request,
                                 EntityManagerInterface $entityManager,
                                 CartService $cartService) {
        $data = json_decode($request->getContent(), true);

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $response = match ($data["requestType"]) {
            "delivery" => $cartService->manageDeliveryRequest($data, $loggedUser, $entityManager),
            "collect" => $cartService->manageCollectRequest($data, $loggedUser, $entityManager),
            "purchase" => $cartService->managePurchaseRequest($data, $loggedUser, $entityManager),
            default => throw new RuntimeException("Unsupported request type"),
        };

        if ($response["success"]) {
            $this->addFlash("success", $response['msg']);
        }

        return $this->json($response);
    }

    #[Route("/add-to-cart-logistic-units", name: "cart_add_logistic_units", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::TRACA, Action::DISPLAY_PACK], mode: HasPermission::IN_JSON)]
    public function addLogisticUnitsToCart(Request                $request,
                                           EntityManagerInterface $entityManager): Response {
        $data = json_decode($request->getContent(), true);
        $ids = $data["id"];
        $response = [];
        $wrongProject = [];
        $alreadyInCart = [];
        $addedArticles = [];
        $unavailableArticles = [];
        $cart = $this->getUser()->getCart();

        $packRepository = $entityManager->getRepository(Pack::class);

        if ($cart->getReferences()->count()){
            $response[] = [
                "success" => false,
                "msg" => "Le panier contient déjà des références. Supprimez les pour pouvoir ajouter des unités logistiques"
            ];
            return $this->json([
                "messages" => $response,
            ]);
        }
        else {
            $cartContent = $cart->getArticles()->toArray();
            $cartContentStream = Stream::from($cartContent);
            foreach ($ids as $id) {
                $unit = $packRepository->findOneBy(["id" => $id]);
                if ($unit) {
                    $rightCartProject = $cartContentStream->isEmpty() || $cartContentStream->every(fn(Article $article) =>
                        $article->getCurrentLogisticUnit()?->getProject()?->getId()
                        === $unit->getProject()?->getId()
                    );

                    if (!$rightCartProject) { // error
                        $wrongProject[] = $unit->getCode();
                    }
                    else {
                        $unitAlreadyInProject = $cartContentStream->some(fn(Article $article) => $article->getCurrentLogisticUnit()?->getId() === $unit->getId());
                        if ($unitAlreadyInProject) { // error
                            $alreadyInCart[] = $unit->getCode();
                        }
                        else {
                            foreach ($unit->getChildArticles() as $article) {
                                if ($article->getStatut()?->getCode() !== Article::STATUT_ACTIF) { // error
                                    $unavailableArticles[] = [
                                        'barCode' => $article->getBarCode(),
                                        'unit' => $unit->getCode()
                                    ];
                                }
                                else { // success
                                    $cart->addArticle($article);
                                    $addedArticles[] = [
                                        'barCode' => $article->getBarCode(),
                                        'unit' => $unit->getCode()
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($wrongProject)) {
            $response[] = [
                "success" => false,
                "msg" => count($wrongProject) === 1
                    ? "L'unité logistique " . $wrongProject[0] . " ne peut pas être ajoutée au panier car le panier ne peut avoir des unités logistiques que d'un seul projet"
                    : "Les unités logistiques  " . join(", ", $wrongProject) . " ne peuvent pas être ajoutées au panier car le panier ne peut avoir des unités logistiques que d'un seul projet"
            ];
        }

        if (!empty($unavailableArticles)) {
            $unavailableArticlesGrouped = Stream::from($unavailableArticles)
                ->keymap(fn($article) => [$article['unit'], $article['barCode']], true)
                ->toArray();

            foreach ($unavailableArticlesGrouped as $unit => $articles) {
                $articlesStr = join(', ', $articles);
                $response[] = [
                    "success" => false,
                    "msg" => count($articles) === 1
                        ? "L'article {$articlesStr} présent dans l'unité logistique {$unit} n'est pas disponible, il ne peut pas être ajouté au panier"
                        : "Les articles {$articlesStr} présents dans l'unité logistique {$unit} ne sont pas disponibles, ils ne peuvent pas être ajoutés au panier"
                ];
            }
        }

        if (!empty($alreadyInCart)) {
            $response[] = [
                "success" => false,
                "msg" => count($alreadyInCart) === 1
                    ? "L'unité logistique " . $alreadyInCart[0] . " est déjà dans le panier"
                    : "Les unités logistiques " . join(", ", $alreadyInCart) . " sont déjà dans le panier"
            ];
        }

        if(!empty($addedArticles)) {
            $addedArticlesGrouped = Stream::from($addedArticles)
                ->keymap(fn($article) => [$article['unit'], $article['barCode']], true)
                ->toArray();

            foreach ($addedArticlesGrouped as $unit => $articles) {
                $articlesStr = join(", ", $articles);
                $response[] = [
                    "success" => true,
                    "msg" => count($articles) === 1
                        ? "L'unité logistique {$unit} et l'article contenu {$articlesStr} ont bien été ajoutés au panier"
                        : "L'unité logistique {$unit} et les articles contenus {$articlesStr} ont bien été ajoutés au panier"
                ];
            }
        }

        $entityManager->flush();
        return $this->json([
            "messages" => $response,
            "cartQuantity" => $cart->getArticles()->count() ?? $cart->getReferences()->count()
        ]);
    }


    #[Route("/articles-logistics-unit-api", name: "articles_logistics_unit_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    //#[HasPermission([Menu::ORDRE, Action::DISPLAY_RECE], mode: HasPermission::IN_JSON)]
    public function getLogisticsUnitAndArticlesApi(): JsonResponse {


        $articlesInCart = $this->getUser()->getCart()->getArticles();
        $logisticsUnits = [];
        foreach ($articlesInCart as $article) {
            if (!in_array($article->getCurrentLogisticUnit(), $logisticsUnits)) {
                array_push($logisticsUnits, $article->getCurrentLogisticUnit());
            }
        }
        foreach ($logisticsUnits as $logisticsUnit) {
            $articles = [];
            foreach ($articlesInCart as $article) {
                if ($article->getCurrentLogisticUnit()?->getId() == $logisticsUnit->getId()) {
                    array_push($articles,array(
                        'id' => $article->getId(),
                        'reference' => $article->getReference(),
                        'barCode' => $article->getBarCode(),
                        'label' => $article->getLabel(),
                        'batch' => $article->getBatch(),
                        'quantity' => $article->getQuantite(),
                        'actions' => $this->renderView('cart/datatableRemoveArticle.html.twig', [
                            'articleId' => $article->getId()
                        ]),
                    ));
                }
            }
            $result[] = [
                'pack' => [
                    "packId" => $logisticsUnit->getId(),
                    "code" => $logisticsUnit->getCode() ?? null,
                    "location" => $logisticsUnit->getLastDrop()?->getEmplacement()?->getLabel() ?? null,
                    "project" => $logisticsUnit->getProject()?->getCode() ?? null,
                    "nature" => $logisticsUnit->getNature()?->getLabel() ?? null,
                    "color" => $logisticsUnit->getNature()?->getColor() ?? null,
                    "quantity" => $logisticsUnit->getQuantity() ?? null,
                    "quantityArticleInLocation" => count($logisticsUnit->getChildArticles()) ?? null,
                    "articles" => $articles ?? null,
                ]
            ];
        }
        return $this->json([
            "success" => true,
            "html" => $this->renderView("cart/line-list.html.twig", [
                "lines" => $result,
            ]),
        ]);
    }

    #[Route("/articles-remove-row-cart-api", name: "articles_remove_row_cart_api", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    public function deleteRow(EntityManagerInterface $entityManager, Request $request): Response {
        try {
            $type = $request->query->get('type');
            $cart = $this->getUser()?->getCart();
            if ($type == 'article') {
                $articleId = $request->query->get('id');
                $articleRepository = $entityManager->getRepository(Article::class);
                $article = $articleRepository->find($articleId);
                $cart->removeArticle($article);
                $entityManager->flush();
                return $this->json([
                    "success" => true,
                    "msg" => "L'article a bien été supprimé du panier",
                ]);
            }elseif ($type == 'ul') {
                $packId = $request->query->get('id');
                $articles = $cart->getArticles();
                foreach ($articles as $article) {
                    if ($article->getCurrentLogisticUnit()?->getId() == $packId) {
                        $cart->removeArticle($article);
                    }
                }
                $entityManager->flush();

                return $this->json([
                    "success" => true,
                    "msg" => "L'unité logistique a bien été supprimé du panier",
                ]);
            }
        } catch(Throwable $e) {
            return $this->json([
                "success" => false,
                "msg" => $e->getMessage(),
            ]);
        }
    }
}
