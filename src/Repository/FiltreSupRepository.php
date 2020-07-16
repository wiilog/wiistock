<?php

namespace App\Repository;

use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method FiltreSup|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiltreSup|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiltreSup[]    findAll()
 * @method FiltreSup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiltreSupRepository extends EntityRepository
{

    /**
     * @param string $field
     * @param string $page
     * @param Utilisateur $user
     * @return FiltreSup|null
     * @throws NonUniqueResultException
     */
    public function findOnebyFieldAndPageAndUser($field, $page, $user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT fs
			FROM App\Entity\FiltreSup fs
			WHERE fs.field = :field
			AND fs.page = :page
			AND fs.user = :user"
        )->setParameters([
            'field' => $field,
            'page' => $page,
            'user' => $user
        ]);

        return $query->getOneOrNullResult();
    }

    /**
     * @param string $field
     * @param string $page
     * @param Utilisateur $user
     * @return string
     * @throws NonUniqueResultException
     */
    public function getOnebyFieldAndPageAndUser($field, $page, $user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT fs.value
			FROM App\Entity\FiltreSup fs
			WHERE fs.field = :field
			AND fs.page = :page
			AND fs.user = :user"
        )->setParameters([
            'field' => $field,
            'page' => $page,
            'user' => $user
        ]);

        $result = $query->getOneOrNullResult();
        return $result ? $result['value'] : null;
    }

    /**
     * @param string $page
     * @param Utilisateur $user
     * @return array
     */
    public function getFieldAndValueByPageAndUser($page, $user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT fs.field, fs.value
			FROM App\Entity\FiltreSup fs
			WHERE fs.page = :page
			AND fs.user = :user"
        )->setParameters([
            'page' => $page,
            'user' => $user
        ]);

        return $query->execute();
    }

    /**
     * @param Utilisateur $utilisateur
     * @param string $page
     */
    public function clearFiltersByUserAndPage(Utilisateur $utilisateur, string $page): void
    {
        $queryBuilder = $this->createQueryBuilder('filter');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->delete(FiltreSup::class, 'filter')
            ->where(
                $queryBuilderExpr->andX(
                    $queryBuilderExpr->eq('filter.user', ':user'),
                    $queryBuilderExpr->eq('filter.page', ':page')
                )
            )
            ->setParameters([
                'user' => $utilisateur,
                'page' => $page
            ])
            ->getQuery()
            ->execute();
    }
}
