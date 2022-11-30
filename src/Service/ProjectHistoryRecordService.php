<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\ProjectHistoryRecord;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

class ProjectHistoryRecordService {

    #[Required]
    public PackService $packService;

    #[Required]
    public FormatService $formatService;

    public function changeProject(EntityManagerInterface $entityManager,
                                  Article|Pack           $item,
                                  ?Project                $project,
                                  DateTime               $recordDate): ?ProjectHistoryRecord {

        /** @var Pack $trackingPack */
        $trackingPack = match ($item instanceof Article) {
            true => $this->packService->updateArticlePack($entityManager, $item),
            false => $item
        };

        $oldProject = $trackingPack->getProject();

        if ($oldProject?->getId() !== $project?->getId()) {
            $trackingPack->setProject($project);

            $historyRecord = new ProjectHistoryRecord();
            $historyRecord
                ->setCreatedAt($recordDate)
                ->setProject($project);
            $entityManager->persist($historyRecord);
            $trackingPack->addProjectHistoryRecord($historyRecord);
        }

        return $historyRecord ?? null;
    }

    public function getProjectHistoryForDatatable(EntityManagerInterface $entityManager,
                                                  Pack                   $pack,
                                                                         $params): array {
        $projectHistoryRecordRepository = $entityManager->getRepository(ProjectHistoryRecord::class);

        $queryResult = $projectHistoryRecordRepository->findLineForProjectHistory($pack, $params);

        $lines = $queryResult["data"];

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = $this->serialize($line);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['filtered'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    private function serialize(ProjectHistoryRecord $projectHistoryRecord): array {
        return [
            'project' => !empty($this->formatService->project($projectHistoryRecord->getProject()))
                ? $this->formatService->project($projectHistoryRecord->getProject())
                : 'Aucun projet',
            'createdAt' => $this->formatService->datetime($projectHistoryRecord->getCreatedAt()),
        ];
    }
}
