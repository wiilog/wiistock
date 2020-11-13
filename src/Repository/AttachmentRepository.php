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
    public function findOneByFileName($fileName)
	{
	    $qb = $this->createQueryBuilder('attachment');

	    $qb
            ->where('attachment.fileName = :fileName')
            ->setParameter('fileName', $fileName);

	    return $qb
            ->getQuery()
            ->getResult();
	}

	public function findOneByFileNameAndLitigeId($fileName, $litigeId)
	{
        $qb = $this->createQueryBuilder('attachment');

        $qb
            ->select('attachment')
            ->where('attachment.fileName = :fileName')
            ->andWhere('attachment.litige = :litigeId')
            ->setParameters([
                'fileName' => $fileName,
                'litigeId' => $litigeId
            ]);

        return $qb
            ->getQuery()
            ->getResult();
	}

	public function getNameGroupByMovements() {
        $queryBuilder = $this->createQueryBuilder('attachment')
            ->select('trackingMovement.id AS trackingMovementId')
            ->addSelect('attachment.originalName')
            ->join('attachment.trackingMovement', 'trackingMovement');

        $result = $queryBuilder
            ->getQuery()
            ->getResult();

        return array_reduce($result, function ($acc, $attachment) {
            $trackingMovementId = (int) $attachment['trackingMovementId'];
            if (empty($acc[$trackingMovementId])) {
                $acc[$trackingMovementId] = '';
            }
            else {
                $acc[$trackingMovementId] .= ', ';
            }

            $acc[$trackingMovementId] .= $attachment['originalName'];
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
