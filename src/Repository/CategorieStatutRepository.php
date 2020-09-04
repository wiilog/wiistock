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
     * @param string[] $labels
     * @return CategorieStatut[]
     */
    public function findByLabelLike(array $labels)
    {
        $queryBuilder = $this->createQueryBuilder('categorie_statut')
            ->select('categorie_statut.id')
            ->addSelect('categorie_statut.nom');

        foreach($labels as $index => $label) {
            $parameterKey = "label_$index";
            $queryBuilder
                ->orWhere("categorie_statut.nom LIKE :$parameterKey")
                ->setParameter($parameterKey, "%$label%");
        }

        return !empty($labels)
            ? $queryBuilder->getQuery()->getResult()
            : [];
    }
}
