<?php

namespace App\Repository\Dashboard;

use App\Entity\Action;
use App\Entity\Dashboard;
use App\Entity\Menu;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;

/**
 * @method Dashboard\Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dashboard\Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dashboard\Page[]    findAll()
 * @method Dashboard\Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends EntityRepository {

    public function findAllowedToAccess(?Utilisateur $user) {
        $qb = $this->createQueryBuilder("p");

        if($user) {
            $qb->where("p.action IN (:actions)")
                ->setParameter("actions", $user->getRole()->getActions());
        }

        return $qb->getQuery()->getResult();
    }

}
