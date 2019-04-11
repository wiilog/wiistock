<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @param string $label
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
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

}
