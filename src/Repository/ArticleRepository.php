<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Demande;
use App\Entity\InventoryFrequency;
use App\Entity\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function getReferencesByRefAndDate($refPrefix, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a.reference
            FROM App\Entity\Article a
            WHERE a.reference LIKE :refPrefix'
		)->setParameter('refPrefix', $refPrefix . $date . '%');

		return array_column($query->execute(), 'reference');
	}

    public function findByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.reception = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

    public function setNullByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'UPDATE App\Entity\Article a
            SET a.reception = null
            WHERE a.reception = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

	/**
	 * @param int $id
	 * @return Article[]|null
	 */
    public function findByCollecteId($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             JOIN a.collectes c
             WHERE c.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

	/**
	 * @param Demande|int $demande
	 * @return Article[]|null
	 */
    public function findByDemande($demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             WHERE a.demande =:demande
            "
        )->setParameter('demande', $demande);
        return $query->execute();
    }

    public function getByDemandeAndType($demande, $type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             WHERE a.demande =:demande AND a.type = :type
            "
        )->setParameters([
            'demande' => $demande,
            'type' => $type
        ]);
        return $query->execute();
    }


    public function findByEmplacement($empl)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.emplacement = :empl'
        )->setParameter('empl', $empl);;
        return $query->execute();
    }

    public function findByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            WHERE a.statut = :statut "
        )->setParameter('statut', $statut);;
        return $query->execute();
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            WHERE a.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function setTypeIdNull($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "UPDATE App\Entity\Article a
            SET a.type = null 
            WHERE a.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function getArticleByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id = :id "
        )->setParameter('id', $id);;
        return $query->execute();
    }

    public function getIdRefLabelAndQuantity()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.id, a.reference, a.label, a.quantite
            FROM App\Entity\Article a
            "
        );
        return $query->execute();
    }

	public function getIdAndRefBySearch($search, $activeOnly = false)
	{
		$em = $this->getEntityManager();

		$dql = "SELECT a.id, a.reference as text
          FROM App\Entity\Article a
          LEFT JOIN a.statut s
          WHERE a.reference LIKE :search";

		if ($activeOnly) {
			$dql .= " AND s.nom = '" . Article::STATUT_ACTIF . "'";
		}

		$query = $em
			->createQuery($dql)
			->setParameter('search', '%' . $search . '%');

		return $query->execute();
	}

    public function getRefByRecep($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.reference
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }
    public function findByPreparation($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            JOIN a.preparations p
            WHERE p.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

	/**
	 * @param ReferenceArticle $refArticle
	 * @param Statut $statut
	 * @return Article[]
	 */
	public function findByRefArticleAndStatut($refArticle, $statut)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle
			'
		)->setParameters([
			'refArticle' => $refArticle,
			'statut' => $statut
		]);

		return $query->execute();
	}

	public function getTotalQuantiteFromRefNotInDemand($refArticle, $statut) {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT SUM(a.quantite)
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle AND a.demande is null
			'
        )->setParameters([
            'refArticle' => $refArticle,
            'statut' => $statut
        ]);

        return $query->getSingleScalarResult();
    }

	public function getTotalQuantiteByRefAndStatusLabel($refArticle, $statusLabel) {
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT SUM(a.quantite)
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			JOIN a.statut s
			WHERE s.nom =:statusLabel AND ra = :refArticle
			'
		)->setParameters([
			'refArticle' => $refArticle,
			'statusLabel' => $statusLabel
		]);

		return $query->getSingleScalarResult();
	}

	public function findByRefArticleAndStatutWithoutDemand($refArticle, $statut)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle
			AND a.demande is null
			ORDER BY a.quantite DESC
			'
		)->setParameters([
			'refArticle' => $refArticle,
			'statut' => $statut
		]);

		return $query->execute();
	}

    public function findByEtat($etat)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.etat = :etat'
        )->setParameter('etat', $etat);;
        return $query->execute();
    }

    public function findAllSortedByName()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.nom, a.id, a.quantite
          FROM App\Entity\Article a
          ORDER BY a.nom
          "
        );
        return $query->execute();
    }

    public function findByParamsAndStatut($params = null, $statutLabel = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a');

        if ($statutLabel) {
            $qb
                ->join('a.statut', 's')
                ->where('s.nom = :statutLabel')
                ->setParameter('statutLabel', $statutLabel);
        }

		$countQuery = $countTotal = count($qb->getQuery()->getResult());

        $allArticleDataTable = null;
		// prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('a.articleFournisseur', 'af')
                        ->leftJoin('af.referenceArticle', 'ra')
                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value OR ra.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = count($qb->getQuery()->getResult());
            }
            $allArticleDataTable = $qb->getQuery();
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();
        return ['data' => $query ? $query->getResult() : null , 'allArticleDataTable' => $allArticleDataTable ? $allArticleDataTable->getResult() : null,
            'count' => $countQuery, 'total' => $countTotal];
    }

	/**
	 * @param ArticleFournisseur[] $listAf
	 * @return Article[]|null
	 */
    public function findByListAF($listAf)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a
          FROM App\Entity\Article a
          JOIN a.articleFournisseur af
          WHERE af IN(:articleFournisseur)"
        )->setParameters([
            'articleFournisseur' => $listAf,
        ]);

        return $query->execute();
    }

    public function countActiveArticles()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.statut s
            WHERE s.nom = :active"
		)->setParameter('active', Article::STATUT_ACTIF);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a"
		);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param int $limit
	 * @return Article[]|null
	 */
    public function findByQuantityMoreThan($limit)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT a
			FROM App\Entity\Article a
			WHERE a.quantite > :limit"
		)->setParameter('limit', $limit);

		return $query->execute();
	}

	/**
	 * @return Article[]|null
	 */
	public function findDoublons()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT a1
			FROM App\Entity\Article a1
			WHERE a1.reference IN (
				SELECT a2.reference FROM App\Entity\Article a2
				GROUP BY a2.reference
				HAVING COUNT(a2.reference) > 1)"
		);

		return $query->execute();
	}

	public function getByPreparationStatutLabelAndUser($statutLabel, $enCours, $user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT a.reference, e.label as location, a.label, a.quantiteAPrelever as quantity, 0 as is_ref, p.id as id_prepa
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.demande d
			JOIN d.preparation p
			JOIN p.statut s
			WHERE s.nom = :statutLabel OR (s.nom = :enCours AND p.utilisateur = :user)"
		)->setParameters([
		    'statutLabel' => $statutLabel,
            'enCours' => $enCours,
            'user' => $user
        ]);

		return $query->execute();
	}

	public function getRefArticleByPreparationStatutLabelAndUser($statutLabel, $enCours, $user) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT 
                    DISTINCT a.reference,
                    a.label, 
                    e.label as location, 
                    a.quantite as quantity, 
                    ra.reference as reference_article
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			JOIN ra.ligneArticles la
			JOIN la.demande d
			JOIN d.preparation p
			JOIN p.statut s
			WHERE s.nom = :statutLabel OR (s.nom = :enCours AND p.utilisateur = :user)"
        )->setParameters([
            'statutLabel' => $statutLabel,
            'enCours' => $enCours,
            'user' => $user
        ]);

        return $query->execute();
    }

	public function getByLivraisonStatutLabelAndWithoutOtherUser($statutLabel, $user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT a.reference, e.label as location, a.label, a.quantiteAPrelever as quantity, 0 as is_ref, l.id as id_livraison
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.demande d
			JOIN d.livraison l
			JOIN l.statut s
			WHERE s.nom = :statutLabel AND (l.utilisateur is null OR l.utilisateur = :user)"
		)->setParameters([
			'statutLabel' => $statutLabel,
			'user' => $user
		]);

		return $query->execute();
	}

	/**
	 * @param string $reference
	 * @return Article|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function findOneByReference($reference)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param string $reference
	 * @return Article|null
	 */
	public function findByReference($reference)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->execute();
	}

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(a)
			FROM App\Entity\Article a
			JOIN a.emplacement e
			WHERE e.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param InventoryMission $mission
	 * @return mixed
	 */
    public function getByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT a.label, a.reference, a.hasInventoryAnomaly, a.id
            FROM App\Entity\Article a
            JOIN a.inventoryMissions m
            LEFT JOIN a.inventoryEntries e
            WHERE m = :mission"
        )->setParameter('mission', $mission);

        return $query->execute();
    }


    /**
     * @param InventoryMission $mission
     * @param int $artId
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getEntryDateByMission($mission, $artId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.date
            FROM App\Entity\InventoryEntry e
            WHERE e.mission = :mission AND e.article = :art"
        )->setParameters([
            'mission' => $mission,
            'art' => $artId
        ]);
        return $query->getOneOrNullResult();
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\InventoryMission im
            LEFT JOIN im.articles a
            WHERE im = :mission
"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function getIdAndReferenceBySearch($search, $activeOnly = false)
    {
        $em = $this->getEntityManager();

        $dql = "SELECT a.id as id, a.reference as text
          FROM App\Entity\Article a
          LEFT JOIN a.statut s
          WHERE a.reference LIKE :search";

        if ($activeOnly) {
            $dql .= " AND s.nom = '" . Article::STATUT_ACTIF . "'";
        }

        $query = $em
            ->createQuery($dql)
            ->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

	/**
	 * @param InventoryFrequency $frequency
	 * @param int $limit
	 * @return Article[]
	 */
	public function findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT a
            FROM App\Entity\Article a
            JOIN a.articleFournisseur af
            JOIN af.referenceArticle ra
            JOIN ra.category c
            LEFT JOIN a.statut sa
            LEFT JOIN a.emplacement ae
            WHERE c.frequency = :frequency
            AND ra.typeQuantite = :typeQuantity 
            AND a.dateLastInventory is null 
            AND sa.nom = :status
            ORDER BY ae.label"
		)->setParameters([
			'frequency' => $frequency,
			'typeQuantity' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
			'status' => Article::STATUT_ACTIF,
		]);

		if ($limit)	$query->setMaxResults($limit);

		return $query->execute();
	}
}
