<?php

namespace App\Repository;

use App\Entity\TagTemplate;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<TagTemplate>
 *
 * @method TagTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method TagTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method TagTemplate[]    findAll()
 * @method TagTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagTemplateRepository extends EntityRepository {

    public function save(TagTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TagTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
