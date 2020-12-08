<?php

namespace App\Repository;

use App\Entity\Pack;
use App\Entity\Fournisseur;
use App\Helper\Stream;
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

    public function getCodesAndLabelsGroupedByReference(): array {
        $queryBuilder = $this->createQueryBuilder('fournisseur');

        return Stream::from($queryBuilder
                ->distinct()
                ->select('fournisseur.nom as supplierLabel')
                ->addSelect('fournisseur.codeReference as supplierCode')
                ->addSelect('referenceArticle.id')
                ->innerJoin('fournisseur.articlesFournisseur', 'articlesFournisseur')
                ->innerJoin('articlesFournisseur.referenceArticle', 'referenceArticle')
                ->getQuery()
                ->getResult())
            ->reduce(function(array $carry, array $supplierWithRefId) {
                $refId = $supplierWithRefId['id'];
                $supplierCode = $supplierWithRefId['supplierCode'];
                $supplierLabel = $supplierWithRefId['supplierLabel'];

                if(!isset($carry[$refId])) {
                    $carry[$refId] = [
                        "supplierCodes" => $supplierCode,
                        "supplierLabels" => $supplierLabel,
                    ];
                } else {
                    $carry[$refId]['supplierCodes'] .= ', ' . $supplierCode;
                    $carry[$refId]['supplierLabels'] .= ', ' . $supplierLabel;
                }

                return $carry;
            }, []);
    }

    public function getIdAndCodeBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f.id,
                    f.codeReference as text,
                    f.nom AS name
          FROM App\Entity\Fournisseur f
          WHERE f.nom LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function getIdAndLabelseBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f.id,
                    f.codeReference AS code,
                    f.nom as text
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

    public function getByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb->from('App\Entity\Fournisseur', 'fournisseur');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $qb
                        ->orderBy('fournisseur.' . self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']], $order);
                }
            }
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('fournisseur.nom LIKE :value OR fournisseur.codeReference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $queryCountFilterd = $qb
            ->select('COUNT(fournisseur)')
            ->getQuery();

        $countFilterd = $queryCountFilterd->getSingleScalarResult();

        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb
            ->select('fournisseur')
            ->getQuery();

        return [
            'recordsTotal' => (int) $this->countAll(),
            'recordsFiltered' => (int) $countFilterd,
            'data' => $query->getResult()
        ];
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
	 * @param Pack $colis
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
            JOIN a.packs c
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
