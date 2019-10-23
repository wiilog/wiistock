<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use App\Repository\CollecteReferenceRepository;
use App\Repository\MailerServerRepository;
use App\Repository\StatutRepository;

use Doctrine\ORM\EntityManagerInterface;

class OrdreCollecteService
{
	/**
	 * @var EntityManagerInterface
	 */
    private $em;
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

    public function __construct(CollecteReferenceRepository $collecteReferenceRepository, MailerService $mailerService, StatutRepository $statutRepository, EntityManagerInterface $em, \Twig_Environment $templating)
	{
		$this->templating = $templating;
		$this->em = $em;
		$this->statutRepository = $statutRepository;
		$this->mailerService = $mailerService;
		$this->collecteReferenceRepository = $collecteReferenceRepository;
	}

	/**
	 * @param OrdreCollecte $collecte
	 * @param Utilisateur $user
	 * @param string $date
	 * @throws \Exception
	 */
	public function finishCollecte($collecte, $user, $date)
	{
		// on modifie le statut de l'ordre de collecte
		$collecte
			->setUtilisateur($user)
			->setStatut($this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setDate($date);

		// on modifie le statut de la demande de collecte
		$demandeCollecte = $collecte->getDemandeCollecte();
		$demandeCollecte->setStatut($this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_COLLECTE));

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

		// on modifie la quantité des articles de référence liés à la collecte
		$collecteReferences = $this->collecteReferenceRepository->findByCollecte($demandeCollecte);

		$addToStock = $demandeCollecte->getStockOrDestruct();

		// cas de mise en stockage
		if ($addToStock) {
			foreach ($collecteReferences as $collecteReference) {
				$refArticle = $collecteReference->getReferenceArticle();
				$refArticle->setQuantiteStock($refArticle->getQuantiteStock() + $collecteReference->getQuantite());
			}

			// on modifie le statut des articles liés à la collecte
			$articles = $demandeCollecte->getArticles();
			foreach ($articles as $article) {
				$article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF));
			}
		}
		$this->em->flush();
	}

}
