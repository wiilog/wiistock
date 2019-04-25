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

   public function getByLivraison($id)
   {
       $entityManager = $this->getEntityManager();
       $query = $entityManager->createQuery(
           "SELECT d
           FROM App\Entity\Demande d
            JOIN d.livraison l
           WHERE l.id = :id "
       )->setParameter('id', $id);

       return $query->getOneOrNullResult();
   }

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

    public function getByStatutAndUser($statut, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\Demande d
            WHERE d.statut = :Statut AND d.utilisateur = :user"
        )->setParameters([
            'Statut'=> $statut,
            'user'=> $user
            ]);
        return $query->execute();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            JOIN d.destination dest
            WHERE dest.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }
    public function findOneByPreparation($preparation)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Demande a
            WHERE a.preparation = :preparation'
        )->setParameter('preparation', $preparation);
        return $query->getOneOrNullResult();
    }
    public function findOneByPreparationAndLivraison($livraison)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Demande a
            WHERE a.livraison = :livraison'
        )->setParameter('livraison', $livraison);
        return $query->getOneOrNullResult();
    }
    
}
