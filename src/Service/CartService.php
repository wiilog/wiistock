<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Cart;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\Demande;
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

class CartService {

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

    public function renderPurchaseTypeModal(Cart $cart, EntityManagerInterface $entityManager) {
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
        $purchases = $purchaseRepository->findByState(Statut::DRAFT);

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
            ->map(function(ReferenceArticle $referenceArticle) {
                return [
                    'articles' => $referenceArticle->getAssociatedArticles(),
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
                    'articles' => $referenceArticle->getAssociatedArticles(),
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

    private function emptyCart(Cart $cart) {
        $cart->getReferences()->clear();
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
            $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
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
                $reference = $referenceRepository->findOneByReference($datum);
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
                                          Utilisateur $utilisateur,
                                          PurchaseRequestService $purchaseRequestService,
                                          EntityManagerInterface $entityManager,
                                          Cart $cart): array
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $status = $statusRepository->findOneByCategorieNameAndStatutState(CategorieStatut::PURCHASE_REQUEST, Statut::DRAFT);

        $requestsByBuyer = [];
        if ($status) {
            foreach ($data as $key => $datum) {
                if (str_starts_with($key, 'reference')) {
                    if (preg_match('/(-(.\d*)-(.\d*))/', $key, $match) == 1) {
                        $buyerID = $match[2];
                        $index = $match[3];
                        $associatedPurchaseRequest = $data['purchase-' . $buyerID] ?: ($requestsByBuyer[$buyerID] ?? null);
                        $wantedQuantity = intval($data['quantity-' . $buyerID . '-' . $index]);
                        $reference = $referenceArticleRepository->findOneByReference($datum);

                        if ($associatedPurchaseRequest) {
                            $request = $associatedPurchaseRequest instanceof PurchaseRequest
                                ? $associatedPurchaseRequest
                                : $purchaseRequestRepository->find($associatedPurchaseRequest);
                        } else {
                            $buyer = $reference->getBuyer();
                            $request = $purchaseRequestService->createPurchaseRequest($entityManager, $status, $utilisateur, null, null, $buyer);
                            $entityManager->persist($request);
                            $entityManager->flush();
                        }
                        $refAlreadyInRequest = !$request->getPurchaseRequestLines()->filter(function (PurchaseRequestLine $line) use ($reference) {
                            return $line->getReference() === $reference;
                        })->isEmpty();
                        if (!$refAlreadyInRequest && (!$request->getBuyer() || $request->getBuyer()->getId() === intval($buyerID))) {
                            $line = new PurchaseRequestLine();
                            $line
                                ->setRequestedQuantity($wantedQuantity)
                                ->setReference($reference)
                                ->setPurchaseRequest($request);
                            $entityManager->persist($line);
                        }
                        $requestsByBuyer[$buyerID] = $request;
                    }
                }
            }
        }
        $this->emptyCart($cart);
        $entityManager->flush();
        return $requestsByBuyer;
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
                ->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')))
                ->setStatut($draft);
            $entityManager->persist($request);
            $entityManager->flush();
        }

        foreach ($data as $key => $datum) {
            if (str_starts_with($key, 'reference')) {
                $index = intval(substr($key, 9));
                $reference = $referenceRepository->findOneByReference($datum);
                $quantityToDeliver = $data['quantity' . $index] ?? null;
                $data['quantity-to-pick'] = $quantityToDeliver;
                $refArticleDataService->addRefToDemand($data, $reference, $utilisateur, false, $entityManager, $request, null, false, true);
            } else if (str_starts_with($key, 'article')) {
                $index = intval(substr($key, 7));
                $article = $articleRepository->find($datum);
                $quantityToDeliver = $data['quantity' . $index] ?? null;

                $article
                    ->setDemande($request)
                    ->setQuantiteAPrelever(max($quantityToDeliver, 0)); // protection contre quantités négatives
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
                ->setCreationDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')))
                ->setStatus($draft);
            $entityManager->persist($request);
            $entityManager->flush();
        }

        foreach ($data as $key => $datum) {
            if (str_starts_with($key, 'reference')) {
                $reference = $referenceRepository->findOneByReference($datum);
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
