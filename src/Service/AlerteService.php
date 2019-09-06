<?php

namespace App\Service;

use App\Entity\AlerteExpiry;

use App\Entity\AlerteStock;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\ReferenceArticle;
use App\Repository\AlerteExpiryRepository;

use App\Repository\StatutRepository;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;

class AlerteService
{
	/**
	 * @var AlerteExpiryRepository
	 */
    private $alerteExpiryRepository;

	/**
	 * @var StatutRepository
	 */
    private $statutRepository;


    public function __construct(StatutRepository $statutRepository, AlerteExpiryRepository $alerteExpiryRepository)
    {
		$this->alerteExpiryRepository = $alerteExpiryRepository;
		$this->statutRepository = $statutRepository;
    }

	/**
	 * @param AlerteExpiry $alerte
	 * @return bool
	 * @throws \Exception
	 */
    public function isAlerteExpiryActive($alerte)
	{
		$refArticle = $alerte->getRefArticle();

		if ($refArticle) {
			$expiryDate = $alerte->getRefArticle()->getExpiryDate();
			if (!$expiryDate) return false;

			switch ($alerte->getTypePeriod()) {
				case AlerteExpiry::TYPE_PERIOD_DAY:
					$interval = 'D';
					break;
				case AlerteExpiry::TYPE_PERIOD_WEEK:
					$interval = 'W';
					break;
				case AlerteExpiry::TYPE_PERIOD_MONTH:
					$interval = 'M';
					break;
				default:
					return false;
			}

			$dateAlerte = $expiryDate->sub(new \DateInterval('P' . $alerte->getNbPeriod() . $interval));
			$now = new \DateTime('now');

			return $now >= $dateAlerte;
		} else {
			//TODO CG cas alerte sur toutes réf
		}
	}

	/**
	 * @param AlerteStock $alerte
	 * @param bool $onlySecurityAlert
	 * @return bool
	 */
	public function isAlerteStockActive($alerte, $onlySecurityAlert)
	{
		$refArticle = $alerte->getRefArticle();
		$statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

		if ($refArticle) {
			// gestion par article
			if ($refArticle->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
				$articlesFournisseurs = $refArticle->getArticlesFournisseur();

				$stockRef = 0;
				foreach ($articlesFournisseurs as $articleFournisseur) {
					$quantityByAF = 0;
					foreach ($articleFournisseur->getArticles() as $article) {
						if ($article->getStatut() == $statut) $quantityByAF += $article->getQuantite();
					}
					$stockRef += $quantityByAF;
				}

			// gestion par référence
			} else {
				$stockRef = $refArticle->getQuantiteStock();
			}

			if ($onlySecurityAlert) {
				return $stockRef <= $alerte->getLimitSecurity();
			} else {
				return $stockRef <= $alerte->getLimitSecurity() || $stockRef <= $alerte->getLimitAlert();
			}

		} else {
			return false;
		}
	}

}
