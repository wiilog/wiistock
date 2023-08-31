<?php

namespace App\Controller;

use App\Entity\CountSimultaneousOpenedSessions;
use App\Entity\SessionHistoryRecord;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\SessionHistoryRecordService;
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

    #[Route('/active-licence-count', name: 'active_licence_count', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function activeLicenceCount(EntityManagerInterface      $entityManager,
                                       SessionHistoryRecordService $sessionHistoryRecordService): JsonResponse {
        $sessionHistoryRecordRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $activeLicenceCount = $sessionHistoryRecordRepository->countOpenedSessions();
        $sessionHistoryRecordService->updateSimultaneousOpenedSessionCounter($entityManager, $activeLicenceCount);
        $maxLicenceCount = $sessionHistoryRecordService->getOpenedSessionLimit();

        return new JsonResponse([
            'success' => true,
            'refreshed' => $sessionHistoryRecordService->refreshDate($entityManager),
            'activeLicenceCount' => $activeLicenceCount,
            'maxLicenceCount' => $maxLicenceCount,
        ]);
    }


    #[Route('/chart-data', name: 'chart_data', options: ['expose' => true], methods: ['GET'])]
    public function getChartData(Request $request, EntityManagerInterface $entityManager, FormatService $formatService): JsonResponse
    {
        $filters = $request->query;
        $countSimultaneousOpenedSessionsRepository = $entityManager->getRepository(CountSimultaneousOpenedSessions::class);
        $label = 'Nombre de sessions ouvertes simultanément';

        $counts = $countSimultaneousOpenedSessionsRepository->getByDates(
            DateTime::createFromFormat("Y-m-d", $filters->get('start'), new \DateTimeZone('Europe/Paris')),
            DateTime::createFromFormat("Y-m-d", $filters->get('end'), new \DateTimeZone('Europe/Paris'))
        );

        $data['colors'][$label] = "#000000";
        foreach ($counts as $count) {
            $date = $count->getDateTime();

            $dateStr = $date->format('d/m/Y H:i:s');
            if (!isset($data[$dateStr])) {
                $data[$dateStr] = [];
            }

            $data[$dateStr][$label] = floatval($count->getCount());
        }

        dump($data);
        return new JsonResponse($data);
    }
}
