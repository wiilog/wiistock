<?php

namespace App\Controller;

use App\Entity\SessionHistoryRecord;
use App\Service\FormatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
}
