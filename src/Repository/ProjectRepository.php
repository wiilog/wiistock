<?php

namespace App\Repository;

use App\Entity\Project;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends EntityRepository
{
    public function findByParams(InputBag $params): array
    {

        $queryBuilder = $this->createQueryBuilder('project');
        $total = QueryBuilderHelper::count($queryBuilder, 'project');

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $queryBuilder->expr();
                    $queryBuilder
                        ->andWhere($exprBuilder->orX(
                            'project.code LIKE :value',
                            'project.description LIKE :value',
                            'search_project_manager.username LIKE :value',
                        ))
                        ->leftJoin('project.projectManager', 'search_project_manager')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'projectManager':
                            $queryBuilder
                                ->leftJoin('project.projectManager', 'order_project_manager')
                                ->orderBy("order_project_manager.username", $order);
                            break;
                        case 'code':
                            $queryBuilder->orderBy("project.code", $order);
                            break;
                        case 'decription':
                            $queryBuilder->orderBy("project.description", $order);
                            break;
                        default:
                            if (property_exists(Project::class, $column)) {
                                $queryBuilder->orderBy('project.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, 'project');

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $queryBuilder->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function findActive(): array {
        return $this->createQueryBuilder("project")
            ->andWhere("project.active = 1")
            ->getQuery()
            ->getResult();
    }

    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder("project")
            ->select("project.id AS id, project.code AS text")
            ->andWhere("project.code LIKE :term")
            ->andWhere("project.active = 1")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function iterateAll(): iterable {
        return $this->createQueryBuilder("project")
            ->getQuery()
            ->toIterable();
    }

}
