<?php

namespace App\Repository;

use App\Entity\ArticleFournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ArticleFournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleFournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleFournisseur[]    findAll()
 * @method ArticleFournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleFournisseurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ArticleFournisseur::class);
    }

    public function countByReference($reference)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT (ra)
            FROM App\Entity\ReferenceArticle ra
            WHERE ra.reference = :reference"
        )->setParameter('reference', $reference);

        return $query->getSingleScalarResult();
    }

    public function getByRefArticle($id)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT rf
            FROM App\Entity\ArticleFournisseur rf
            WHERE rf.referenceArticle = :id"
        )->setParameter('id', $id);

        return $query->getResult();
    }


    public function findOneByRefArticleAndFournisseur($refArticleId, $fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.referenceArticle = :refArticleId AND af.fournisseur = :fournisseurId"
        )->setParameters(['refArticleId' => $refArticleId, 'fournisseurId' => $fournisseurId]);

        return $query->getOneOrNullResult();
    }

}
