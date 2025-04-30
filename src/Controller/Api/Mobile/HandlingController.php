<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Entity\Handling;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Service\AttachmentService;
use App\Service\FreeFieldService;
use App\Service\HandlingService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\StatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Annotation as Wii;

#[Route("/api/mobile")]
class HandlingController extends AbstractController {

    #[Route("/handlings", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function postHandlings(Request                $request,
                                  AttachmentService      $attachmentService,
                                  EntityManagerInterface $entityManager,
                                  FreeFieldService       $freeFieldService,
                                  StatusService          $statusService,
                                  SettingsService        $settingsService,
                                  HandlingService        $handlingService,
                                  StatusHistoryService   $statusHistoryService)
    {
        $nomadUser = $this->getUser();

        $handlingRepository = $entityManager->getRepository(Handling::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $data = [];

        $commentaire = $request->request->get('comment');
        $id = $request->request->get('id');
        /** @var Handling $handling */
        $handling = $handlingRepository->find($id);
        $oldStatus = $handling->getStatus();

        if (!$oldStatus || !$oldStatus->isTreated()) {
            $statusId = $request->request->get('statusId');
            $newStatus = $statusRepository->find($statusId);
            if (!empty($newStatus)) {
                $statusHistoryService->updateStatus($entityManager, $handling, $newStatus, [
                    "initiatedBy" => $nomadUser,
                ]);
            }

            $treatmentDelay = $request->request->get('treatmentDelay');
            if (!empty($commentaire)) {
                $previousComments = $handling->getComment() !== '<p><br></p>' ? "{$handling->getComment()}\n" : "";
                $dateStr = (new DateTime())->format('d/m/y H:i:s');
                $dateAndUser = "<strong>$dateStr - {$nomadUser->getUsername()} :</strong>";
                $handling->setComment("$previousComments $dateAndUser $commentaire");
            }

            if (!empty($treatmentDelay)) {
                $handling->setTreatmentDelay($treatmentDelay);
            }

            $maxNbFilesSubmitted = 10;
            $fileCounter = 1;
            $addedAttachments = [];
            // upload of photo_1 to photo_10
            do {
                $photoFile = $request->files->get("photo_$fileCounter");
                if (!empty($photoFile)) {
                    $attachments = $attachmentService->createAttachmentsDeprecated([$photoFile]);
                    if (!empty($attachments)) {
                        $attachment = $attachments[0];
                        $handling->addAttachment($attachment);
                        $entityManager->persist($attachment);

                        $addedAttachments[] = [
                            "name" => $attachment->getOriginalName(),
                            "href" => "{$request->getSchemeAndHttpHost()}/uploads/attachments/{$attachment->getFileName()}",
                        ];
                    }
                }
                $fileCounter++;
            } while (!empty($photoFile) && $fileCounter <= $maxNbFilesSubmitted);

            $freeFieldValuesStr = $request->request->get('freeFields', '{}');
            $freeFieldValuesStr = json_decode($freeFieldValuesStr, true);
            $freeFieldService->manageFreeFields($handling, $freeFieldValuesStr, $entityManager);

            if (!$handling->getValidationDate()
                && $newStatus) {
                if ($newStatus->isTreated()) {
                    $handling
                        ->setValidationDate(new DateTime('now'));
                }
                $handling->setTreatedByHandling($nomadUser);
            }
            $entityManager->flush();

            if ((!$oldStatus && $newStatus)
                || (
                    $oldStatus
                    && $newStatus
                    && ($oldStatus->getId() !== $newStatus->getId())
                )) {
                $viewHoursOnExpectedDate = !$settingsService->getValue($entityManager, Setting::REMOVE_HOURS_DATETIME);
                $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate);
            }

            $data['success'] = true;
            $data['state'] = $statusService->getStatusStateCode($handling->getStatus()->getState());
            $data['freeFields'] = json_encode($handling->getFreeFields());
            $data["addedAttachments"] = $addedAttachments;
        } else {
            $data['success'] = false;
            $data['message'] = "Cette demande de service a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }
}
