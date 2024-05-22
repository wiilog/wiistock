<?php

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\Traits\AttachmentTrait;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use WiiCommon\Helper\Stream;

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

	public function findOneByFileNameAndDisputeId($fileName, $disputeId)
	{
        $qb = $this->createQueryBuilder('attachment');

        $qb
            ->select('attachment')
            ->where('attachment.fileName = :fileName')
            ->andWhere('attachment.dispute = :disputeId')
            ->setParameters([
                'fileName' => $fileName,
                'disputeId' => $disputeId
            ]);

        return $qb
            ->getQuery()
            ->getResult();
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

    public function getUnusedAttachments(): array
    {
        $entityManager = $this->getEntityManager();
        $metas = $entityManager->getMetadataFactory()->getAllMetadata();
        $classWhoUseAttachmentTrait = Stream::from($metas)
            ->filter(static function (ClassMetadata $meta) {
                $reflexionClass = new ReflectionClass($meta->getName());
                return in_array(AttachmentTrait::class, $reflexionClass->getTraitNames());
            })
            ->toArray();

        $query = $this->createQueryBuilder('attachment');

        foreach ($classWhoUseAttachmentTrait as $meta) {
            $reflectionClass = new ReflectionClass($meta->getName());
            $entityAlias = strtolower($reflectionClass->getShortName());
            $query->leftJoin($meta->getName(), $entityAlias);
            foreach ($meta->associationMappings as $association) {
                if ($association['targetEntity'] === Attachment::class) {
                    $fieldAlias = $entityAlias . '_' . $association['fieldName'];
                    $fieldName = $association['fieldName'];
                    if (isset($association['mappedBy']) || isset($association['joinTable']) || isset($association['inversedBy'])) {
                            $query->leftJoin("$entityAlias.$fieldName", $fieldAlias)
                            ->andWhere("$fieldAlias.id is NULL");
                    } else {
                        $query->andWhere("$entityAlias.$fieldName IS NULL");
                    }
                }
            }
        }
        dump($query->getQuery());

        return $query->setMaxResults(500)->getQuery()->getResult();
    }

}
