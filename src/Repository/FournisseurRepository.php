<?php

namespace App\Repository;

use App\Entity\Colis;
use App\Entity\Fournisseur;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method Fournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fournisseur[]    findAll()
 * @method Fournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FournisseurRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'Nom' => 'nom',
        'Code de référence' => 'codeReference',
    ];

    /**
     * @param $code
     * @return Fournisseur|null
     * @throws NonUniqueResultException
     */
    public function findOneByCodeReference($code)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT f
          FROM App\Entity\Fournisseur f
          WHERE f.codeReference = :search"
        )->setParameter('search', $code);

        return $query->getOneOrNullResult();
    }

    public function countByCode($code)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(f)
          FROM App\Entity\Fournisseur f
          WHERE f.codeReference = :code"
        )->setParameter('code', $code);

        return $query->getSingleScalarResult();
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f.id, f.nom as text
          FROM App\Entity\Fournisseur f
          WHERE f.nom LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function getIdAndLibelleBySearchForFilter($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f.nom as id, f.nom as text
          FROM App\Entity\Fournisseur f
          WHERE f.nom LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function findByRefArticle($refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
          FROM App\Entity\Fournisseur f
          WHERE f.refenceArticle = :refArticle
          "
        )->setParameter('refArticle', $refArticle);

        return $query->execute();
    }

    public function findByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Fournisseur', 'a');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $qb
                        ->orderBy('a.' . self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']], $order);
                }
            }
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('a.nom LIKE :value OR a.codeReference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Fournisseur a
           "
        );

        return $query->getSingleScalarResult();
    }

    public function findAllSorted()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f FROM App\Entity\Fournisseur f
            ORDER BY f.nom
            "
        );

        return $query->execute();
    }

	/**
	 * @param Colis $colis
	 * @return Fournisseur
	 * @throws NonUniqueResultException
	 */
    public function findOneByColis($colis)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
            FROM App\Entity\Fournisseur f
            JOIN f.arrivages a
            JOIN a.colis c
            WHERE c = :colis"
        )->setParameter('colis', $colis);

        return $query->getOneOrNullResult();
    }

    public function getNameAndRefArticleFournisseur($ref)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT DISTINCT f.nom, af.reference
            FROM App\Entity\Fournisseur f
            JOIN f.articlesFournisseur af
            WHERE af.referenceArticle = :ref"
        )->setParameter('ref', $ref);

        return $query->execute();
    }
}
