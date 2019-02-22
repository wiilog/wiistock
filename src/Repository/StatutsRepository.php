<?php

namespace App\Repository;

use App\Entity\Statuts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Statuts|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statuts|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statuts[]    findAll()
 * @method Statuts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Statuts::class);
    }

    public function findById($id)
    {
        $em = $this->getEntityManager();
        return $query = $em->createQuery("SELECT s FROM App\Entity\Statuts s WHERE s.id = :id")
            ->setParameter("id", $id)
            ->execute();
    }

    public function findByCategorieName($categorieName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
            FROM App\Entity\Statuts s
            JOIN s.categorie c
            WHERE c.nom = :categorieName");

        $query->setParameter("categorieName", $categorieName);

        return $query->execute();
    }

    public function findOneByCategorieAndStatut($categorieName, $statutName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
          "SELECT s
          FROM App\Entity\Statuts s
          JOIN s.categorie c
          WHERE c.nom = :categorieName AND s.nom = :statutName
          "
        );

        $query->setParameters([
            'categorieName' => $categorieName,
            'statutName' => $statutName
        ]);

        $result = $query->execute();
        return ($result) ? $result[0] : null;
    }

    // /**
    //  * @return Statuts[] Returns an array of Statuts objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Statuts
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
