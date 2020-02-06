<?php

namespace App\Repository;

use App\Entity\FieldsParam;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FieldsParam|null find($id, $lockMode = null, $lockVersion = null)
 * @method FieldsParam|null findOneBy(array $criteria, array $orderBy = null)
 * @method FieldsParam[]    findAll()
 * @method FieldsParam[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FieldsParamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FieldsParam::class);
    }

    /**
     * @param $entity
     * @return array [fieldCode => ['mustToCreate' => boolean, 'mustToModify' => boolean, 'displayed' => boolean]]
     */
    function getByEntity($entity): array {
        $em = $this->getEntityManager();
        $query = $em
            ->createQuery(
                "SELECT f.fieldCode, f.fieldLabel, f.mustToCreate, f.mustToModify, f.displayed
                FROM App\Entity\FieldsParam f
                WHERE f.entityCode = :entity"
            )
            ->setParameter('entity', $entity);
        $result = $query->execute();
        return array_reduce(
            $result,
            function (array $acc, $field) {
                $acc[$field['fieldCode']] = [
                    'mustToCreate' => $field['mustToCreate'],
                    'mustToModify' => $field['mustToModify'],
                    'displayed' => $field['displayed'],
                ];
                return $acc;
            },
            []);
    }

    /**
     * @param string $entity
     * @return array
     */
    function getHiddenByEntity($entity): array {
        $em = $this->getEntityManager();
        $query = $em
            ->createQuery(
                "SELECT f.fieldCode
                FROM App\Entity\FieldsParam f
                WHERE f.entityCode = :entity AND f.displayed = 0"
            )
            ->setParameter('entity', $entity);

		return array_column($query->execute(), 'fieldCode');
	}

	/**
	 * @param $entity
	 * @return FieldsParam[]
	 */
    function findByEntityForEntity($entity) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT f
            FROM App\Entity\FieldsParam f
            WHERE f.entityCode = :entity"
        )->setParameter('entity', $entity);

        return $query->execute();
    }


}
