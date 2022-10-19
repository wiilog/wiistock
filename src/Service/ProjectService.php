<?php


namespace App\Service;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;


class ProjectService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public FormatService $formatService;

    public function getDataForDatatable(InputBag $params): array {
        $queryResult = $this->manager->getRepository(Project::class)->findByParams($params);

        $projects = $queryResult['data'];

        $rows = [];
        foreach ($projects as $project) {
            $rows[] = $this->dataRowProject($project);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowProject(Project $project): array {
        return [
            'code' => $project->getCode(),
            'description' => $project->getDescription(),
            'projectManager' => $this->formatService->user($project->getProjectManager()),
            'active' => $project->isActive(),
            'actions' => $this->templating->render('project/actions.html.twig', [
                'id' => $project->getId(),
            ]),
        ];
    }
}
