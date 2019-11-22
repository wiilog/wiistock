<?php

namespace App\Repository;

use App\Entity\Emplacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Emplacement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emplacement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emplacement[]    findAll()
 * @method Emplacement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmplacementRepository extends ServiceEntityRepository
{

    private const DtToDbLabels = [
        'Nom' => 'label',
        'Description' => 'description',
        'Point de livraison' => 'isDeliveryPoint',
        'Actif / Inactif' => 'isActive',
    ];

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Emplacement::class);
    }

    public function getIdAndNom()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT e.id, e.label
            FROM App\Entity\Emplacement e
            "
        );;
        return $query->execute();
    }

    public function countByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(e.label)
            FROM App\Entity\Emplacement e
            WHERE e.label = :label"
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    /**
     * @param string $label
     * @return Emplacement|null
     * @throws \Doctrine\ORM\NonUniqueResultException
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

    public function getNoOne($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT e.id, e.label
            FROM App\Entity\Emplacement e
            WHERE e.id <> :id
            "
        )->setParameter('id', $id);;
        return $query->execute();
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
          "
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function findByParamsAndExcludeInactive($params = null, $excludeInactive = false)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('e')
            ->from('App\Entity\Emplacement', 'e');

        if ($excludeInactive) {
            $qb->where('e.isActive = 1');
        }

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

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
                $countQuery = count($qb->getQuery()->getResult());
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $qb->orderBy('e.' . self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['name']], $order);
                }
            }
            $allEmplacementDataTable = $qb->getQuery();
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'allEmplacementDataTable' => $allEmplacementDataTable ? $allEmplacementDataTable->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(e)
            FROM App\Entity\Emplacement e
           "
        );

        return $query->getSingleScalarResult();
    }

    public function findOneByRefArticleWithChampLibreAdresse($refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "
            SELECT e
            FROM App\Entity\Emplacement e
            WHERE e.label IN 
            (SELECT v.valeur
            FROM App\Entity\ValeurChampLibre v
            JOIN v.champLibre c
            JOIN v.articleReference a
            WHERE c.label LIKE 'adresse%' AND v.valeur is not null AND a =:refArticle)"
        )->setParameter('refArticle', $refArticle);

        return $query->getResult() ? $query->getResult()[0] : null;
    }

    // TODO VERIFCECILE
    /**
     * @return Emplacement[]
     */
    public function findWhereArticleIs()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "
            SELECT e
            FROM App\Entity\Emplacement AS e
            WHERE
            (
                SELECT COUNT(m)
                FROM App\Entity\MouvementTraca AS m
                WHERE m.refEmplacement = e.label AND m.type LIKE 'depose'
            ) > 0 AND e.dateMaxTime IS NOT NULL"
        );
        return $query->execute();
    }
}
