<?php

namespace App\Repository;

use App\Entity\Article;
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

    /**
     * @param $referenceArticle
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByReference($referenceArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT COUNT(a)
            FROM App\Entity\Article a
            WHERE a.reference LIKE :referenceArticle'
        )->setParameter('referenceArticle', '%' . $referenceArticle . '%');
        return $query->getSingleScalarResult();
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

    public function getByCollecte($id)
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

    public function getByDemande($demande)
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
    public function getByPreparation($id)
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

//    public function countByRefArticleAndStatut($refArticle, $statut)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT COUNT(a)
//            FROM App\Entity\Article a
//            WHERE a.refArticle = :refArticle AND a.etat = TRUE AND s.nom = :statut"
//        )->setParameters(['refArticle' => $refArticle, 'statut' => $statut]);
//
//        return $query->getSingleScalarResult();
//    }

    public function countByRefArticle($refArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            WHERE a.refArticle = :refArticle"
        )->setParameter('refArticle', $refArticle);

        return $query->getSingleScalarResult();
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
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();

        return ['data' => $query->getResult(), 'count' => $countQuery, 'total' => $countTotal];
    }

//    public function findByParamsActifStatut($params = null, $statutId)
//    {
//        $em = $this->getEntityManager();
//        $qb = $em->createQueryBuilder();
//
//        $qb
//            ->select('a')
//            ->from('App\Entity\Article', 'a')
//            ->where('a.statut =' . $statutId);
//
//        // prise en compte des paramètres issus du datatable
//        if (!empty($params)) {
//            if (!empty($params->get('start'))) {
//                $qb->setFirstResult($params->get('start'));
//            }
//            if (!empty($params->get('length'))) {
//                $qb->setMaxResults($params->get('length'));
//            }
//            if (!empty($params->get('search'))) {
//                $search = $params->get('search')['value'];
//                if (!empty($search)) {
//                    $qb
//                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value')
//                        ->setParameter('value', '%' . $search . '%');
//                }
//            }
//        }
//        $query = $qb->getQuery();
//        return $query->getResult();
//    }

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

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
           "
        );

        return $query->getSingleScalarResult();
    }

    public function findByQuantityMoreThan($limit)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT a
			FROM App\Entity\Article a
			WHERE a.quantite > :limit"
		)->setParameter('limit', $limit);

		return $query->execute();
	}

	public function findDoublons()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT a1
			FROM App\Entity\Article a1
			WHERE a1.reference IN (
				SELECT a2.reference FROM App\Entity\Article a2
				GROUP BY a2.reference
				HAVING COUNT(a2.reference) > 1)"
		);

		return $query->execute();
	}
}
