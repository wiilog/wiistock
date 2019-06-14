<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Fournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fournisseur[]    findAll()
 * @method Fournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Fournisseur::class);
    }

    public function findBySearch($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.nom like :value OR r.code_reference like :value')
            ->setParameter('value', '%' . $value . '%')
            ->orderBy('r.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function getNoOne($fournisseur)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT f
            FROM App\Entity\Fournisseur f
            WHERE f.id <> :fournisseur"
        )->setParameter('fournisseur', $fournisseur);;
        return $query->execute();
    }

    public function findOneByCodeReference($code)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
          FROM App\Entity\Fournisseur f
          WHERE f.codeReference LIKE :search"
        )->setParameter('search', $code);

        return $query->getOneOrNullResult();
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f.id, f.nom as text
          FROM App\Entity\Fournisseur f
          WHERE f.nom LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function findByRefArticle($refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
          FROM App\Entity\Fournisseur f
          WHERE f.refenceArticle = :refArticle
          "
        )->setParameter('refArticle', $refArticle);

        return $query->execute();
    }

    public function findByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Fournisseur', 'a');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('a.nom LIKE :value OR a.codeReference LIKE :value')
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
            "SELECT COUNT(a)
            FROM App\Entity\Fournisseur a
           "
        );

        return $query->getSingleScalarResult();
    }

    public function findAllSorted()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f FROM App\Entity\Fournisseur f
            ORDER BY f.nom
            "
        );

        return $query->execute();
    }

}
