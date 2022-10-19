<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Pack;
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

    public function countArticle(Project $project): int {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->from(Article::class, 'article')
            ->select('COUNT(article)')
            ->andWhere('article.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLogisticUnit(Project $project): int {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->from(Pack::class, 'pack')
            ->select('COUNT(pack)')
            ->andWhere('pack.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
