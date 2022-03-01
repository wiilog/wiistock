<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
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
            self::MODE_ARRIVAL
        ];

        if (!in_array($mode, $availableMode)) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $hasAccess = match($mode) {
            self::MODE_ARRIVAL_DISPUTE, self::MODE_ARRIVAL => $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_TRACKING),
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
                $stateOptions = Stream::from([['empty' => true]], $statusService->getStatusStatesValues($mode))
                    ->map(function(array $state) use ($status) {
                        if ($state['empty'] ?? false) {
                            return '<option/>';
                        }
                        else {
                            $selected = $state['id'] == $status->getState() ? 'selected' : '';
                            return "<option value='{$state['id']}' {$selected}>{$state['label']}</option>";
                        }
                    })
                    ->join('');

                $defaultStatut = $status->isDefaultForCategory() == 1 ? 'checked' : "";
                $sendMailBuyers = $status->getSendNotifToBuyer() == 1 ? 'checked' : "";
                $sendMailRequesters = $status->getSendNotifToDeclarant() == 1 ? 'checked' : "";
                $sendMailDest = $status->getSendNotifToRecipient() == 1 ? 'checked' : "";

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
                    "order" => "<input type='number' name='order' min='1' value='{$status->getDisplayOrder()}' class='form-control data needed'/>",
                ];
            } else {
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => $status->getNom(),
                    "type" => FormatHelper::type($status->getType()),
                    "state" => $statusService->getStatusStateLabel($status->getState()),
                    "comment" => $status->getComment(),
                    "defaultStatut" => $status->isDefaultForCategory() ? 'Oui' : 'Non',
                    "sendMailBuyers" => $status->getSendNotifToBuyer() ? 'Oui' : 'Non',
                    "sendMailRequesters" => $status->getSendNotifToDeclarant() ? 'Oui' : 'Non',
                    "sendMailDest" => $status->getSendNotifToRecipient() ? 'Oui' : 'Non',
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
        $constraints = [
            "un litige" => $entity->getDisputes(),
            "une demande d'achat" => $entity->getPurchaseRequests(),
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
