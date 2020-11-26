<?php

namespace App\Repository;

use App\Entity\Emplacement;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Emplacement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emplacement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emplacement[]    findAll()
 * @method Emplacement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmplacementRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'Nom' => 'label',
        'Description' => 'description',
        'Point de livraison' => 'isDeliveryPoint',
        'Délai maximum' => 'dateMaxTime',
        'Actif / Inactif' => 'isActive',
        'allowed-natures' => 'allowed-natures',
    ];

    public function getLocationsArray()
    {
        return $this->createQueryBuilder('location')
            ->select('location.id')
            ->addSelect('location.label')
            ->where('location.isActive = true')
            ->getQuery()
            ->getResult();
    }

    public function countAll()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(e)
            FROM App\Entity\Emplacement e"
        );
        return $query->getSingleScalarResult();
    }


    /**
	 * @param string $label
	 * @param int|null $emplacementId
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
    public function countByLabel($label, $emplacementId = null)
    {
        $entityManager = $this->getEntityManager();
        $dql = /** @lang DQL */
			"SELECT COUNT(e.label)
            FROM App\Entity\Emplacement e
            WHERE e.label = :label";

		if ($emplacementId) {
			$dql .= " AND e.id != :id";
		}

        $query = $entityManager
			->createQuery($dql)
			->setParameter('label', $label);

		if ($emplacementId) {
			$query->setParameter('id', $emplacementId);
		}

        return $query->getSingleScalarResult();
    }

    /**
     * @param string $label
     * @return Emplacement|null
     * @throws NonUniqueResultException
     */
    public function findOneByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT e
            FROM App\Entity\Emplacement e
            WHERE e.label = :label
            "
        )->setParameter('label', $label);;
        return $query->getOneOrNullResult();
    }

    public function getIdAndLabelActiveBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.id, e.label as text
          FROM App\Entity\Emplacement e
          WHERE e.label LIKE :search
          AND e.isActive = 1
          ORDER BY e.label ASC
          "
        )->setParameter('search', '%' . str_replace('_', '\_', $search) . '%');

        return $query->execute();
    }

    /**
     * @param null $params
     * @param false $excludeInactive
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findByParamsAndExcludeInactive($params = null, $excludeInactive = false)
    {
        $countTotal = $this->countAll();

        $em = $this->getEntityManager();
        $qb = $em
            ->createQueryBuilder()
            ->from('App\Entity\Emplacement', 'e');

        if ($excludeInactive) {
            $qb->where('e.isActive = 1');
        }

        $allEmplacementDataTable = null;
        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('e.label LIKE :value OR e.description LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                $field =  self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['name']];
                if (!empty($order) && $field) {
                    $qb->orderBy("e.${field}", $order);
                }
            }
            $qb->select('count(e)');
            $countQuery = (int) $qb->getQuery()->getSingleScalarResult();
        }
        else {
            $countQuery = $countTotal;
        }

        $qb
            ->select('e');
        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'allEmplacementDataTable' => !empty($params) ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }


    //VERIFCECILE
    /**
     * @return Emplacement[]
     */
    public function findWhereArticleIs()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "
            SELECT e.id, e.label, (
                SELECT COUNT(m)
                FROM App\Entity\TrackingMovement AS m
                JOIN m.emplacement e_other
                JOIN m.type t
                WHERE e_other.label = e.label AND t.nom LIKE 'depose'
            ) AS nb
            FROM App\Entity\Emplacement AS e
            WHERE e.dateMaxTime IS NOT NULL AND e.dateMaxTime != ''
            ORDER BY nb DESC"
        );
        return $query->execute();
    }

    public function findByIds(array $ids): array {
	    return $this->createQueryBuilder('location')
            ->where('location.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getResult();
	}
}
