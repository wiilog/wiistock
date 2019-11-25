<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Urgence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Urgence::class);
    }

    /**
     * @param Arrivage $arrivage the arrivage to analyse
     * @return int the number of emergencies
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByArrivageData(Arrivage $arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(u) 
                FROM App\Entity\Urgence u
                WHERE u.dateStart <= :date AND u.dateEnd >= :date AND u.commande LIKE :commande"
        )->setParameters([
            'date' => $arrivage->getDate(),
            'commande' => $arrivage->getNumeroBL()
        ]);

        return $query->getSingleScalarResult();
    }
}
