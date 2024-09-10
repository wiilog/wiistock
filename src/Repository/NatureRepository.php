<?php

namespace App\Repository;

use App\Entity\Language;
use App\Entity\Nature;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Nature|null find($id, $lockMode = null, $lockVersion = null)
 * @method Nature|null findOneBy(array $criteria, array $orderBy = null)
 * @method Nature[]    findAll()
 * @method Nature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NatureRepository extends EntityRepository
{
    public function findByParams(InputBag $params, $options = []): array {
        $qb = $this->createQueryBuilder('nature');
        $total = QueryBuilderHelper::count($qb, 'nature');

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                "nature.label LIKE :value",
                                "nature.code LIKE :value",
                                "nature.description LIKE :value"
                            )
                            . ')')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if($column === "label") {
                        $qb
                            ->leftJoin("nature.labelTranslation", "join_labelTranslation")
                            ->leftJoin("join_labelTranslation.translations", "join_translation", Join::WITH, "join_translation.language = :language")
                            ->leftJoin("join_labelTranslation.translations", "join_translation_default", Join::WITH, "join_translation_default.language = :default")
                            ->setParameter("language", $options["language"])
                            ->setParameter("default", $options["defaultLanguage"])
                            ->orderBy("IFNULL(join_translation.translation, IFNULL(join_translation_default.translation, nature.label))", $order);
                    } else if(property_exists(Nature::class, $column)) {
                        $qb->orderBy('nature.' . $column, $order);
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, 'nature');

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total,
        ];
    }

    public function getAllowedNaturesIdByLocation() {
        return $this->createQueryBuilder('nature')
            ->select('nature.id AS nature_id')
            ->addSelect('location.id AS location_id')
            ->join('nature.emplacements', 'location')
            ->getQuery()
            ->getResult();
    }

    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder('nature')
            ->select("nature.id AS id")
            ->addSelect("nature.label AS text")
            ->andWhere('nature.label LIKE :term')
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }

    public function countUsedById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
            LEFT JOIN n.packs pack
            WHERE pack.nature = :id
           "
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

    /**
     * @param string[] $forms
     * @return Nature[]
     */
    public function findByAllowedForms(array $forms): array {
        $qb = $this->createQueryBuilder('nature');

        foreach ($forms as $form) {
            $qb->orWhere("JSON_EXTRACT(nature.allowedForms, '$.\"$form\"') IS NOT NULL");
        }
        $qb->orderBy('nature.label');
        return $qb->getQuery()->getResult();
    }

    public function findDuplicateLabels(string $label, string $languageSlug, array $except = []) {
        $queryBuilder = $this->createQueryBuilder('nature');
        $exprBuilder = $queryBuilder->expr();

        if (!empty($except)) {
            $queryBuilder
                ->andWhere("nature NOT IN (:except)")
                ->setParameter("except", $except);
        }

        $duplicateCondition = $exprBuilder->orX(
            $exprBuilder->andX(
                "join_language.slug = :languageSlug",
                "join_translations.translation = :label"
            )
        );

        if (Language::FRENCH_SLUG) {
            $duplicateCondition->add("nature.label LIKE :label");
        }

        return $queryBuilder
            ->join("nature.labelTranslation", "join_source")
            ->leftJoin("join_source.translations", "join_translations")
            ->leftJoin("join_translations.language", "join_language")
            ->andWhere($duplicateCondition)
            ->setParameter("languageSlug", $languageSlug)
            ->setParameter("label", $label)
            ->getQuery()
            ->getResult();
    }
}
