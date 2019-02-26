<?php

namespace App\Repository;

use App\Entity\Demande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Demande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demande[]    findAll()
 * @method Demande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Demande::class);
    }

//    public function findDmdByStatut($Statut)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT d
//            FROM App\Entity\Demande d
//            WHERE d.Statut = :Statut "
//        )->setParameter('Statut', $Statut);
//
//        return $query->execute();
//    }

    public function findByUserAndNotStatus($user, $status)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\Demande d
            JOIN d.Statut s
            WHERE s.nom <> :status AND d.utilisateur = :user"
        )->setParameters(['user' => $user, 'status' => $status]);

        return $query->execute(); 
    }

//    public function findByPrepa($preparation)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT d
//            FROM App\Entity\Demande d
//            WHERE d.preparation = :prepa "
//        )->setParameter('prepa', $preparation);
//        ;
//        return $query->execute();
//    }
    
//    public function findEmplacementByStatut($Statut)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT DISTINCT t.id, t.nom
//            FROM App\Entity\Demande d
//            JOIN d.destination t
//            WHERE d.Statut = :Statut "
//        )->setParameter('Statut', $Statut);
//        ;
//        return $query->execute();
//    }

//    public function findByDestiAndStatut($destination, $Statut)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT d
//            FROM App\Entity\Demande d
//            WHERE d.destination = :destination AND d.Statut = :Statut"
//        )->setParameter('destination', $destination)
//         ->setParameter('Statut', $Statut);
//        ;
//        return $query->execute();
//    }

//    public function findByLivrais($livraison)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT d
//            FROM App\Entity\Demande d
//            WHERE d.livraison = :livraison"
//        )->setParameter('livraison', $livraison);
//        ;
//        return $query->execute();
//    }

//    public function findCountByStatutAndPrepa($statut, $preparation)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT COUNT (d)
//            FROM App\Entity\Demande d
//            JOIN d.preparation p
//            WHERE d.Statut <> :statut AND p = :preparation"
//        )->setParameter('preparation', $preparation)
//        ->setParameter('statut', $statut);
//        ;
//        return $query->execute();
//    }
    
    // /**
    //  * @return Demande[] Returns an array of Demande objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Demande
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
