<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use App\Service\UserService;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.reception = :id'
        )->setParameter('id', $id);;
        return $query->execute();
    }

    public function getByCollecte($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             JOIN a.collectes c
             WHERE c.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

    public function getByDemande($demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             WHERE a.demande =:demande
            "
        )->setParameter('demande', $demande);
        return $query->execute();
    }


    public function findByEmplacement($empl)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.emplacement = :empl'
        )->setParameter('empl', $empl);;
        return $query->execute();
    }

    public function findByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            WHERE a.Statut = :Statut "
        )->setParameter('Statut', $statut);;
        return $query->execute();
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            WHERE a.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function setTypeIdNull($typeId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            /** @lang DQL */
            "UPDATE App\Entity\Article a
            SET a.type = null 
            WHERE a.type = :typeId"
        )->setParameter('typeId', $typeId);

        return $query->execute();
    }

    public function getArticleByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id = :id "
        )->setParameter('id', $id);;
        return $query->execute();
    }

    public function getArticleByRefId()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.id, a.reference, a.quantite
            FROM App\Entity\Article a
            "
        );
        return $query->execute();
    }

    public function getRefByRecep($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.reference
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }
    public function getByPreparation($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            JOIN a.preparations p
            WHERE p.id =:id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

    public function getByAFAndInactif($articleFournisseur, $statut)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.id, a.reference
          FROM App\Entity\Article a
          JOIN a.articleFournisseur af
          WHERE a.Statut = :statut AND af.id IN(:articleFournisseur)"
        )->setParameters([
            'articleFournisseur' => $articleFournisseur,
            'statut' => $statut
        ]);

        return $query->execute();
    }

    public function getByAFAndActifAndDemandeNull($articleFournisseur, $statut)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.id, a.reference
          FROM App\Entity\Article a
          WHERE a.Statut = :statut AND a.articleFournisseur IN(:articleFournisseur) AND (a.demande IS NULL )"
        )->setParameters([
            'articleFournisseur' => $articleFournisseur,
            'statut' => $statut,
        ]);

        return $query->execute();
    }

    public function getByAFAndActifAndDemandeStatus($articleFournisseur, $statut, $demandeStatus)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.id, a.reference
          FROM App\Entity\Article a
          JOIN a.demande d
          WHERE a.Statut = :statut AND a.articleFournisseur IN(:articleFournisseur) AND  d.statut = :demandeStatut"
        )->setParameters([
            'articleFournisseur' => $articleFournisseur,
            'statut' => $statut,
            'demandeStatut' => $demandeStatus
        ]);

        return $query->execute();
    }



    public function findByEtat($etat)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.etat = :etat'
        )->setParameter('etat', $etat);;
        return $query->execute();
    }

    public function countByRefArticleAndStatut($refArticle, $statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.Statut s
            WHERE a.refArticle = :refArticle AND a.etat = TRUE AND s.nom = :statut"
        )->setParameters(['refArticle' => $refArticle, 'statut' => $statut]);

        return $query->getSingleScalarResult();
    }

    public function findAllSortedByName()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.nom, a.id, a.quantite
          FROM App\Entity\Article a
          ORDER BY a.nom
          "
        );
        return $query->execute();
    }

    public function findByParams($params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a');

        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) {
                $qb->setFirstResult($params->get('start'));
            }
            if (!empty($params->get('length'))) {
                $qb->setMaxResults($params->get('length'));
            }
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $query = $qb->getQuery();

        return $query->getResult();
    }

    public function findByParamsActifStatut($params = null, $statutId)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a')
            //    ->join('a.Statut', 'App\Entity\Statut','s')
            ->where('a.Statut =' . $statutId);
        //    dump($qb);
        // $qb = $em->createQuery(
        //     "SELECT a
        //     FROM App\Entity\Article a
        //     JOIN a.Statut s
        //     WHERE s.nom = :actif");



        // prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('start'))) {
                $qb->setFirstResult($params->get('start'));
            }
            if (!empty($params->get('length'))) {
                $qb->setMaxResults($params->get('length'));
            }
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
        }

        $query = $qb->getQuery();


        return $query->getResult();
    }

    public function getByAF($af)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a
          FROM App\Entity\Article a
          JOIN a.articleFournisseur af
          WHERE af.id IN(:articleFournisseur)"
        )->setParameters([
            'articleFournisseur' => $af,
        ]);

        return $query->execute();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
           "
        );

        return $query->getSingleScalarResult();
    }
}
