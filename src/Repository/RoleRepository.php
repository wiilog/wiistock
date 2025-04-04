<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\ORM\EntityRepository;

class RoleRepository extends EntityRepository
{

    public function countByLabel($label)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            "SELECT count(r)
            FROM App\Entity\Role r
            WHERE r.label = :label"
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

    public function findByLabel($label)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            "SELECT r
            FROM App\Entity\Role r
            WHERE r.label = :label"
        )->setParameter('label', $label);

        return $query->getOneOrNullResult();
    }

    public function findAllExceptNoAccess()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            'SELECT r
            FROM App\Entity\Role r
            WHERE r.label <> :no_access
            '
        )->setParameter('no_access', Role::NO_ACCESS_USER);

        return $query->execute();
    }

    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder('role')
            ->select("role.id AS id")
            ->addSelect("role.label AS text")
            ->andWhere('role.label LIKE :term')
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }

    public function findByActionParams($menuLabel, $actionLabel, $subMenu = null) {
        $queryBuilder = $this->createQueryBuilder('role')
            ->innerJoin('role.actions', 'action')
            ->join("action.menu", "menu")
            ->where("action.label = :action")
            ->andWhere("menu.label = :menu")
            ->setParameter("action", $actionLabel)
            ->setParameter("menu", $menuLabel);

        if($subMenu) {
            $queryBuilder->andWhere("action.subMenu = :sub_menu")
                ->setParameter("sub_menu", $subMenu);
        }
        return $queryBuilder->getQuery()
            ->getResult();
    }
}
