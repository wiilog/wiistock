<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;
use Symfony\Component\Routing\RouterInterface;

class CartService {

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public Environment $twig;

    /** @Required */
    public Security $security;

    /** @Required */
    public FreeFieldService $freeFieldService;

    /** @Required */
    public DemandeLivraisonService $demandeLivraisonService;

    /** @Required */
    public PurchaseRequestService $purchaseRequestService;

    private function emptyCart(Cart $cart, ?array $referencesToRemove = null): void {
        if ($referencesToRemove) {
            /** @var ReferenceArticle $reference */
            foreach ($cart->getReferences()->toArray() as $reference) {
                if (in_array($reference->getId(), $referencesToRemove)) {
                    $cart->removeReference($reference);
                }
            }
        } else {
            $cart->getReferences()->clear();
        }
    }

    public function managePurchaseRequest($data,
                                          Utilisateur $user,
                                          EntityManagerInterface $entityManager): array
    {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $status = $statusRepository->findOneByCategorieNameAndStatutState(CategorieStatut::PURCHASE_REQUEST, Statut::DRAFT);
        $treatedCartReferences = [];

        if ($status) {
            $requestsByBuyer = Stream::from(json_decode($data['buyers'], true))
                ->keymap(fn(array $buyerData) => [
                    $buyerData['buyer'],
                    !empty($buyerData['existingPurchase']) ? $entityManager->find(PurchaseRequest::class, $buyerData['existingPurchase']) : null
                ])
                ->toArray();
            $cart = json_decode($data['cart'], true);
            foreach ($cart as $referenceData) {
                $reference = !empty($referenceData['reference'])
                    ? $entityManager->find(ReferenceArticle::class, $referenceData['reference'])
                    : null;

                if ($reference) {
                    $buyer = $reference->getBuyer();
                    if ($buyer) {
                        $quantity = (int)$referenceData['quantity'] ?? 0;
                        $associatedPurchaseRequest = $requestsByBuyer[$buyer->getId()] ?? null;

                        if ($quantity > 0) {
                            if (!isset($associatedPurchaseRequest)) {
                                $associatedPurchaseRequest = $this->purchaseRequestService->createPurchaseRequest($entityManager, $status, $user, null, null, $buyer);
                                $entityManager->persist($associatedPurchaseRequest);

                                $entityManager->flush();
                                $requestsByBuyer[$buyer->getId()] = $associatedPurchaseRequest;
                            }

                            /** @var PurchaseRequestLine|null $associatedLine */
                            $associatedLine = $associatedPurchaseRequest
                                ->getPurchaseRequestLines()
                                ->filter(fn(PurchaseRequestLine $line) => $line->getReference() === $reference)
                                ->first() ?: null;

                            $treatedCartReferences[] = $reference->getId();

                            if ($associatedLine) {
                                if ($associatedLine->getRequestedQuantity() < $quantity) {
                                    $associatedLine->setRequestedQuantity($quantity);
                                }
                            } else {
                                $line = new PurchaseRequestLine();
                                $line
                                    ->setRequestedQuantity($quantity)
                                    ->setReference($reference)
                                    ->setPurchaseRequest($associatedPurchaseRequest);
                                $entityManager->persist($line);
                            }

                        }
                    }
                }
            }

            $buyers = array_keys($requestsByBuyer);
            if (count($buyers) === 1) {
                $buyer = $buyers[0];
                $purchaseRequest = $requestsByBuyer[$buyer];
                $link = $this->router->generate('purchase_request_show', ['id' => $purchaseRequest->getId()]);
            }
            else {
                $link = $this->router->generate('purchase_request_index');
            }
        }
        $this->emptyCart($user->getCart(), $treatedCartReferences);
        $entityManager->flush();

        return [
            "success" => true,
            "msg" => "Les références ont bien été ajoutées dans des demandes d'achat",
            "link" => $link ?? null,
        ];
    }

    public function manageDeliveryRequest(array $data,
                                          Utilisateur $user,
                                          EntityManagerInterface $manager): array {
        $cartContent = json_decode($data['cart'], true);

        $msg = '';
        $link = '';
        if ($data['addOrCreate'] === "add") {
            $deliveryRequest = $manager->find(Demande::class, $data['existingDelivery']);
            $this->addReferencesToCurrentUserCart($manager, $user, $deliveryRequest, $cartContent);

            $manager->flush();

            $link = $this->router->generate('demande_show', ['id' => $deliveryRequest->getId()]);
            $msg = "Les références ont bien été ajoutées dans la demande existante";
        }
        else if ($data['addOrCreate'] === "create") {
            $statutRepository = $manager->getRepository(Statut::class);
            $destination = $manager->find(Emplacement::class, $data['destination']);
            $type = $manager->find(Type::class, $data['deliveryType']);
            $draft = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::DEM_LIVRAISON,
                Demande::STATUT_BROUILLON
            );
            $deliveryRequest = new Demande();

            $deliveryRequest
                ->setNumero($this->demandeLivraisonService->generateNumeroForNewDL($manager))
                ->setUtilisateur($user)
                ->setType($type)
                ->setFilled(false)
                ->setCreatedAt(new DateTime('now'))
                ->setDestination($destination)
                ->setStatut($draft);

            $this->freeFieldService->manageFreeFields($deliveryRequest, $data, $manager);
            $manager->persist($deliveryRequest);

            $this->addReferencesToCurrentUserCart($manager, $user, $deliveryRequest, $cartContent);

            $manager->flush();

            $link = $this->router->generate('demande_show', ['id' => $deliveryRequest->getId()]);
            $msg = "Les references ont bien été ajoutées dans une nouvelle demande de livraison";
        }

