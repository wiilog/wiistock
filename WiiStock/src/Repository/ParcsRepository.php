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
    public function findByStateSiteImmatriculation($state, $site, $immat)
    {
        $qb = $this->createQueryBuilder('parc');

        if ($state) {
            $qb->andWhere('parc.statut = :valState')
            ->setParameter('valState', $state);
        }

        if ($site) {
            $qb->leftJoin('parc.site', 'site')
            ->andwhere('site.nom = :valSite')
            ->setParameter('valSite', $site);
        }

        if ($immat != "") {
            $qb->leftJoin('parc.vehicules', 'vehicule')
            ->leftJoin('parc.chariots', 'chariot')
            ->andWhere('vehicule.immatriculation = :valImmat OR chariot.n_serie = :valImmat')
            ->setParameter('valImmat', $immat);
        }
        
        return $qb->orderBy('parc.id', 'ASC')
                ->getQuery()
                ->getResult();
    }

    public function findLast()
    {
        $qb = $this->createQueryBuilder('parc');
        $qb->andWhere('parc.statut = :value1 OR parc.statut = :value2 OR parc.statut = :value3');
        $qb->setParameters(['value1' => 'Actif', 'value2' => 'Demande sortie/transfert', 'value3' => 'Sorti']);
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
