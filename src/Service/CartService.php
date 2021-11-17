<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Cart;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;
use  Symfony\Component\Routing\RouterInterface;

class CartService {

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public Environment $twig;

    /** @Required */
    public Security $security;

    public function getDataForDatatable($params = null) {
        $referenceRepository = $this->manager->getRepository(ReferenceArticle::class);

        $queryResult = $referenceRepository->findInCart($this->security->getUser(), $params);

        $references = $queryResult['data'];

        $rows = [];
        foreach ($references as $reference) {
            $rows[] = $this->dataRowReference($reference);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"],
            "recordsTotal" => $queryResult["total"],
        ];
    }

    private function dataRowReference(ReferenceArticle $reference): array {
        return [
            "actions" => "<i class='fas fa-trash remove-reference pointer' data-id='{$reference->getId()}'></i>",
            "label" => $reference->getLibelle(),
            "reference" => $reference->getReference(),
            "supplierReference" => Stream::from($reference->getArticlesFournisseur())
                ->map(fn(ArticleFournisseur $article) => $article->getReference())
                ->join(";"),
            "type" => $reference->getType()->getLabel(),
            "availableQuantity" => $reference->getQuantiteDisponible(),
        ];
    }

    public function renderPurchaseTypeModal(Cart $cart, EntityManagerInterface $entityManager, Utilisateur $requester) {
        $purchaseRepository = $entityManager->getRepository(PurchaseRequest::class);
        $refs = Stream::from($cart->getReferences())
            ->map(function(ReferenceArticle $referenceArticle) {
                return [
                    'reference' => $referenceArticle->getReference(),
                    'buyer' => $referenceArticle->getBuyer() ? $referenceArticle->getBuyer()->getId() : null
                ];
            })->reduce(function(array $carry, array $referenceArticle) {
                if ($referenceArticle['buyer']) {
                    $carry[$referenceArticle['buyer']][] = $referenceArticle;
                }
                return $carry;
            }, []);
        $purchases = $purchaseRepository->findByStateAndRequester(Statut::DRAFT, $requester);
        return [
            'html' => $this->twig->render('cart/purchaseTypeContent.html.twig', [
                'refsByBuyer' => $refs,
                'purchases' => $purchases,
            ]),
            'count' => count($refs),
            'message' => 'Les références du panier n\'ont aucun acheteur.',
        ];
    }

    public function renderDeliveryTypeModal(Cart $cart, EntityManagerInterface $entityManager) {
        $deliveryRepository = $entityManager->getRepository(Demande::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $parametreRepository = $entityManager->getRepository(Parametre::class);
        $parametreRoleRepository = $entityManager->getRepository(ParametreRole::class);

        $managed = $parametreRoleRepository->findOneBy([
            'role' => $cart->getUser()->getRole(),
            'parametre' => $parametreRepository->findOneBy([
                'label' => Parametre::LABEL_AJOUT_QUANTITE
            ]),
        ]);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_BROUILLON);
        $refs = Stream::from($cart->getReferences())
            ->map(function (ReferenceArticle $referenceArticle) {
                return [
                    'articles' => Stream::from($referenceArticle->getAssociatedArticles())
                        ->filter(fn(Article $article) => $article->getStatut()->getCode() === Article::STATUT_ACTIF && $article->getQuantite() > 0)
                        ->toArray(),
                    'reference' => $referenceArticle->getReference(),
                ];
            })->toArray();
        $deliveries = $deliveryRepository->findBy([
            'utilisateur' => $cart->getUser(),
            'statut' => $draft
        ]);
        return [
            'html' => $this->twig->render('cart/deliveryTypeContent.html.twig', [
                'refs' => $refs,
                'deliveries' => $deliveries,
                'managedByArticle' => $managed && $managed->getValue() == Parametre::VALUE_PAR_ART,
            ]),
            'count' => count($refs),
            'message' => 'Le panier est vide.',
        ];
    }

    public function renderTransferTypeModal(Cart $cart, EntityManagerInterface $entityManager) {
        $transferRepository = $entityManager->getRepository(TransferRequest::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);
        $refs = Stream::from($cart->getReferences())
            ->map(function(ReferenceArticle $referenceArticle) {
                return [
                    'articles' => Stream::from($referenceArticle->getAssociatedArticles())
                        ->filter(fn(Article $article) => $article->getStatut()->getCode() === Article::STATUT_ACTIF && $article->getQuantite() > 0)
                        ->toArray(),
                    'reference' => $referenceArticle->getReference(),
                ];
            })->toArray();
        $transfers = $transferRepository->findBy([
            'requester' => $cart->getUser(),
            'status' => $draft
        ]);

        return [
            'html' => $this->twig->render('cart/transferTypeContent.html.twig', [
                'refs' => $refs,
                'transfers' => $transfers,
            ]),
            'count' => count($refs),
            'message' => 'Le panier est vide.',
        ];
    }

    public function renderCollectTypeModal(Cart $cart, EntityManagerInterface $entityManager) {
        $collectsRepository = $entityManager->getRepository(Collecte::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_BROUILLON);
        $refs = Stream::from($cart->getReferences())
            ->map(function(ReferenceArticle $referenceArticle) {
                return [
                    'reference' => $referenceArticle->getReference(),
                ];
            })->toArray();
        $collects = $collectsRepository->findBy([
            'demandeur' => $cart->getUser(),
            'statut' => $draft
        ]);
        return [
            'html' => $this->twig->render('cart/collectTypeContent.html.twig', [
                'refs' => $refs,
                'collects' => $collects,
            ]),
            'count' => count($refs),
            'message' => 'Le panier est vide.',
        ];
    }

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

