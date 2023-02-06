<?php

namespace App\Repository;

use App\Entity\NativeCountry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;

/**
 * @method NativeCountry|null find($id, $lockMode = null, $lockVersion = null)
 * @method NativeCountry|null findOneBy(array $criteria, array $orderBy = null)
 * @method NativeCountry[]    findAll()
 * @method NativeCountry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NativeCountryRepository extends EntityRepository {
    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder('native_country')
            ->select("native_country.id AS id")
            ->addSelect("native_country.code AS code")
            ->andWhere("native_country.active = 1")
            ->andWhere("native_country.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }
}
