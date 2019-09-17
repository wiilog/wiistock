<?php

namespace App\Repository;

use App\Entity\PrefixeNomDemande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PrefixeNomDemande|null find($id, $lockMode = null, $lockVersion = null)
 * @method PrefixeNomDemande|null findOneBy(array $criteria, array $orderBy = null)
 * @method PrefixeNomDemande[]    findAll()
 * @method PrefixeNomDemande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrefixeNomDemandeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PrefixeNomDemande::class);
    }

	/**
	 * @param string $typeDemande
	 * @return PrefixeNomDemande|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
    public function findOneByTypeDemande($typeDemande){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
           FROM App\Entity\PrefixeNomDemande p
           WHERE p.typeDemandeAssociee =:typeDemande"
        )->setParameter('typeDemande' , $typeDemande);
        return $query->getOneOrNullResult();
    }
}