    public function manageCollectRequest($data,
                                         Utilisateur $utilisateur,
                                         EntityManagerInterface $entityManager,
                                         Cart $cart): Collecte {
        $collectRepository = $entityManager->getRepository(Collecte::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_BROUILLON);
        $collect = $data['collect'];
        if ($collect) {
            $request = $collectRepository->find(intval($collect));
        } else {
            $date = new DateTime('now');
            $request = new Collecte();
            $request
                ->setDemandeur($utilisateur)
                ->setFilled(false)
                ->setDate($date)
                ->setStockOrDestruct(true)
                ->setNumero('C-' . $date->format('YmdHis'))
                ->setStatut($draft);
            $entityManager->persist($request);
            $entityManager->flush();
        }

        foreach ($data as $key => $datum) {
            if (str_starts_with($key, 'reference')) {
                $index = intval(substr($key, 9));
                $reference = $referenceRepository->findOneBy(['reference' => $datum]);
                $quantityToDeliver = $data['quantity' . $index] ?? null;
                $collecteReference = new CollecteReference();
                $collecteReference
                    ->setCollecte($request)
                    ->setReferenceArticle($reference)
                    ->setQuantite(max($quantityToDeliver, 0)); // protection contre quantités négatives
                $entityManager->persist($collecteReference);
            }
        }
        $this->emptyCart($cart);
        $entityManager->flush();
        return $request;
    }

    public function managePurchaseRequest($data,
                                          Utilisateur $user,
                                          PurchaseRequestService $purchaseRequestService,
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
                                $associatedPurchaseRequest = $purchaseRequestService->createPurchaseRequest($entityManager, $status, $user, null, null, $buyer);
                                $entityManager->persist($associatedPurchaseRequest);

                                $entityManager->flush();
                                $requestsByBuyer[$buyer->getId()] = $associatedPurchaseRequest;
                            }
                            if (count(json_decode($data['buyers'], true)) === 1) {
                                $idToRedirect = $associatedPurchaseRequest->getId();
                                $redirect = $this->router->generate('purchase_request_show', ['id' => $idToRedirect]);
                            } else {
                                $redirect = $this->router->generate('purchase_request_index');
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
        }
        $this->emptyCart($user->getCart(), $treatedCartReferences);
        $entityManager->flush();

        return [
            "success" => true,
            "msg" => "Les références ont bien étées ajoutées dans des demandes d'achat",
            "link" => $redirect,
        ];
    }

    public function manageDeliveryRequest($data,
                                           DemandeLivraisonService $demandeLivraisonService,
                                           Utilisateur $utilisateur,
                                           RefArticleDataService $refArticleDataService,
                                           EntityManagerInterface $entityManager,
                                           Cart $cart): Demande {
        $deliveryRepository = $entityManager->getRepository(Demande::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_BROUILLON);
        $delivery = $data['delivery'];
        if ($delivery) {
            $request = $deliveryRepository->find(intval($delivery));
        } else {
            $request = new Demande();
            $request
                ->setNumero($demandeLivraisonService->generateNumeroForNewDL($entityManager))
                ->setUtilisateur($utilisateur)
                ->setFilled(false)
                ->setCreatedAt(new DateTime('now'))
                ->setStatut($draft);
            $entityManager->persist($request);
            $entityManager->flush();
        }

        foreach ($data as $key => $datum) {
            if (str_starts_with($key, 'reference')) {
                $index = intval(substr($key, 9));
                $reference = $referenceRepository->findOneBy(['reference' => $datum]);
                $quantityToDeliver = $data['quantity' . $index] ?? null;
                $data['quantity-to-pick'] = $quantityToDeliver;
                $refArticleDataService->addRefToDemand($data, $reference, $utilisateur, false, $entityManager, $request, null, false, true);
            } else if (str_starts_with($key, 'article')) {
                $index = intval(substr($key, 7));
                /** @var Article $article */
                $article = $articleRepository->find($datum);
                $quantityToDeliver = $data['quantity' . $index] ?? null;

                $articleLine = $demandeLivraisonService->createArticleLine($article, $request, max($quantityToDeliver, 0));
                $entityManager->persist($articleLine);
            }
        }
        $this->emptyCart($cart);
        $entityManager->flush();
        return $request;
    }

    public function manageTransferRequest($data,
                                          UniqueNumberService $uniqueNumberService,
                                          Utilisateur $utilisateur,
                                          EntityManagerInterface $entityManager,
                                          Cart $cart): TransferRequest {
        $transferRequestRepository = $entityManager->getRepository(TransferRequest::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);
        $type = $entityManager->getRepository(Type::class)->findOneByCategoryLabel(CategoryType::TRANSFER_REQUEST);
        $transferRequestNumber = $uniqueNumberService->createUniqueNumber(
            $entityManager,
            TransferRequest::NUMBER_PREFIX,
            TransferRequest::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT
        );

        $transfer = $data['transfer'];
        if ($transfer) {
            $request = $transferRequestRepository->find(intval($transfer));
        } else {
            $request = new TransferRequest();
            $request
                ->setNumber($transferRequestNumber)
                ->setRequester($utilisateur)
                ->setFilled(false)
                ->setType($type)
                ->setCreationDate(new DateTime('now'))
                ->setStatus($draft);
            $entityManager->persist($request);
            $entityManager->flush();
        }

        foreach ($data as $key => $datum) {
            if (str_starts_with($key, 'reference')) {
                $reference = $referenceRepository->findOneBy(['reference' => $datum]);
                $request->addReference($reference);
            } else if (str_starts_with($key, 'article')) {
                $article = $articleRepository->find($datum);
                $request->addArticle($article);
            }
        }
        $this->emptyCart($cart);
        $entityManager->flush();
        return $request;
    }

}
