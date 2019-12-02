<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use App\Repository\CollecteReferenceRepository;
use App\Repository\MailerServerRepository;
use App\Repository\StatutRepository;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
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

    public function __construct(MailerServerRepository $mailerServerRepository,
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
	}

	public function setEntityManager(EntityManagerInterface $entityManager): self {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * TODO CG mouvements
     * @param OrdreCollecte $collecte
     * @param Utilisateur $user
     * @param DateTime $date
     * @param Emplacement $depositLocation
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
	public function finishCollecte(OrdreCollecte $collecte, Utilisateur $user, DateTime $date, Emplacement $depositLocation, array $mouvements)
	{
		// on modifie le statut de l'ordre de collecte
		$collecte
			->setUtilisateur($user)
			->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setDate($date);

		// on modifie le statut de la demande de collecte
		$demandeCollecte = $collecte->getDemandeCollecte();
		$demandeCollecte->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Collecte::CATEGORIE, Collecte::STATUS_COLLECTE));

		// on modifie la quantité des articles de référence liés à la collecte
		$collecteReferences = $this->collecteReferenceRepository->findByCollecte($demandeCollecte);

		$addToStock = $demandeCollecte->getStockOrDestruct();

		// cas de mise en stockage
		if ($addToStock) {
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
			$articles = $demandeCollecte->getArticles();
			foreach ($articles as $article) {
				$article
                    ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Article::CATEGORIE, Article::STATUT_ACTIF))
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

        if ($this->mailerServerRepository->findAll()) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Collecte effectuée',
                $this->templating->render(
                    'mails/mailCollecteDone.html.twig',
                    [
                        'title' => 'Votre demande a bien été collectée.',
                        'collecte' => $demandeCollecte,

                    ]
                ),
                $demandeCollecte->getDemandeur()->getEmail()
            );
        }
	}
}
