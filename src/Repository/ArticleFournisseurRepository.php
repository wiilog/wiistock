<?php

namespace App\Repository;

use App\Entity\ArticleFournisseur;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method ArticleFournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleFournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleFournisseur[]    findAll()
 * @method ArticleFournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleFournisseurRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Code Fournisseur' => 'fournisseur',
        'Référence' => 'reference',
        'Article de référence' => 'art_ref',
        'label' => 'label',
    ];

    public function findBySearch($value)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.id, a.label as text
          FROM App\Entity\ArticleFournisseur a
          WHERE a.label LIKE :search"
        )->setParameter('search', '%' . $value . '%');

        return $query->execute();
    }

    public function getGroupedByRefArticle() {

        $queryBuilder = $this->createQueryBuilder('article_fournisseur');

        $articlesFournisseurs = $queryBuilder
            ->addSelect('article_fournisseur.label as providerArticleLabel')
            ->addSelect('reference_article.id as idRef')
            ->addSelect('fournisseur.nom as providerName')
            ->join('article_fournisseur.referenceArticle', 'reference_article')
            ->join('article_fournisseur.fournisseur', 'fournisseur')
            ->getQuery()
            ->execute();

        return array_reduce($articlesFournisseurs, function(array $accumulator, array $articleFournisseur) {
            $idRef = $articleFournisseur['idRef'];
            $providerArticleLabel = $articleFournisseur['providerArticleLabel'];
            $providerName = $articleFournisseur['providerName'];
            if (!isset($accumulator[$idRef])) {
                $accumulator[$idRef] = [
                    'providerNames' => '',
                    'providerArticlesLabels' => ''
                ];
            }
            $accumulator[$idRef]['providerNames'] .= ((!empty($accumulator[$idRef]['providerNames']) ? ', ' : '') . $providerName);
            $accumulator[$idRef]['providerArticlesLabels'] .= ((!empty($accumulator[$idRef]['providerArticlesLabels']) ? ', ' : '') . $providerArticleLabel);

            return $accumulator;
        }, []);
    }

    public function getByFournisseur($fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.fournisseur = :fournisseurId"
        )->setParameters(['fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }


    public function findByRefArticle($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT rf
            FROM App\Entity\ArticleFournisseur rf
            WHERE rf.referenceArticle = :id"
        )->setParameter('id', $id);

        return $query->getResult();
    }

    public function countByRefArticle($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(rf)
            FROM App\Entity\ArticleFournisseur rf
            WHERE rf.referenceArticle = :id"
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param int $refArticleId
	 * @param int $fournisseurId
	 * @return ArticleFournisseur[]|null
	 */
    public function findByRefArticleAndFournisseur($refArticleId, $fournisseurId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.referenceArticle = :refArticleId
              AND af.fournisseur = :fournisseurId"
        )->setParameters(['refArticleId' => $refArticleId, 'fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }

    public function findByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $countTotal = $this->countAll();

        $qb
            ->select('af')
            ->from('App\Entity\ArticleFournisseur', 'af');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    if ($column === 'fournisseur') {
                        $qb
                            ->leftJoin('af.fournisseur', 'f')
                            ->orderBy('f.nom', $order);
                    } else if ($column === 'art_ref') {
                        $qb
                            ->leftJoin('af.referenceArticle', 'ra')
                            ->orderBy('ra.libelle', $order);
                    } else if ($column === 'label') {
                        $qb
                            ->orderBy('af.label', $order);
                    } else {
                        $qb
                            ->orderBy('af.' . $column, $order);
                    }
                }
            }
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('af.fournisseur', 'f2')
                        ->leftJoin('af.referenceArticle', 'ra2')
                        ->andWhere('f2.nom LIKE :value OR af.reference LIKE :value OR ra2.libelle LIKE :value OR af.label LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            $qb->select('count(af)');
            $countQuery = (int) $qb->getQuery()->getSingleScalarResult();
        } else {
            $countQuery = $countTotal;
        }
        $qb
            ->select('af');
        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(af)
            FROM App\Entity\ArticleFournisseur af
           "
        );

        return $query->getSingleScalarResult();
    }

    public function getByRefArticleAndFournisseur($refArticleId, $fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.referenceArticle = :refArticleId AND af.fournisseur = :fournisseurId"
        )->setParameters(['refArticleId' => $refArticleId, 'fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }

    public function countByFournisseur($fournisseurId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT COUNT(af)
			FROM App\Entity\ArticleFournisseur af
			WHERE af.fournisseur = :fournisseurId"
		)->setParameter('fournisseurId', $fournisseurId);

		return $query->getSingleScalarResult();
	}

    public function getIdAndLibelleBySearch($search, $refArticle = null)
    {
        $queryBuilder = $this->createQueryBuilder('articleFournisseur')
            ->addSelect('articleFournisseur.id AS id')
            ->addSelect('articleFournisseur.reference AS text')
            ->andWhere('articleFournisseur.reference LIKE :search')
            ->setParameter('search', '%' . $search . '%');

        if (!empty($refArticle)) {
            $queryBuilder
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->andWhere('referenceArticle.reference = :referenceArticleReference')
                ->setParameter('referenceArticleReference', $refArticle);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getIdAndLibelleBySearchAndRef($search, $ref)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT articleFournisseur.id,
                         articleFournisseur.reference as text
          FROM App\Entity\ArticleFournisseur articleFournisseur
          WHERE articleFournisseur.reference LIKE :search AND articleFournisseur.referenceArticle = :ref"
        )->setParameters([
            'search' => '%' . $search . '%',
            'ref' => $ref,
        ]);

        return $query->execute();
    }

	/**
	 * @param ReferenceArticle $ref
	 * @return array
	 */
    public function getIdAndLibelleByRef($ref)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT articleFournisseur.id,
                         articleFournisseur.reference as reference
          FROM App\Entity\ArticleFournisseur articleFournisseur
          WHERE articleFournisseur.referenceArticle = :ref"
        )->setParameter('ref', $ref);

        return $query->execute();
    }

    /**
     * @param $reference
     * @return array
     */
    public function getIdAndLibelleByRefRef($reference)
    {
        $queryBuilder = $this->createQueryBuilder('articleFournisseur');
        $queryBuilder
            ->select('articleFournisseur.id AS id')
            ->addSelect('articleFournisseur.reference as reference')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->where('referenceArticle.reference = :reference')
            ->setParameter('reference', $reference);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * @param string $reference
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByReference($reference): int
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(articleFournisseur)
           FROM App\Entity\ArticleFournisseur articleFournisseur
           WHERE articleFournisseur.reference = :reference"
        )->setParameter('reference', $reference);

        return (int) $query->getSingleScalarResult();
    }

    /**
     * @param string $label
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByLabel($label): int
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(articleFournisseur)
           FROM App\Entity\ArticleFournisseur articleFournisseur
           WHERE articleFournisseur.label = :label"
        )
            ->setParameter('label', $label);

        return (int) $query->getSingleScalarResult();
    }
}
