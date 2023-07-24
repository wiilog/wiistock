<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Customer>
 *
 * @method Customer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Customer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Customer[]    findAll()
 * @method Customer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CustomerRepository extends EntityRepository
{

    public function save(Customer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Customer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllSorted()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT c FROM App\Entity\Customer c
            ORDER BY c.name
            "
        );

        return $query->execute();
    }

    public function iterateAll(): iterable {
        return $this->createQueryBuilder("customer")
            ->getQuery()
            ->toIterable();
    }

    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder("customer")
            ->select("customer.name AS id")
            ->addSelect("customer.name AS text")
            ->addSelect("customer.address AS address")
            ->addSelect("customer.recipient AS recipient")
            ->addSelect("customer.email AS email")
            ->addSelect("customer.phoneNumber AS phoneNumber")
            ->addSelect("customer.fax AS fax")
            ->andWhere("customer.name LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }
}
