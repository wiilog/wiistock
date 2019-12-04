<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;

use App\Repository\ArticleRepository;
use App\Repository\CollecteReferenceRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\MailerServerRepository;
use App\Repository\OrdreCollecteReferenceRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\StatutRepository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use DateTime;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig_Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

class OrdreCollecteService
{
    public const COLLECTE_ALREADY_BEGAN = 'collecte-already-began';

	/**
	 * @var EntityManagerInterface
	 */
    private $entityManager;
	/**
	 * @var \Twig_Environment
	 */
	private $templating;
	/**
	 * @var StatutRepository
	 */
	private $statutRepository;
	/**
	 * @var MailerServerRepository
	 */
	private $mailerServerRepository;
	/**
	 * @var MailerService
	 */
	private $mailerService;
	/**
	 * @var CollecteReferenceRepository
	 */
	private $collecteReferenceRepository;

	/**
	 * @var OrdreCollecteReferenceRepository
	 */
	private $ordreCollecteReferenceRepository;

	/**
	 * @var OrdreCollecteRepository
	 */
	private $ordreCollecteRepository;

	/**
	 * @var ArticleRepository
	 */
	private $articleRepository;

	/**
	 * @var FiltreSupRepository
	 */
	private $filtreSupRepository;

	/**
	 * @var Utilisateur
	 */
	private $user;

	/**
	 * @var Router
	 */
	private $router;

