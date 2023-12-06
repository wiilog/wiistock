<?php

namespace App\Repository;

use App\Entity\Printer;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Printer>
 *
 * @method Printer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Printer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Printer[]    findAll()
 * @method Printer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrinterRepository extends EntityRepository
{
    public function getForSelect(?string $term, ?int $default, ?Utilisateur $user): array {
        $query = $this->createQueryBuilder("printer")
            ->select("printer.id AS id, printer.name AS text")
            ->andWhere("printer.name LIKE :term")
            ->setParameter("term", "%$term%");

        if ($default) {
            $query
                ->andWhere("printer.id != :default")
                ->setParameter("default", $default);
        }

        if($user){
            $query
                ->andWhere(":user MEMBER OF printer.allowedUsers")
                ->orWhere(":user MEMBER OF printer.defaultUsers")
                ->setParameter("user", $user->getId());
        }

        return $query
            ->getQuery()
            ->getArrayResult();
    }
}
