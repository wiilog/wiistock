<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use App\Repository\MailerServerRepository;
use App\Repository\StatutRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

    public function __construct(StatutRepository $statutRepository, EntityManagerInterface $em, \Twig_Environment $templating)
	{
		$this->templating = $templating;
		$this->em = $em;
		$this->statutRepository = $statutRepository;
	}

	/**
	 * @param OrdreCollecte $collecte
	 * @throws \Exception
	 */
	public function finishCollecte($collecte, $user)
	{
		// on modifie le statut de l'ordre de collecte
		$collecte
			->setUtilisateur($user)
			->setStatut($this->statutRepository->findOneByCategorieAndStatut(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setDate(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

		// on modifie le statut de la demande de collecte
		$demandeCollecte = $collecte->getDemandeCollecte();
		$demandeCollecte->setStatut($this->statutRepository->findOneByCategorieAndStatut(Collecte::CATEGORIE, Collecte::STATUS_COLLECTE));

		if ($this->mailerServerRepository->findAll()) {
			$this->mailerService->sendMail(
				'FOLLOW GT // Collecte effectuée',
				$this->renderView(
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
		$ligneArticles = $this->collecteReferenceRepository->findByCollecte($collecte->getDemandeCollecte());

		$addToStock = $demandeCollecte->getStockOrDestruct();

		// cas de mise en stockage
		if ($addToStock) {
			foreach ($ligneArticles as $ligneArticle) {
				$refArticle = $ligneArticle->getReferenceArticle();
				$refArticle->setQuantiteStock($refArticle->getQuantiteStock() + $ligneArticle->getQuantite());
			}

			// on modifie le statut des articles liés à la collecte
			$demandeCollecte = $collecte->getDemandeCollecte();

			$articles = $demandeCollecte->getArticles();
			foreach ($articles as $article) {
				$article->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF));
			}
		}

		$this->getDoctrine()->getManager()->flush();
	}




}
