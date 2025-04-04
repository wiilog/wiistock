<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method ReceptionReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionReferenceArticle[]    findAll()
 * @method ReceptionReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionReferenceArticleRepository extends EntityRepository
{

	public function findByReferenceArticleAndReceptionStatus(ReferenceArticle $referenceArticle, array $statuses, ?Reception $ignored = null) {
	    $queryBuilder = $this->createQueryBuilder('reception_reference_article');
	    $queryExpression = $queryBuilder->expr();
        $query = $queryBuilder
            ->join('reception_reference_article.referenceArticle', 'reference_article')
            ->join('reception_reference_article.receptionLine', 'receptionLine')
            ->join('receptionLine.reception', 'reception')
            ->join('reception.statut', 'status')
            ->andWhere('reference_article = :ref')
            ->andWhere('status.code IN (:statuses)')
            ->andWhere(
                $queryExpression->orX(
                    'reception_reference_article.quantite != reception_reference_article.quantiteAR',
                    'status.code = :inProgress'
                )
            )
            ->setParameters([
                'ref' => $referenceArticle,
                'statuses' => $statuses,
                'inProgress' => Reception::STATUT_EN_ATTENTE
            ]);

	    if ($ignored) {
	        $query
                ->andWhere('reception != :recep')
                ->setParameter('recep', $ignored);
        }
	    return $query
            ->getQuery()
            ->getResult();
    }

    public function getAssociatedIdAndReferences(int $disputeId = null): array {
        $subQuery = $this->createQueryBuilder('sub_reception_reference_article')
            ->select("GROUP_CONCAT(sub_join_referenceArticle.reference SEPARATOR ', ')")
            ->join('sub_reception_reference_article.referenceArticle', 'sub_join_referenceArticle')
            ->join('sub_reception_reference_article.articles', 'sub_join_articles')
            ->join('sub_join_articles.disputes', 'sub_join_disputes')
            ->where('sub_join_disputes.id = join_disputes.id')
            ->getQuery()
            ->getDQL();

        $results = $this->createQueryBuilder('reception_reference_article')
            ->select("($subQuery) AS references")
            ->addSelect('join_disputes.id AS disputeId')
            ->join('reception_reference_article.articles', 'join_articles')
            ->join('join_articles.disputes', 'join_disputes');

        if($disputeId) {
            $results
                ->andWhere('join_disputes.id = :disputeId')
                ->setParameter('disputeId', $disputeId);
        }

        $results = $results
            ->getQuery()
            ->getResult();

        return Stream::from($results)
            ->keymap(fn($line) => [$line['disputeId'], $line['references']])
            ->toArray();
    }

    public function getAssociatedIdAndOrderNumbers(int $disputeId = null): array {
        $subQuery = $this->createQueryBuilder('sub_reception_reference_article')
            ->select("GROUP_CONCAT(sub_reception_reference_article.commande SEPARATOR ', ')")
            ->join('sub_reception_reference_article.articles', 'sub_join_articles')
            ->join('sub_join_articles.disputes', 'sub_join_disputes')
            ->where('sub_join_disputes.id = join_disputes.id')
            ->getQuery()
            ->getDQL();

        $results = $this->createQueryBuilder('reception_reference_article')
            ->select("($subQuery) AS orderNumbers")
            ->addSelect('join_disputes.id AS disputeId')
            ->join('reception_reference_article.articles', 'join_articles')
            ->join('join_articles.disputes', 'join_disputes');

        if($disputeId) {
            $results
                ->andWhere('join_disputes.id = :disputeId')
                ->setParameter('disputeId', $disputeId);
        }

        $results = $results
            ->getQuery()
            ->getResult();

        return Stream::from($results)
            ->keymap(fn($line) => [$line['disputeId'], $line['orderNumbers']])
            ->toArray();
    }
}
