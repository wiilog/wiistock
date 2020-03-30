<?php

namespace App\Repository;

use App\Entity\Action;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method Action|null find($id, $lockMode = null, $lockVersion = null)
 * @method Action|null findOneBy(array $criteria, array $orderBy = null)
 * @method Action[]    findAll()
 * @method Action[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionRepository extends EntityRepository {

    /**
     * @param string $menuLabel
     * @param string $actionLabel
     * @return Action
     * @throws NonUniqueResultException
     */
    public function findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            "SELECT a
            FROM App\Entity\Action a
            JOIN a.menu m
            WHERE a.label = :actionLabel AND m.label = :menuLabel"
        )->setParameters(['actionLabel' => $actionLabel, 'menuLabel' => $menuLabel]);

        return $query->getOneOrNullResult();
    }
}
