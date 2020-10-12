<?php

namespace App\Repository;

use App\Entity\Attachment;
use Doctrine\ORM\EntityRepository;

/**
 * @method Attachment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Attachment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Attachment[]    findAll()
 * @method Attachment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AttachmentRepository extends EntityRepository
{
    public function findOneByFileName($filename)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
            "SELECT pj
           FROM App\Entity\Attachment pj
           WHERE pj.fileName = :filename"
		)->setParameter('filename', $filename);
		;
		return $query->getResult();
	}

	public function findOneByFileNameAndLitigeId($filename, $litigeId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang  DQL */
            "SELECT pj
           FROM App\Entity\Attachment pj
           WHERE pj.fileName = :filename AND pj.litige = :litigeId"
		)->setParameters(['filename' => $filename, 'litigeId' => $litigeId]);

		return $query->getResult();
	}

	public function getNameGroupByMouvements() {
        $queryBuilder = $this->createQueryBuilder('attachment')
            ->select('mouvementTraca.id AS mouvementTracaId')
            ->addSelect('attachment.originalName')
            ->join('attachment.mouvementTraca', 'mouvementTraca');

        $result = $queryBuilder
            ->getQuery()
            ->getResult();

        return array_reduce($result, function ($acc, $attachment) {
            $mouvementTracaId = (int) $attachment['mouvementTracaId'];
            if (empty($acc[$mouvementTracaId])) {
                $acc[$mouvementTracaId] = '';
            }
            else {
                $acc[$mouvementTracaId] .= ', ';
            }

            $acc[$mouvementTracaId] .= $attachment['originalName'];
            return $acc;
        }, []);
    }

    public function getMobileAttachmentForHandling(array $handlingIds): array {
        if (!empty($handlingIds)) {
            $queryBuilder = $this->createQueryBuilder('attachment')
                ->select('attachment.fileName AS fileName')
                ->addSelect('attachment.originalName AS originalName')
                ->addSelect('handling.id AS handlingId')
                ->join('attachment.handling', 'handling')
                ->where('handling.id IN (:handlingIds)')
                ->setParameter('handlingIds', $handlingIds);
            $res = $queryBuilder
                ->getQuery()
                ->getResult();
        }
        else {
            $res = [];
        }
        return $res;

    }

}
