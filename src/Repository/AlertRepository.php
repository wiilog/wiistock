<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\ReferenceArticle;
use App\Helper\QueryCounter;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Alert|null find($id, $lockMode = null, $lockVersion = null)
 * @method Alert|null findOneBy(array $criteria, array $orderBy = null)
 * @method Alert[]    findAll()
 * @method Alert[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlertRepository extends EntityRepository {

    public function findForReference($reference, $types) {
        if(!is_array($types)) {
            $types = [$types];
        }

        return $this->createQueryBuilder("a")
            ->where("a.reference = :reference")
            ->andWhere("a.type IN (:types)")
            ->setParameter("reference", $reference)
            ->setParameter("types", $types)
            ->getQuery()
            ->getResult();
    }

    public function findForArticle($article, int $type) {
        return $this->createQueryBuilder("a")
            ->where("a.article = :article")
            ->andWhere("a.type = :type")
            ->setParameter("article", $article)
            ->setParameter("type", $type)
            ->getQuery()
            ->getResult();
    }

    public function getAlertDataByParams($params, $filters) {
        $qb = $this->createQueryBuilder("a")
            ->leftJoin("a.reference", "reference")
            ->leftJoin("a.article", "article");

        $total = QueryCounter::count($qb, "a");

        foreach($filters as $filter) {
            switch($filter['field']) {
                case 'type':
                    $qb
                        ->join('reference.type', 't3')
                        ->andWhere('t3.label LIKE :type')
                        ->setParameter('type', $filter['value']);
                    break;
                case 'alert':
                    $value = Alert::TYPE_LABELS_IDS[$filter['value']];
                    $qb->andWhere('a.type = :alert')
                        ->setParameter('alert', $value);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);

                    $or = $qb->expr()->orX();
                    foreach($value as $user) {
                        $id = explode(":", $user)[0];
                        $or->add(":user_$id MEMBER OF reference.managers");
                        $or->add(":user_$id MEMBER OF articlera.managers");
                        $qb->setParameter("user_$id", $id);
                    }

                    $qb->andWhere($or)
                        ->leftJoin("article.articleFournisseur", "articleaf")
                        ->leftJoin("articleaf.referenceArticle", "articlera");
                    break;
            }
        }

        // prise en compte des paramètres issus du datatable
        if(!empty($params)) {
            if(!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if(!empty($search)) {
                    $qb
                        ->andWhere('reference.reference LIKE :value OR reference.libelle LIKE :value')
                        ->setParameter('value', '%' . str_replace('_', '\_', $search) . '%');
                }
            }

            $countFiltered = QueryCounter::count($qb, "a");

            if(!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if(!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch($column) {
                        case "label":
                            $qb->addSelect("COALESCE(article.label, reference.libelle) AS HIDDEN label")
                                ->orderBy("label", $order);
                            break;
                        case "reference":
                            $qb->addSelect("COALESCE(article.reference, reference.reference) AS HIDDEN stref")
                                ->orderBy("stref", $order);
                            break;
                        case "code":
                            $qb->addSelect("COALESCE(article.barCode, reference.barCode) AS HIDDEN code")
                                ->orderBy("code", $order);
                            break;
                        case "quantity":
                            $qb->orderBy('quantity', $order);
                            break;
                        case "quantityType":
                            $qb->orderBy("reference.typeQuantite", $order);
                            break;
                        case "securityThreshold":
                            $qb->orderBy('reference.limitSecurity', $order);
                            break;
                        case "warningThreshold":
                            $qb->orderBy('reference.limitWarning', $order);
                            break;
                        case "expiry":
                            $qb->orderBy('article.expiryDate', $order);
                            break;
                        default:
                            $qb->orderBy('a.' . $column, $order);
                            break;
                    }
                }
            }
        }

        $qb->groupBy('a.id')
            ->addSelect('COALESCE(reference.quantiteDisponible, article.quantite) AS quantity');

        if(!empty($params)) {
            if(!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if(!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(AbstractQuery::HYDRATE_OBJECT),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function countAll(): int {
        return $this->createQueryBuilder("a")
            ->select("COUNT(a)")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findNoLongerExpired() {
        $since = new DateTime("now", new DateTimeZone("Europe/Paris"));

        return $this->createQueryBuilder("a")
            ->join("a.article", "ar")
            ->where("ar.id IS NOT NULL")
            ->andWhere("a.type = " . Alert::EXPIRY)
            ->andWhere("ar.expiryDate > :since OR ar.expiryDate IS NULL")
            ->setParameter("since", $since)
            ->getQuery()
            ->getResult();
    }

}