        return [
            "success" => true,
            "msg" => $msg,
            "link" => $link
        ];
    }

    public function manageCollectRequest(array $data,
                                         Utilisateur $user,
                                         EntityManagerInterface $manager): array {
        $cartContent = json_decode($data['cart'], true);

        $msg = '';
        $link = '';
        if ($data['addOrCreate'] === "add") {
            $collectRequest = $manager->find(Collecte::class, $data['existingCollect']);
            $this->addReferencesToCurrentUserCart($manager, $user, $collectRequest, $cartContent);

            $manager->flush();

            $link = $this->router->generate('collecte_show', ['id' => $collectRequest->getId()]);
            $msg = "Les références ont bien été ajoutées dans la demande existante";
        }
        else if ($data['addOrCreate'] === "create") {
            $draftStatus = $manager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(
                CategorieStatut::DEM_COLLECTE,
                Collecte::STATUT_BROUILLON
            );
            $type = $manager->find(Type::class, $data['collectType']);
            $collectLocation = $manager->find(Emplacement::class, $data['location']);
            $number = 'C-' . (new DateTime('now'))->format('YmdHis');
            $collectRequest = new Collecte();

            $collectRequest
                ->setNumero($number)
                ->setDate(new DateTime('now'))
                ->setType($type)
                ->setStatut($draftStatus)
                ->setObjet($data['object'])
                ->setStockOrDestruct(!($data['destination'] == 0))
                ->setPointCollecte($collectLocation)
                ->setCommentaire($data['comment'])
                ->setDemandeur($user);

            $this->freeFieldService->manageFreeFields($collectRequest, $data, $manager);
            $manager->persist($collectRequest);

            $this->addReferencesToCurrentUserCart($manager, $user, $collectRequest, $cartContent);

            $manager->flush();

            $link = $this->router->generate('collecte_show', ['id' => $collectRequest->getId()]);
            $msg = "Les references ont bien été ajoutées dans une nouvelle demande de collecte";
        }

        return [
            "success" => true,
            "msg" => $msg,
            "link" => $link
        ];
    }

    private function addReferencesToCurrentUserCart(EntityManagerInterface $entityManager,
                                                    Utilisateur            $user,
                                                                           $request,
                                                    array                  $cart) {
        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $references = $referenceRepository->findByIds(
            Stream::from($cart)
                ->map(fn($referenceData) => $referenceData['reference'])
                ->toArray()
        );
        foreach ($cart as $referenceData) {
            $referenceId = $referenceData['reference'];
            $quantity = $referenceData['quantity'];
            $reference = $references[$referenceId];
            if($request instanceof Demande) {
                /** @var DeliveryRequestReferenceLine|null $alreadyInRequest */
                $alreadyInRequest = Stream::from($request->getReferenceLines())
                    ->filter(fn(DeliveryRequestReferenceLine $line) => $line->getReference() === $reference)
                    ->first() ?: null;

                if($alreadyInRequest) {
                    if ($alreadyInRequest->getQuantityToPick() < $quantity) {
                        $alreadyInRequest
                            ->setQuantityToPick($quantity);
                    }
                } else {
                    $deliveryRequestLine = (new DeliveryRequestReferenceLine())
                        ->setReference($reference)
                        ->setQuantityToPick($quantity);

                    $request->addReferenceLine($deliveryRequestLine);
                }
            }
            else if($request instanceof Collecte) {
                /** @var CollecteReference|null $alreadyInRequest */
                $alreadyInRequest = Stream::from($request->getCollecteReferences())
                    ->filter(fn(CollecteReference $line) => $line->getReferenceArticle() === $reference)
                    ->first() ?: null;

                if ($alreadyInRequest) {
                    if ($alreadyInRequest->getQuantite() < $quantity) {
                        $alreadyInRequest
                            ->setQuantite($quantity);
                    }
                } else {
                    $collectRequestLine = new CollecteReference();
                    $collectRequestLine
                        ->setReferenceArticle($reference)
                        ->setQuantite($quantity);
                    $request->addCollecteReference($collectRequestLine);
                }
            }
        }

        $this->emptyCart($user->getCart());
    }

}
