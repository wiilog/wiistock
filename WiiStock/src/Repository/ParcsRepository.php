<?php

namespace App\Repository;

use App\Entity\Parcs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method Parcs|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parcs|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parcs[]    findAll()
 * @method Parcs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParcsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Parcs::class);
    }

    /**
     * @return Parcs[] Returns an array of Parcs objects
     */
    public function findByStateSiteImmatriculation($state, $site, $immat, $nserie)
    {
        return $this->createQueryBuilder('parc')
            ->andWhere('parc.state = :valState')
            ->setParameter('valState', $state)
            ->andWhere('parc.site =: valSite')
            ->setParameter('valSite', $site)
            ->andWhere('parc.vehicules.immat =: valImmat')
            ->setParameter('valImmat', $immat)
            ->orWhere('parc.chariots.nserie =: valNserie')
            ->setParameter('valNserie', $nserie)
            ->orderBy('parc.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLast()
    {
        $qb = $this->createQueryBuilder('parc');
        $qb->setMaxResults(1);
        $qb->orderBy('parc.id', 'DESC');

        return $qb->getQuery()->getOneOrNullResult();
    }
    
//    /**
//     * @return Parcs[] Returns an array of Parcs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
     */

    /*
    public function findOneBySomeField($value): ?Parcs
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
     */
}
