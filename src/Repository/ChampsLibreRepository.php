<?php

namespace App\Repository;

use App\Entity\ChampsLibre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ChampsLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChampsLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChampsLibre[]    findAll()
 * @method ChampsLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChampsLibreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ChampsLibre::class);
    }

    public function getByType($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\ChampsLibre c 
            JOIN c.type t 
            WHERE t.id = :id"
        )->setParameter('id', $type);
        ;
        return $query->execute(); 
    }
    
    public function getByTypeAndRequiredCreate($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\ChampsLibre c 
            WHERE c.type = :type AND c.requiredCreate = TRUE"
        )->setParameter('type', $type);
        ;
        return $query->getResult(); 
    }

    public function getByTypeAndRequiredEdit($type)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id
            FROM App\Entity\ChampsLibre c 
            WHERE c.type = :type AND c.requiredEdit = TRUE"
        )->setParameter('type', $type);
        ;
        return $query->getResult(); 
    }

    public function getLabelAndIdAndTypage()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id, c.typage
            FROM App\Entity\ChampsLibre c 
            "
        );
        return $query->getResult(); 
    }

    public function getLabelByCategory($category)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c.label, c.id, c.typage
            FROM App\Entity\ChampsLibre c 
            JOIN c.type t
            JOIN t.category z
            WHERE z.label = :category
            "
        )->setParameter('category', $category);
        return $query->getResult(); 
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\ChampsLibre cl
            WHERE cl.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function deleteByType($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "DELETE FROM App\Entity\ChampsLibre cl
            WHERE cl.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function countByLabel($label)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(cl)
            FROM App\Entity\ChampsLibre cl
            WHERE cl.label = :label
           "
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

   
}
