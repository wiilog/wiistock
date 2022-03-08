<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\StatusService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


/**
 * @Route("/parametrage")
 */
class StatusController extends AbstractController
{
    const MODE_ARRIVAL_DISPUTE = 'arrival-dispute';
    const MODE_RECEPTION_DISPUTE = 'reception-dispute';
    const MODE_PURCHASE_REQUEST = 'purchase-request';
    const MODE_ARRIVAL = 'arrival';
    const MODE_DISPATCH= 'dispatch';
    const MODE_HANDLING= 'handling';

    /**
     * @Route("/statuses-api", name="settings_statuses_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK})
     */
    public function statusesApi(Request                $request,
                                UserService            $userService,
                                StatusService          $statusService,
                                EntityManagerInterface $entityManager): JsonResponse {
        $edit = $request->query->getBoolean("edit");
        $mode = $request->query->get("mode");
        $typeId = $request->query->get("type");

        $availableMode = [
            self::MODE_ARRIVAL_DISPUTE,
            self::MODE_RECEPTION_DISPUTE,
            self::MODE_PURCHASE_REQUEST,
            self::MODE_ARRIVAL,
            self::MODE_DISPATCH,
            self::MODE_HANDLING
        ];

        if (!in_array($mode, $availableMode)) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $hasAccess = match($mode) {
            self::MODE_ARRIVAL_DISPUTE, self::MODE_ARRIVAL, self::MODE_DISPATCH, self::MODE_HANDLING => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_TRACKING),
            self::MODE_RECEPTION_DISPUTE, self::MODE_PURCHASE_REQUEST => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_STOCK)
        };

        if (!$hasAccess) {
            throw new BadRequestHttpException();
        }

        $canDelete = $userService->hasRightFunction(Menu::PARAM, Action::DELETE);

        $data = [];

        $statusRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $category = match ($mode) {
            self::MODE_ARRIVAL_DISPUTE => CategorieStatut::DISPUTE_ARR,
            self::MODE_RECEPTION_DISPUTE => CategorieStatut::LITIGE_RECEPT,
            self::MODE_PURCHASE_REQUEST => CategorieStatut::PURCHASE_REQUEST,
            self::MODE_ARRIVAL => CategorieStatut::ARRIVAGE,
            self::MODE_DISPATCH => CategorieStatut::DISPATCH,
            self::MODE_HANDLING => CategorieStatut::HANDLING
        };

        $type = $typeId ? $typeRepository->find($typeId) : null;
        $statuses = $statusRepository->findStatusByType($category, $type);

        foreach ($statuses as $status) {
            $actionColumn = $canDelete
                ? "<button class='btn btn-silent delete-row' data-id='{$status->getId()}'>
                       <i class='wii-icon wii-icon-trash text-primary'></i>
                   </button>
                   <input type='hidden' name='statusId' class='data' value='{$status->getId()}'/>
                   <input type='hidden' name='mode' class='data' value='{$mode}'/>"
                : "";

            if ($edit) {
                $stateOptions = $statusService->getStatusStatesOptions($mode, $status->getState(), true);

                $disabledMobileSync = in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) ? 'disabled' : '';

                $defaultStatut = $status->isDefaultForCategory() ? 'checked' : "";
                $sendMailBuyers = $status->getSendNotifToBuyer() ? 'checked' : "";
                $sendMailRequesters = $status->getSendNotifToDeclarant() ? 'checked' : "";
                $sendMailDest = $status->getSendNotifToRecipient() ? 'checked' : "";
                $needsMobileSync = (!in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) && $status->getNeedsMobileSync()) ? 'checked' : "";
                $commentNeeded = $status->getCommentNeeded() ? 'checked' : "";
                $automaticReceptionCreation = $status->getAutomaticReceptionCreation() ? 'checked' : "";

                $data[] = [
                    "actions" => $actionColumn,
                    "label" => "<input type='text' name='label' value='{$status->getNom()}' class='form-control data needed'/>",
                    "state" => "<select name='state' class='data form-control needed select-size'>{$stateOptions}</select>",
                    "comment" => "<input type='text' name='comment' value='{$status->getComment()}' class='form-control data'/>",
                    "type" => FormatHelper::type($status->getType()),
                    "defaultStatut" => "<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data' {$defaultStatut}/></div>",
                    "sendMailBuyers" => "<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data' {$sendMailBuyers}/></div>",
                    "sendMailRequesters" => "<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data' {$sendMailRequesters}/></div>",
                    "sendMailDest" => "<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data' {$sendMailDest}/></div>",
                    "needsMobileSync" => "<div class='checkbox-container'><input type='checkbox' name='needsMobileSync' class='form-control data' {$disabledMobileSync} {$needsMobileSync}/></div>",
                    "commentNeeded" => "<div class='checkbox-container'><input type='checkbox' name='commentNeeded' class='form-control data' {$commentNeeded}/></div>",
                    "automaticReceptionCreation" => "<div class='checkbox-container'><input type='checkbox' name='automaticReceptionCreation' class='form-control data' {$automaticReceptionCreation}/></div>",
                    "order" => "<input type='number' name='order' min='1' value='{$status->getDisplayOrder()}' class='form-control data needed px-2 text-center' data-no-arrow/>",
                ];
            } else {
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => $status->getNom(),
                    "type" => FormatHelper::type($status->getType()),
                    "state" => $statusService->getStatusStateLabel($status->getState()),
                    "comment" => $status->getComment(),
                    "defaultStatut" => FormatHelper::bool($status->isDefaultForCategory()),
                    "sendMailBuyers" => FormatHelper::bool($status->getSendNotifToBuyer()),
                    "sendMailRequesters" => FormatHelper::bool($status->getSendNotifToDeclarant()),
                    "sendMailDest" => FormatHelper::bool($status->getSendNotifToRecipient()),
                    "needsMobileSync" => FormatHelper::bool(!in_array($status->getState(), [Statut::DRAFT, Statut::TREATED]) && $status->getNeedsMobileSync()),
                    "commentNeeded" => FormatHelper::bool($status->getCommentNeeded()),
                    "automaticReceptionCreation" => FormatHelper::bool($status->getAutomaticReceptionCreation()),
                    "order" => $status->getDisplayOrder(),
                ];
            }
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/status/{entity}/supprimer", name="settings_delete_status", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteStatus(EntityManagerInterface $manager, Statut $entity): JsonResponse
    {
        if($entity->isDefaultForCategory()) {
            return $this->json([
                "success" => false,
                "msg" => "Impossible de supprimer le statut car il est un statut par défaut",
            ]);
        } else {
            $constraints = [
                "un litige" => $entity->getDisputes(),
                "une demande d'achat" => $entity->getPurchaseRequests(),
                "un arrivage" => $entity->getArrivages(),
                "un article" => $entity->getArticles(),
                "une collecte" => $entity->getCollectes(),
                "une demande de livraison" => $entity->getDemandes(),
                "un ordre de livraison" => $entity->getLivraisons(),
                "une préparation" => $entity->getPreparations(),
                "une réception" => $entity->getReceptions(),
                "une référence article" => $entity->getReferenceArticles(),
                "une demande de service" => $entity->getHandlings(),
                "une demande d'acheminement" => $entity->getDispatches(),
                "une demande de transfert" => $entity->getTransferRequests(),
                "un ordre de transfert" => $entity->getTransferOrders(),
            ];

            $constraints = Stream::from($constraints)
                ->filter(fn($collection) => !$collection->isEmpty())
                ->takeKeys()
                ->map(fn(string $item) => "au moins $item")
                ->join(", ");

            if (!$constraints) {
                $manager->remove($entity);
                $manager->flush();
            } else {
                return $this->json([
                    "success" => false,
                    "msg" => "Impossible de supprimer le statut car il est lié à $constraints",
                ]);
            }

            return $this->json([
                "success" => true,
                "msg" => "Le statut a été supprimé",
            ]);
        }
    }
}
