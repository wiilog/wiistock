<?php

namespace App\Repository;

use App\Entity\Action;
use Doctrine\ORM\EntityRepository;

/**
 * @method Action|null find($id, $lockMode = null, $lockVersion = null)
 * @method Action|null findOneBy(array $criteria, array $orderBy = null)
 * @method Action[]    findAll()
 * @method Action[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionRepository extends EntityRepository {

    public function findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel): ?Action {
        return $this->createQueryBuilder("action")
            ->join("action.menu", "menu")
            ->where("action.label = :action")
            ->andWhere("menu.label = :menu")
            ->setParameter("action", $actionLabel)
            ->setParameter("menu", $menuLabel)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByParams($menuLabel, $actionLabel, $subMenu = null): ?Action {
        $qb = $this->createQueryBuilder("action")
            ->join("action.menu", "menu")
            ->where("action.label = :action")
            ->andWhere("menu.label = :menu")
            ->setParameter("action", $actionLabel)
            ->setParameter("menu", $menuLabel);

        if($subMenu) {
            $qb->andWhere("action.subMenu = :sub_menu")
                ->setParameter("sub_menu", $subMenu);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

}
