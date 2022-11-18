<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\MouvementStock;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;
use Exception;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class OrdreCollecteService
{
    public const COLLECTE_ALREADY_BEGUN = 'collecte-already-begun';
    public const COLLECTE_MOUVEMENTS_EMPTY = 'collecte-mouvements-empty';

	/**
	 * @var EntityManagerInterface
	 */
    private $entityManager;
	/**
	 * @var Twig_Environment
	 */
	private $templating;
	/**
	 * @var MailerService
	 */
	private $mailerService;

	/**
	 * @var Utilisateur
	 */
	private $user;

	/**
	 * @var Router
	 */
	private $router;

	private $trackingMovementService;
	private $mouvementStockService;
	private $stringService;
	private $tokenStorage;

    #[Required]
	public NotificationService $notificationService;

    #[Required]
    public DemandeCollecteService $demandeCollecteService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public SpecificService $specificService;

    public function __construct(RouterInterface $router,
                                TokenStorageInterface $tokenStorage,
                                MailerService $mailerService,
                                MouvementStockService $mouvementStockService,
                                EntityManagerInterface $entityManager,
                                TrackingMovementService $trackingMovementService,
                                StringService $stringService,
                                Twig_Environment $templating)
	{
	    $this->stringService = $stringService;
		$this->templating = $templating;
		$this->entityManager = $entityManager;
		$this->mailerService = $mailerService;
		$this->trackingMovementService = $trackingMovementService;
		$this->tokenStorage = $tokenStorage;
		$this->router = $router;
		$this->mouvementStockService = $mouvementStockService;
	}

	public function setEntityManager(EntityManagerInterface $entityManager): self {
        $this->entityManager = $entityManager;
        return $this;
    }

    public function finishCollecte(OrdreCollecte $ordreCollecte,
                                   Utilisateur $user,
                                   DateTime $date,
                                   array $mouvements,
                                   bool $fromNomade = false)
	{

        $pairings = $ordreCollecte->getPairings();
        $pairingEnd = new DateTime('now');
        foreach ($pairings as $pairing) {
            if ($pairing->isActive()) {
                $pairing
                    ->setActive(false)
                    ->setEnd($pairingEnd);
            }
        }

		$em = $this->entityManager;

		$statutRepository = $em->getRepository(Statut::class);
		$settingRepository = $em->getRepository(Setting::class);
        $userRepository = $em->getRepository(Utilisateur::class);
		$ordreCollecteReferenceRepository = $em->getRepository(OrdreCollecteReference::class);
        $emplacementRepository = $em->getRepository(Emplacement::class);
        $referenceArticleRepository = $em->getRepository(ReferenceArticle::class);

        $statusActiveReference = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF);

		$demandeCollecte = $ordreCollecte->getDemandeCollecte();

		if ($ordreCollecte->getStatut()?->getCode() !== OrdreCollecte::STATUT_A_TRAITER) {
            throw new Exception(self::COLLECTE_ALREADY_BEGUN);
        }

		if (empty($mouvements)) {
		    throw new Exception(self::COLLECTE_MOUVEMENTS_EMPTY);
        }

        $mouvmentByBarcode = array_reduce(
            $mouvements,
            function (array $carry, array $current) {
                $barCode = $current['barcode'];
                $carry[$barCode] = [
                    'depositLocationId' => $current['depositLocationId'] ?? null,
                    'quantity' => $current['quantity']
                ];
                return $carry;
            },
            []
        );

		// on construit la liste des lignes à transférer vers une nouvelle collecte
		$rowsToRemove = [];
		$listOrdreCollecteReference = $ordreCollecte->getOrdreCollecteReferences();
		$articlesToAdd = [];
		foreach ($listOrdreCollecteReference as $ordreCollecteReference) {
            /** @var ReferenceArticle $refArticle */
            $refArticle = $ordreCollecteReference->getReferenceArticle();

            if ($refArticle->getStatut()->getId() !== $statusActiveReference->getId()) {
                throw new ArticleNotAvailableException();
            }
            $barCode = $refArticle->getBarCode();

            if (!isset($mouvmentByBarcode[$barCode])) {
                $rowsToRemove[] = [
                    'id' => $refArticle->getId(),
                    'isRef' => 1
                ];
            } else {
                $quantity = $mouvmentByBarcode[$barCode]['quantity'];
                if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE && $quantity > 0) {
                    $articleData = [];
                    $articleData['quantity-to-pick'] = $mouvmentByBarcode[$barCode]['quantity'];
                    $insertedArticle = $this->demandeCollecteService->persistArticleInDemand($articleData, $refArticle, $demandeCollecte);
                    $this->entityManager->persist($insertedArticle);
                    $articlesToAdd[] = $insertedArticle;
                    $this->entityManager->remove($ordreCollecteReference);
                } else {
                    $oldQuantity = $ordreCollecteReference->getQuantite();
                    if ($quantity > 0 && $quantity < $oldQuantity) {
                        $ordreCollecteReference->setQuantite($quantity);
                    }
                }
            }
        }


		$listArticles = $ordreCollecte->getArticles();
		foreach ($listArticles as $article) {
		    $barCode = $article->getBarCode();
			if (!isset($mouvmentByBarcode[$barCode])) {
				$rowsToRemove[] = [
					'id' => $article->getId(),
					'isRef' => 0
				];
			}
			else {
                $quantity = $mouvmentByBarcode[$barCode]['quantity'];
                $oldQuantity = $article->getQuantite();
                if($quantity > 0 && $quantity < $oldQuantity) {
                    $article->setQuantite($quantity);
                }
            }
		}
        foreach ($articlesToAdd as $article) {
            $ordreCollecte->addArticle($article);
            $demandeCollecte->removeArticle($article);
        }
        $this->entityManager->flush();
        $this->removeArticlesFromCollecte($rowsToRemove, $ordreCollecte, $em);

		// on modifie le statut de l'ordre de collecte
		$ordreCollecte
			->setUtilisateur($user)
			->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setTreatingDate($date);


		// on modifie la quantité des articles de référence liés à la collecte
		$collecteReferences = $ordreCollecteReferenceRepository->findByOrdreCollecte($ordreCollecte);

		// cas de mise en stockage
		if ($demandeCollecte->isStock()) {
			foreach ($collecteReferences as $collecteReference) {
			    /** @var ReferenceArticle $refArticle */
				$refArticle = $collecteReference->getReferenceArticle();

                if (!$fromNomade) {
                    $stockQuantity = ($refArticle->getQuantiteStock() ?? 0) + $collecteReference->getQuantite();
                    $referenceArticleRepository->updateFields($refArticle, [
                        'quantiteStock' => $stockQuantity
                    ]);
                    $refArticle->setQuantiteStock($stockQuantity);
                }

                $this->persistMouvementsFromStock(
                    $user,
                    $refArticle,
                    $date,
                    $demandeCollecte->getPointCollecte(),
                    $refArticle->getEmplacement(),
                    $collecteReference->getQuantite(),
					$ordreCollecte,
                    $fromNomade
                );
			}

			// on modifie le statut des articles liés à la collecte
			$articles = $ordreCollecte->getArticles();
            foreach ($articles as $article) {
                if (isset($mouvmentByBarcode[$article->getBarCode()])) {
                    $depositLocationId = $mouvmentByBarcode[$article->getBarCode()]['depositLocationId'];
                    $depositLocation = $depositLocationId ? $emplacementRepository->find($depositLocationId) : null;
                    if (!$fromNomade) {
                        $article->setEmplacement($depositLocation);
                    }

                    $statutArticle = $statutRepository->findOneByCategorieNameAndStatutCode(
                        CategorieStatut::ARTICLE,
                        $fromNomade ? Article::STATUT_INACTIF : Article::STATUT_ACTIF
                    );
                    $article->setStatut($statutArticle);

                    $this->persistMouvementsFromStock(
                        $user,
                        $article,
                        $date,
                        $demandeCollecte->getPointCollecte(),
                        $depositLocation,
                        $article->getQuantite(),
                        $ordreCollecte,
                        $fromNomade
                    );
                }
            }

            foreach ($articlesToAdd as $article) {
                $article->setStockEntryDate($date);
                $this->persistMouvementsFromStock(
                    $user,
                    $article,
                    $date,
                    $demandeCollecte->getPointCollecte(),
                    $article->getEmplacement(),
                    $article->getQuantite(),
                    $ordreCollecte,
                    $fromNomade
                );
            }
        }

		$this->entityManager->flush();

		$partialCollect = !empty($rowsToRemove);

        $kioskUser = $demandeCollecte->getDemandeur()->isKioskUser();
        $to = $kioskUser
            ? $userRepository->find($settingRepository->getOneParamByLabel(Setting::COLLECT_REQUEST_REQUESTER))
            : $demandeCollecte->getDemandeur();

        if($kioskUser && $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI)) {
            $managers = Stream::from($demandeCollecte->getCollecteReferences()->first()->getReferenceArticle()->getManagers())
                ->map(fn(Utilisateur $manager) => $manager->getEmail())
                ->toArray();
            $to = array_merge([$to], $managers);
        }

        $this->mailerService->sendMail(
            'FOLLOW GT // Collecte effectuée',
            $this->templating->render(
                'mails/contents/mailCollecteDone.html.twig',
                [
                    'title' => $partialCollect
                        ? 'Votre demande de collecte a été partiellement effectuée.'
                        : 'Votre demande de collecte a bien été effectuée.',
                    'collecte' => $ordreCollecte,
                    'demande' => $demandeCollecte,
                ]
            ),
            $to
        );

		return $newCollecte ?? null;
	}

	public function getDataForDatatable($params = null, $demandeCollecteIdFilter = null)
	{
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $ordreCollecteRepository = $this->entityManager->getRepository(OrdreCollecte::class);

		if ($demandeCollecteIdFilter) {
			$filters = [
				['field' => 'demandeCollecte',
				'value' => $demandeCollecteIdFilter]
			];
		} else {
			$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_COLLECTE, $this->tokenStorage->getToken()->getUser());
		}
		$queryResult = $ordreCollecteRepository->findByParamsAndFilters($params, $filters);

		$collectes = $queryResult['data'];

		$rows = [];
		foreach ($collectes as $collecte) {
			$rows[] = $this->dataRowCollecte($collecte);
		}

		return [
			'data' => $rows,
			'recordsTotal' => $queryResult['total'],
			'recordsFiltered' => $queryResult['count'],
		];
	}

    private function dataRowCollecte(OrdreCollecte $collecte)
    {
        $demandeCollecte = $collecte->getDemandeCollecte();

        $lastMessage = $collecte->getLastMessage();
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = !$collecte->getPairings()->isEmpty();

        $url['show'] = $this->router->generate('ordre_collecte_show', ['id' => $collecte->getId()]);
        return [
            'id' => $collecte->getId() ?? '',
            'Numéro' => $collecte->getNumero() ?? '',
            'Date' => $collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : '',
            'Statut' => $collecte->getStatut() ? $this->formatService->status($collecte->getStatut()) : '',
            'Opérateur' => $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '',
            'Type' => $demandeCollecte && $demandeCollecte->getType() ? $demandeCollecte->getType()->getLabel() : '',
            'Actions' => $this->templating->render('ordre_collecte/datatableCollecteRow.html.twig', [
                'url' => $url,
                'hasPairing' => $hasPairing,
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
        ];
    }

    private function persistMouvementsFromStock(Utilisateur $user,
                                                $article,
                                                ?DateTime $date,
                                                Emplacement $locationFrom,
                                                ?Emplacement $locationTo,
                                                int $quantity,
                                                OrdreCollecte $ordreCollecte,
                                                bool $fromNomade = false): void {

        // mouvement de stock d'entrée
        $mouvementStock = $this->mouvementStockService->createMouvementStock($user, $locationFrom, $quantity, $article, MouvementStock::TYPE_ENTREE);
        $mouvementStock->setCollecteOrder($ordreCollecte);
        $this->entityManager->persist($mouvementStock);

        // Mouvement traca prise
        $createdMvt = $this->trackingMovementService->createTrackingMovement(
            $article->getBarCode(),
            $locationFrom,
            $user,
            $date,
            $fromNomade,
            !$fromNomade,
            TrackingMovement::TYPE_PRISE,
            [
                'mouvementStock' => $mouvementStock,
                'quantity' => $mouvementStock->getQuantity()
            ]
        );
        $this->trackingMovementService->persistSubEntities($this->entityManager, $createdMvt);
        $this->entityManager->persist($createdMvt);

        // si on est sur la supervision
        if (!$fromNomade) {
            $createdPack = $createdMvt->getPack();

            $deposeDate = clone $date;
            $deposeDate->modify('+1 second');
            // mouvement de traca de dépose
            $createdMvt = $this->trackingMovementService->createTrackingMovement(
                $createdPack,
                $locationTo,
                $user,
                $deposeDate,
                $fromNomade,
                !$fromNomade,
                TrackingMovement::TYPE_DEPOSE,
                [
                    'mouvementStock' => $mouvementStock,
                    'quantity' => $mouvementStock->getQuantity()
                ]
            );
            $this->trackingMovementService->persistSubEntities($this->entityManager, $createdMvt);
            $this->entityManager->persist($createdMvt);

            // On fini le mouvement de stock
            $this->mouvementStockService->finishMouvementStock($mouvementStock, $deposeDate, $locationTo);
        }
    }

    public function createHeaderDetailsConfig(OrdreCollecte $ordreCollecte): array {
        $demande = $ordreCollecte->getDemandeCollecte();
        $requester = FormatHelper::collectRequester($demande);
        $pointCollecte = $demande ? $demande->getPointCollecte() : null;
        $dateCreation = $ordreCollecte->getDate();
        $dateCollecte = $ordreCollecte->getTreatingDate();
        $comment = $demande->getCommentaire();

        return [
            [ 'label' => 'Numéro', 'value' => $ordreCollecte->getNumero() ],
            [ 'label' => 'Statut', 'value' => $ordreCollecte->getStatut() ? $this->stringService->mbUcfirst($this->formatService->status($ordreCollecte->getStatut())) : '' ],
            [ 'label' => 'Opérateur', 'value' => $ordreCollecte->getUtilisateur() ? $ordreCollecte->getUtilisateur()->getUsername() : '' ],
            [ 'label' => 'Demandeur', 'value' => $requester],
            [ 'label' => 'Destination', 'value' => $demande->isStock() ? 'Mise en stock' : 'Destruction' ],
            [ 'label' => 'Point de collecte', 'value' => $pointCollecte ? $pointCollecte->getLabel() : '' ],
            [ 'label' => 'Date de création', 'value' => $dateCreation ? $dateCreation->format('d/m/Y H:i') : '' ],
            [ 'label' => 'Date de collecte', 'value' => $dateCollecte ? $dateCollecte->format('d/m/Y H:i') : '' ],
            [
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]
        ];
    }

    private function removeArticlesFromCollecte(array $articlesToRemove,
                                                OrdreCollecte $ordreCollecte,
                                                EntityManagerInterface $entityManager) {
        $demandeCollecte = $ordreCollecte->getDemandeCollecte();
        $statutRepository = $entityManager->getRepository(Statut::class);
        $dateNow = new DateTime('now');
        // cas de collecte partielle
        if (!empty($articlesToRemove)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $ordreCollecteReferenceRepository = $entityManager->getRepository(OrdreCollecteReference::class);
            $articleRepository = $entityManager->getRepository(Article::class);

            $statutATraiter = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ORDRE_COLLECTE, OrdreCollecte::STATUT_A_TRAITER);

            $newCollecte = new OrdreCollecte();
            $newCollecte
                ->setDate($ordreCollecte->getDate())
                ->setNumero('C-' . $dateNow->format('YmdHis'))
                ->setDemandeCollecte($ordreCollecte->getDemandeCollecte())
                ->setStatut($statutATraiter);

            $entityManager->persist($newCollecte);

            foreach ($articlesToRemove as $mouvement) {
                if ($mouvement['isRef'] == 1) {
                    $ordreCollecteRef = $ordreCollecteReferenceRepository->findByOrdreCollecteAndRefId($ordreCollecte, $mouvement['id']);
                    $ordreCollecte->removeOrdreCollecteReference($ordreCollecteRef);
                    $newCollecte->addOrdreCollecteReference($ordreCollecteRef);
                } else {
                    $article = $articleRepository->find($mouvement['id']);
                    $ordreCollecte->removeArticle($article);
                    $newCollecte->addArticle($article);
                }
            }

            $demandeCollecte->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_INCOMPLETE));

            $entityManager->flush();

            if ($newCollecte->getDemandeCollecte()->getType()->isNotificationsEnabled()) {
                $this->notificationService->toTreat($newCollecte);
            }
        }
        else {
            $statutCollecte = $statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_COLLECTE);
            // cas de collecte totale
            $demandeCollecte
                ->setStatut($statutCollecte);
        }
    }

    public function putCollecteLine($handle,
                                    CSVExportService $csvService,
                                    OrdreCollecte $ordreCollecte)
    {
        $collecte = $ordreCollecte->getDemandeCollecte();

        $dataCollecte =
            [
                $ordreCollecte->getNumero() ?? '',
                $ordreCollecte->getStatut() ? $this->formatService->status($ordreCollecte->getStatut()) : '',
                $ordreCollecte->getDate() ? $ordreCollecte->getDate()->format('d/m/Y') : '',
                $ordreCollecte->getUtilisateur() ? $ordreCollecte->getUtilisateur()->getUsername() : '',
                $collecte->getType() ? $collecte->getType()->getLabel() : ''
            ];

        foreach ($ordreCollecte->getOrdreCollecteReferences() as $ordreCollecteReference) {
            $referenceArticle = $ordreCollecteReference->getReferenceArticle();

            $data = array_merge($dataCollecte, [
                $referenceArticle->getReference() ?? '',
                $referenceArticle->getLibelle() ?? '',
                $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '',
                $ordreCollecteReference->getQuantite() ?? 0,
                $referenceArticle->getBarCode(),
            ]);
            $csvService->putLine($handle, $data);
        }

        foreach ($ordreCollecte->getArticles() as $article) {
            $articleFournisseur = $article->getArticleFournisseur();
            $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
            $reference = $referenceArticle ? $referenceArticle->getReference() : '';

            $data = array_merge($dataCollecte, [
                $reference,
                $article->getLabel() ?? '',
                $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                $article->getQuantite() ?? 0,
                $article->getBarCode(),
                $collecte->isStock() ? 'Mise en stock' : 'Destruction'
            ]);
            $csvService->putLine($handle, $data);
        }
    }

    public function createPairing(SensorWrapper $sensorWrapper, OrdreCollecte $orderCollect){
        $pairing = new Pairing();
        $start =  new DateTime("now");
        $pairing
            ->setStart($start)
            ->setSensorWrapper($sensorWrapper)
            ->setCollectOrder($orderCollect)
            ->setActive(true);

        return $pairing;
    }

}
