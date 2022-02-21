<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\ORM\EntityRepository;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
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

    public function getForSelect(string $term): array {
        return $this->createQueryBuilder('role')
            ->select("role.id AS id")
            ->addSelect("role.label AS text")
            ->andWhere('role.label LIKE :term')
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }
}
