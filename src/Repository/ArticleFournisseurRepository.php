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


    public function findBySearch($value)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.id, a.label as text
          FROM App\Entity\ArticleFournisseur a
          WHERE a.label LIKE :search"
        )->setParameter('search', '%' . $value . '%');

        return $query->execute();
    }

    public function getByFournisseur($fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.fournisseur = :fournisseurId"
        )->setParameters(['fournisseurId' => $fournisseurId]);

        return $query->getResult();
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

    public function findByRefArticleAndFournisseur($refArticleId, $fournisseurId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT af
            FROM App\Entity\ArticleFournisseur af
            WHERE af.referenceArticle = :refArticleId AND af.fournisseur = :fournisseurId"
        )->setParameters(['refArticleId' => $refArticleId, 'fournisseurId' => $fournisseurId]);

        return $query->getResult();
    }

    public function findByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('af')
            ->from('App\Entity\ArticleFournisseur', 'af');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('af.fournisseur', 'f')
                        ->leftJoin('af.referenceArticle', 'ra')
                        ->andWhere('f.nom LIKE :value OR af.reference LIKE :value OR ra.libelle LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(af)
            FROM App\Entity\ArticleFournisseur af
           "
        );

        return $query->getSingleScalarResult();
    }

}
