<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Utilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateur[]    findAll()
 * @method Utilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function countByEmail($email)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            WHERE u.email = :email"
        )->setParameter('email', $email);

        return $query->getSingleScalarResult();
    }

    public function countApiKey($apiKey)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            WHERE u.apiKey = :apiKey"
        )->setParameter('apiKey', $apiKey);

        return $query->getSingleScalarResult();
    }

    public function getIdAndUsername()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u.id, u.username
            FROM App\Entity\Utilisateur u
            ORDER BY u.username
            "
        );

        return $query->execute();
    }

    public function getNoOne($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.id <> :id"
        )->setParameter('id', $id);

        return $query->execute();
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
          "SELECT u.id, u.username as text
          FROM App\Entity\Utilisateur u
          WHERE u.username LIKE :search"
        )->setParameter('search', '%'.$search.'%');

        return $query->execute();
    }

    public function countByRoleId($roleId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            JOIN u.role r
            WHERE r.id = :roleId"
        )->setParameter('roleId', $roleId);
        ;
        return $query->getSingleScalarResult();
    }

    public function getByMail($mail)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.email = :email"
        )->setParameter('email', $mail);

        return $query->getOneOrNullResult();
    }
}
