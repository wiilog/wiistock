<?php

namespace App\Repository;

use App\Entity\SubMenu;
use Doctrine\ORM\EntityRepository;

/**
 * @method SubMenu|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubMenu|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubMenu[]    findAll()
 * @method SubMenu[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubMenuRepository extends EntityRepository {

    public function findOneByLabel(string $menu, string $label): ?SubMenu {
        return $this->createQueryBuilder("submenu")
            ->join("submenu.menu", "menu")
            ->andWhere("menu.label LIKE :menu")
            ->andWhere("submenu.label LIKE :label")
            ->setParameter("menu", $menu)
            ->setParameter("label", $label)
            ->getQuery()
            ->getOneOrNullResult();
    }

}