    public function __construct(RouterInterface $router,
    							TokenStorageInterface $tokenStorage,
    							FiltreSupRepository $filtreSupRepository,
    							OrdreCollecteRepository $ordreCollecteRepository,
    							ArticleRepository $articleRepository,
								OrdreCollecteReferenceRepository $ordreCollecteReferenceRepository,
								MailerServerRepository $mailerServerRepository,
                                CollecteReferenceRepository $collecteReferenceRepository,
                                MailerService $mailerService,
                                StatutRepository $statutRepository,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
	{
	    $this->mailerServerRepository = $mailerServerRepository;
		$this->templating = $templating;
		$this->entityManager = $entityManager;
		$this->statutRepository = $statutRepository;
		$this->mailerService = $mailerService;
		$this->collecteReferenceRepository = $collecteReferenceRepository;
		$this->ordreCollecteReferenceRepository = $ordreCollecteReferenceRepository;
		$this->articleRepository = $articleRepository;
		$this->ordreCollecteRepository = $ordreCollecteRepository;
		$this->filtreSupRepository = $filtreSupRepository;
		$this->user = $tokenStorage->getToken()->getUser();
		$this->router = $router;
	}

	public function setEntityManager(EntityManagerInterface $entityManager): self {
        $this->entityManager = $entityManager;
        return $this;
    }

	/**
	 * @param OrdreCollecte $collecte
	 * @param Utilisateur $user
	 * @param DateTime $date
	 * @param Emplacement $depositLocation
	 * @param array $mouvements
	 * @return OrdreCollecte|null
	 * @throws NonUniqueResultException
	 */
    public function buildListAndFinishCollecte(OrdreCollecte $collecte, Utilisateur $user, DateTime $date, Emplacement $depositLocation, array $mouvements)
	{
		// transforme liste articles à ajouter en liste articles à supprimer de la collecte
		$listRefRef = $listArtRef = [];
		foreach($mouvements as $mouvement) {
			if ($mouvement['is_ref']) {
				$listRefRef[] = $mouvement['reference'];
			} else {
				$listArtRef[] = $mouvement['reference'];
			}
		}

		$rowsToRemove = [];
		$listOrdreCollecteReference = $this->ordreCollecteReferenceRepository->findByOrdreCollecte($collecte);
		foreach ($listOrdreCollecteReference as $ordreCollecteReference) {
			$refArticle = $ordreCollecteReference->getReferenceArticle();
			if (!in_array($refArticle->getReference(), $listRefRef)) {
				$rowsToRemove[] = [
					'id' => $refArticle->getId(),
					'isRef' => 1
				];
			}
		}

		$listArticles = $this->articleRepository->findByOrdreCollecteId($collecte->getId());
		foreach ($listArticles as $article) {
			if (!in_array($article->getReference(), $listArtRef)) {
				$rowsToRemove[] = [
					'id' => $article->getId(),
					'isRef' => 0
				];
			}
		}

		return $this->finishCollecte($collecte, $user, $date, $depositLocation, $rowsToRemove);
	}

	/**
	 * @param OrdreCollecte $collecte
	 * @param Utilisateur $user
	 * @param DateTime $date
	 * @param Emplacement $depositLocation
	 * @param array $toRemove
	 * @return OrdreCollecte|null
	 * @throws NonUniqueResultException
	 */
	public function finishCollecte(OrdreCollecte $collecte, Utilisateur $user, DateTime $date, Emplacement $depositLocation, array $toRemove)
	{
		$em = $this->entityManager;
		$demandeCollecte = $collecte->getDemandeCollecte();
		$dateNow = new DateTime('now', new \DateTimeZone('Europe/Paris'));

		// cas de collecte partielle
		if (!empty($toRemove)) {
			$newCollecte = new OrdreCollecte();
			$statutATraiter = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_COLLECTE, OrdreCollecte::STATUT_A_TRAITER);
			$newCollecte
				->setDate($collecte->getDate())
				->setNumero('C-' . $dateNow->format('YmdHis'))
				->setDemandeCollecte($collecte->getDemandeCollecte())
				->setStatut($statutATraiter);

			$em->persist($newCollecte);

			foreach ($toRemove as $mouvement) {
				if ($mouvement['isRef'] == 1) {
					$ordreCollecteRef = $this->ordreCollecteReferenceRepository->findByOrdreCollecteAndRefId($collecte, $mouvement['id']);
					$collecte->removeOrdreCollecteReference($ordreCollecteRef);
					$newCollecte->addOrdreCollecteReference($ordreCollecteRef);
				} else {
					$article = $this->articleRepository->find($mouvement['id']);
					$collecte->removeArticle($article);
					$newCollecte->addArticle($article);
				}
			}
			$em->flush();
		} else {
		// cas de collecte totale
			$demandeCollecte
				->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Collecte::CATEGORIE, Collecte::STATUS_COLLECTE))
				->setValidationDate($dateNow);
		}

		// on modifie le statut de l'ordre de collecte
		$collecte
			->setUtilisateur($user)
			->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setDate($date);

		// on modifie la quantité des articles de référence liés à la collecte
		$collecteReferences = $this->ordreCollecteReferenceRepository->findByOrdreCollecte($collecte);

		// cas de mise en stockage
		if ($demandeCollecte->getStockOrDestruct()) {
			foreach ($collecteReferences as $collecteReference) {
				$refArticle = $collecteReference->getReferenceArticle();
				$refArticle->setQuantiteStock($refArticle->getQuantiteStock() + $collecteReference->getQuantite());

                $newMouvement = new MouvementStock();
                $newMouvement
                    ->setUser($user)
                    ->setRefArticle($refArticle)
                    ->setDate($date)
                    ->setEmplacementFrom($demandeCollecte->getPointCollecte())
                    ->setEmplacementTo($depositLocation)
                    ->setType(MouvementStock::TYPE_ENTREE)
                    ->setQuantity($collecteReference->getQuantite());
                $this->entityManager->persist($newMouvement);
			}

			// on modifie le statut des articles liés à la collecte
			$articles = $collecte->getArticles();
			foreach ($articles as $article) {
				$article
                    ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_ACTIF))
                    ->setEmplacement($depositLocation);

                $newMouvement = new MouvementStock();
                $newMouvement
                    ->setUser($user)
                    ->setArticle($article)
                    ->setDate($date)
                    ->setEmplacementFrom($demandeCollecte->getPointCollecte())
                    ->setEmplacementTo($depositLocation)
                    ->setType(MouvementStock::TYPE_ENTREE)
                    ->setQuantity($article->getQuantite());
                $this->entityManager->persist($newMouvement);
			}
		}
		$this->entityManager->flush();
//TODO CG modif mail infos ordre et pas demande
//        if ($this->mailerServerRepository->findAll()) {
//            $this->mailerService->sendMail(
//                'FOLLOW GT // Collecte effectuée',
//                $this->templating->render(
//                    'mails/mailCollecteDone.html.twig',
//                    [
//                        'title' => 'Votre demande a bien été collectée.',
//                        'collecte' => $demandeCollecte,
//
//                    ]
//                ),
//                $demandeCollecte->getDemandeur()->getEmail()
//            );
//        }

		return $newCollecte ?? null;
	}

	public function getDataForDatatable($params = null)
	{
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_COLLECTE, $this->user);
		$queryResult = $this->ordreCollecteRepository->findByParamsAndFilters($params, $filters);

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
		$row = [
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

		return $row;
	}
}
