<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\MailerServerRepository;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use DateTime;
use Exception;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;

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
	 * @var MailerServerRepository
	 */
	private $mailerServerRepository;
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

	private $mouvementTracaService;
	private $mouvementStockService;
	private $stringService;

    public function __construct(RouterInterface $router,
    							TokenStorageInterface $tokenStorage,
								MailerServerRepository $mailerServerRepository,
                                MailerService $mailerService,
                                MouvementStockService $mouvementStockService,
                                EntityManagerInterface $entityManager,
                                MouvementTracaService $mouvementTracaService,
                                StringService $stringService,
                                Twig_Environment $templating)
	{
	    $this->mailerServerRepository = $mailerServerRepository;
	    $this->stringService = $stringService;
		$this->templating = $templating;
		$this->entityManager = $entityManager;
		$this->mailerService = $mailerService;
		$this->mouvementTracaService = $mouvementTracaService;
		$this->user = $tokenStorage->getToken()->getUser();
		$this->router = $router;
		$this->mouvementStockService = $mouvementStockService;
	}

	public function setEntityManager(EntityManagerInterface $entityManager): self {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @param OrdreCollecte $ordreCollecte
     * @param Utilisateur $user
     * @param DateTime $date
     * @param Emplacement $depositLocation
     * @param array $mouvements
     * @param bool $fromNomade
     * @return OrdreCollecte|null
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws Exception
     */
    public function finishCollecte(OrdreCollecte $ordreCollecte,
                                   Utilisateur $user,
                                   DateTime $date,
                                   ?Emplacement $depositLocation,
                                   array $mouvements,
                                   bool $fromNomade = false)
	{
		$em = $this->entityManager;

		$statutRepository = $em->getRepository(Statut::class);
		$articleRepository = $em->getRepository(Article::class);
		$ordreCollecteReferenceRepository = $em->getRepository(OrdreCollecteReference::class);


		$demandeCollecte = $ordreCollecte->getDemandeCollecte();
		$dateNow = new DateTime('now', new DateTimeZone('Europe/Paris'));

		$listRefRef = $listArtRef = [];
		$referenceToQuantity = [];
		$artToQuantity = [];

        $statutATraiter = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ORDRE_COLLECTE, OrdreCollecte::STATUT_A_TRAITER);
		if ($statutATraiter->getId() !== $ordreCollecte->getStatut()->getId()) {
            throw new Exception(self::COLLECTE_ALREADY_BEGUN);
        }

		if (empty($mouvements)) {
		    throw new Exception(self::COLLECTE_MOUVEMENTS_EMPTY);
        }

		foreach($mouvements as $mouvement) {
		    $quantity = $mouvement['quantity'] ?? $mouvement['quantite'];
			if ($mouvement['is_ref']) {
				$listRefRef[] = $mouvement['barcode'];
                $referenceToQuantity[$mouvement['barcode']] = $quantity;
			} else {
				$listArtRef[] = $mouvement['barcode'];
                $artToQuantity[$mouvement['barcode']] = $quantity;
			}
		}

		// on construit la liste des lignes à transférer vers une nouvelle collecte
		$rowsToRemove = [];
		$listOrdreCollecteReference = $ordreCollecteReferenceRepository->findByOrdreCollecte($ordreCollecte);
		foreach ($listOrdreCollecteReference as $ordreCollecteReference) {
		    /** @var ReferenceArticle $refArticle */
			$refArticle = $ordreCollecteReference->getReferenceArticle();
			if (!in_array($refArticle->getBarCode(), $listRefRef)) {
				$rowsToRemove[] = [
					'id' => $refArticle->getId(),
					'isRef' => 1
				];
			}
			else {
                $quantity = $referenceToQuantity[$refArticle->getBarCode()];
                $oldQuantity = $ordreCollecteReference->getQuantite();
                if($quantity > 0 && $quantity < $oldQuantity) {
                    $ordreCollecteReference->setQuantite($quantity);
                }
            }
		}

		$listArticles = $articleRepository->findByOrdreCollecteId($ordreCollecte->getId());
		foreach ($listArticles as $article) {
			if (!in_array($article->getBarCode(), $listArtRef)) {
				$rowsToRemove[] = [
					'id' => $article->getId(),
					'isRef' => 0
				];
			}
			else {
                $quantity = $artToQuantity[$article->getBarCode()];
                $oldQuantity = $article->getQuantite();
                if($quantity > 0 && $quantity < $oldQuantity) {
                    $article->setQuantite($quantity);
                }
            }
		}

		// cas de collecte partielle
		if (!empty($rowsToRemove)) {
			$newCollecte = new OrdreCollecte();
			$newCollecte
				->setDate($ordreCollecte->getDate())
				->setNumero('C-' . $dateNow->format('YmdHis'))
				->setDemandeCollecte($ordreCollecte->getDemandeCollecte())
				->setStatut($statutATraiter);

			$em->persist($newCollecte);

			foreach ($rowsToRemove as $mouvement) {
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

			$demandeCollecte->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_INCOMPLETE));

			$em->flush();
		}
		else {
		// cas de collecte totale
			$demandeCollecte
				->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_COLLECTE))
				->setValidationDate($dateNow);
		}

		// on modifie le statut de l'ordre de collecte
		$ordreCollecte
			->setUtilisateur($user)
			->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setDate($date);

		// on modifie la quantité des articles de référence liés à la collecte
		$collecteReferences = $ordreCollecteReferenceRepository->findByOrdreCollecte($ordreCollecte);

		// cas de mise en stockage
		if ($demandeCollecte->getStockOrDestruct()) {
			foreach ($collecteReferences as $collecteReference) {
			    /** @var ReferenceArticle $refArticle */
				$refArticle = $collecteReference->getReferenceArticle();

                if (!$fromNomade) {
                    $refArticle->setQuantiteStock(($refArticle->getQuantiteStock() ?? 0) + $collecteReference->getQuantite());
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
		$this->entityManager->flush();

		$partialCollect = !empty($rowsToRemove);

		if ($this->mailerServerRepository->findAll()) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Collecte effectuée',
                $this->templating->render(
                    'mails/mailCollecteDone.html.twig',
                    [
                        'title' => $partialCollect ?
							'Votre demande de collecte a été partiellement effectuée.' :
							'Votre demande de collecte a bien été effectuée.',
                        'collecte' => $ordreCollecte,
						'demande' => $demandeCollecte,
                    ]
                ),
                $demandeCollecte->getDemandeur()->getMainAndSecondaryEmails()
            );
        }

		return $newCollecte ?? null;
	}

    /**
     * @param null $params
     * @param null $demandeCollecteIdFilter
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
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
			$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_COLLECTE, $this->user);
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

	/**
	 * @param OrdreCollecte $collecte
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
	private function dataRowCollecte($collecte)
	{
		$demandeCollecte = $collecte->getDemandeCollecte();

		$url['show'] = $this->router->generate('ordre_collecte_show', ['id' => $collecte->getId()]);
		return [
			'id' => $collecte->getId() ?? '',
			'Numéro' => $collecte->getNumero() ?? '',
			'Date' => $collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : '',
			'Statut' => $collecte->getStatut() ? $collecte->getStatut()->getNom() : '',
			'Opérateur' => $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '',
			'Type' => $demandeCollecte && $demandeCollecte->getType() ? $demandeCollecte->getType()->getLabel() : '',
			'Actions' => $this->templating->render('ordre_collecte/datatableCollecteRow.html.twig', [
				'url' => $url,
			])
		];
	}

    /**
     * @param Utilisateur $user
     * @param ReferenceArticle|Article $article
     * @param DateTime $date
     * @param Emplacement $locationFrom
     * @param Emplacement $locationTo
     * @param int $quantity
     * @param bool $fromNomade
     * @param OrdreCollecte $ordreCollecte
     * @throws Exception
     */
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
        $this->entityManager->persist($this->mouvementTracaService->createMouvementTraca(
            $article->getBarCode(),
            $locationFrom,
            $user,
            $date,
            $fromNomade,
            !$fromNomade,
            MouvementTraca::TYPE_PRISE,
            ['mouvementStock' => $mouvementStock]
        ));

        // si on est sur la supervision
        if (!$fromNomade) {
            $deposeDate = clone $date;
            $deposeDate->modify('+1 second');
            // mouvement de traca de dépose
            $this->entityManager->persist($this->mouvementTracaService->createMouvementTraca(
                $article->getBarCode(),
                $locationTo,
                $user,
                $deposeDate,
                $fromNomade,
                !$fromNomade,
                MouvementTraca::TYPE_DEPOSE,
                ['mouvementStock' => $mouvementStock]
            ));

            // On fini le mouvement de stock
            $this->mouvementStockService->finishMouvementStock($mouvementStock, $deposeDate, $locationTo);
        }
    }

    public function createHeaderDetailsConfig(OrdreCollecte $ordreCollecte): array {
        $demande = $ordreCollecte->getDemandeCollecte();
        $requester = $demande ? $demande->getDemandeur() : null;
        $pointCollecte = $demande ? $demande->getPointCollecte() : null;
        $dateCollecte = $ordreCollecte->getDate();
        $comment = $demande->getCommentaire();

        return [
            [ 'label' => 'Numéro', 'value' => $ordreCollecte->getNumero() ],
            [ 'label' => 'Statut', 'value' => $ordreCollecte->getStatut() ? $this->stringService->mbUcfirst($ordreCollecte->getStatut()->getNom()) : '' ],
            [ 'label' => 'Opérateur', 'value' => $ordreCollecte->getUtilisateur() ? $ordreCollecte->getUtilisateur()->getUsername() : '' ],
            [ 'label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : '' ],
            [ 'label' => 'Destination', 'value' => $demande->getStockOrDestruct() ? 'Mise en stock' : 'Destruction' ],
            [ 'label' => 'Point de collecte', 'value' => $pointCollecte ? $pointCollecte->getLabel() : '' ],
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
}
