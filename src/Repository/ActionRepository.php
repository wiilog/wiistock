<?php

namespace App\Repository;

use App\Entity\Action;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Action|null find($id, $lockMode = null, $lockVersion = null)
 * @method Action|null findOneBy(array $criteria, array $orderBy = null)
 * @method Action[]    findAll()
 * @method Action[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Action::class);
    }

    /**
     * @param string $menuCode
     * @param string $label
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findOneByMenuCodeAndLabel($menuCode, $label)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            "SELECT a
            FROM App\Entity\Action a
            JOIN a.menu m
            WHERE a.label = :label AND m.code = :menuCode"
        )->setParameters(['label' => $label, 'menuCode' => $menuCode]);

        return $query->getOneOrNullResult();
    }
}
