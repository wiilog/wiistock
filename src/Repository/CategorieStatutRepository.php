<?php

namespace App\Repository;

use App\Entity\CategorieStatut;
use Doctrine\ORM\EntityRepository;

/**
 * @method CategorieStatut|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategorieStatut|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategorieStatut[]    findAll()
 * @method CategorieStatut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieStatutRepository extends EntityRepository
{

    /**
     * @param string|null $disputeStatusLabel
     * @param string|null $acheminementStatusLabel
     * @return CategorieStatut[]
     */
    public function findByLabelLike(?string $disputeStatusLabel, ?string $acheminementStatusLabel)
    {
            $queryBuilder = $this->createQueryBuilder('categorie_statut')
                ->select('categorie_statut.id')
                ->addSelect('categorie_statut.nom')
                ->where('categorie_statut.nom LIKE :disputeStatusLabel')
                ->orWhere('categorie_statut.nom LIKE :acheminementStatusLabel');

            if ($disputeStatusLabel) {
                $queryBuilder->setParameter('disputeStatusLabel', '%' . $disputeStatusLabel . '%');
            }

            if ($acheminementStatusLabel) {
                $queryBuilder->setParameter('acheminementStatusLabel', '%' . $acheminementStatusLabel . '%');
            }

            return $queryBuilder
                ->getQuery()
                ->getResult();
    }
}
