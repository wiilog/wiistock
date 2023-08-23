<?php

namespace App\Controller;

use App\Entity\SessionHistoryRecord;
use App\Service\CSVExportService;
use App\Service\FormatService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

#[Route('/session-history-record', name: 'session_history_record_')]
class SessionHistoryRecordController extends AbstractController
{
    #[Route('/api', name: 'api', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    public function api(EntityManagerInterface $entityManager,
                        Request                $request,
                        FormatService $formatService): JsonResponse {
        $sessionHistoryRecordRepository = $entityManager->getRepository(SessionHistoryRecord::class);

        $data = $sessionHistoryRecords = $sessionHistoryRecordRepository->findByParams($request->request);
        $data["recordsFiltered"] = 0;


        $sessionHistoryRecords["data"] = Stream::from($sessionHistoryRecords["data"])
            ->map(static fn(SessionHistoryRecord $historyRecord) => [
                "user" => $formatService->user($historyRecord->getUser()),
                "userEmail" => $formatService->user($historyRecord->getUser(), '', true),
                "type" => $formatService->type($historyRecord->getType()),
                "openedAt" => $formatService->datetime($historyRecord->getOpenedAt()),
                "closedAt" => $formatService->dateTime($historyRecord->getClosedAt()),
                "sessionId" => $historyRecord->getSessionId(),
            ])
            ->toArray();

        return new JsonResponse($sessionHistoryRecords);
    }

    #[Route('/csv', name: 'csv', options: ['expose' => true], methods: ['GET'])]
    public function getSessionHistoryRecordsCSV(CSVExportService        $CSVExportService,
                                                EntityManagerInterface  $entityManager,
                                                FormatService           $formatService): Response {
        $csvHeader = [
            "Nom utilisateur",
            "Email",
            "Type de connexion",
            "Date de connexion",
            "Date de déconnexion",
            "Identifiant de la session",
        ];

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        $sessionHistoryRecords = $entityManager->getRepository(SessionHistoryRecord::class)->iterateAll();
        return $CSVExportService->streamResponse(function ($output) use ($formatService, $entityManager, $CSVExportService, $sessionHistoryRecords) {
            foreach ($sessionHistoryRecords as $sessionHistoryRecord) {
                $CSVExportService->putLine($output, $sessionHistoryRecord->serialize($formatService));
            }
        }, "export-session-history-records-$today.csv", $csvHeader);
    }
}
