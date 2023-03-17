<?php

namespace App\Repository;

use App\Entity\Chauffeur;
use Doctrine\ORM\EntityRepository;

/**
 * @method Chauffeur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chauffeur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chauffeur[]    findAll()
 * @method Chauffeur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChauffeurRepository extends EntityRepository
{
    /**
     * @return Chauffeur[]
     */
    public function findAllSorted()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT c FROM App\Entity\Chauffeur c
            ORDER BY c.nom
            "
        );

        return $query->execute();
    }

    public function countByTransporteur($transporteur)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(c)
            FROM App\Entity\Chauffeur c
            WHERE c.transporteur = :transporteur
            ")
        ->setParameter('transporteur',$transporteur);

        return $query->getSingleScalarResult();

    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT c.id, c.nom as text
          FROM App\Entity\Chauffeur c
          WHERE c.nom LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    function getDriversArray(){
        return $this->createQueryBuilder('chauffeur')
            ->select('chauffeur.id AS id')
            ->addSelect('chauffeur.nom AS label')
            ->addSelect('chauffeur.prenom AS prenom')
            ->addSelect('join_transporteur.id AS id_transporteur')
            ->leftJoin('chauffeur.transporteur', 'join_transporteur')
            ->getQuery()
            ->getResult();
    }

    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder('driver')
            ->select("driver.id AS id")
            ->addSelect("CONCAT(driver.prenom, ' ', driver.nom) AS text")
            ->andWhere('driver.nom LIKE :term OR driver.prenom LIKE :term')
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }
}
