<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Collecte;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Menu;
use App\Entity\Cart;
use App\Entity\Setting;
use App\Entity\PurchaseRequest;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\CartService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use App\Controller\AbstractController;
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
    public function addToCart(ReferenceArticle $reference, EntityManagerInterface $entityManager): JsonResponse {
        /** @var Cart $cart */
        $cart = $this->getUser()->getCart();
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
}
