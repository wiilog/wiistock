<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Menu;
use App\Entity\Statut;
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

    /**
     * @Route("/statuses-api", name="settings_statuses_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK})
     */
    public function disputeStatusesApi(Request                $request,
                                       UserService            $userService,
                                       EntityManagerInterface $manager): JsonResponse
    {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $mode = $request->query->get("mode");
        if (!in_array($mode, ['arrival-dispute', 'reception-dispute', 'purchase-request'])) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $hasAccess = $mode === 'arrival-dispute'
            ? $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_TRACKING)
            // mode === 'reception-dispute' || 'purchase-request'
            : $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_STOCK);

        if (!$hasAccess) {
            throw new BadRequestHttpException();
        }

        $canDelete = $userService->hasRightFunction(Menu::PARAM, Action::DELETE);

        $data = [];

        $treated = Statut::TREATED;
        $notTreated = Statut::NOT_TREATED;
        $inProgress = Statut::IN_PROGRESS;
        $draft = Statut::DRAFT;
        $statutRepository = $manager->getRepository(Statut::class);

        $statuses = $mode === 'arrival-dispute'
            ? $statutRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR)
            : ($mode === 'reception-dispute'
                ? $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT)
                // mode === 'purchase-request'
                : $statutRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST));


        foreach ($statuses as $statut) {
            $actionColumn = $canDelete
                ? "<button class='btn btn-silent delete-row' data-id='{$statut->getId()}'>
                       <i class='wii-icon wii-icon-trash text-primary'></i>
                   </button>
                   <input type='hidden' name='statusId' class='data' value='{$statut->getId()}'/>
                   <input type='hidden' name='mode' class='data' value='{$mode}'/>"
                : "";

            if ($edit) {
                $checkedNotTreated = ($statut->getState() === Statut::NOT_TREATED) ? 'selected' : '';
                $checkedTreated = ($statut->getState() === Statut::TREATED) ? 'selected' : '';
                $checkedInProgress = ($statut->getState() === Statut::IN_PROGRESS) ? 'selected' : '';
                $checkedDraft = ($statut->getState() === Statut::DRAFT) ? 'selected' : '';
                $optionsSelect = "
                    <option/>
                    <option value='{$notTreated}' {$checkedNotTreated}>A traité</option>
                    <option value='{$treated}' {$checkedTreated}>Traité</option>
                " . ($mode === 'purchase-request' ? "
                    <option value='{$draft}' {$checkedDraft}>Brouillon</option>
                    <option value='{$inProgress}' {$checkedInProgress}>En cours</option>" : "");
                $defaultStatut = $statut->isDefaultForCategory() == 1 ? 'checked' : "";
                $sendMailBuyers = $statut->getSendNotifToBuyer() == 1 ? 'checked' : "";
                $sendMailRequesters = $statut->getSendNotifToDeclarant() == 1 ? 'checked' : "";
                $sendMailDest = $statut->getSendNotifToRecipient() == 1 ? 'checked' : "";
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => "<input type='text' name='label' value='{$statut->getNom()}' class='form-control data needed'/>",
                    "state" => "<select name='state' class='data form-control needed select-size'>{$optionsSelect}</select>",
                    "comment" => "<input type='text' name='comment' value='{$statut->getComment()}' class='form-control data'/>",
                    "defaultStatut" => "<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data' {$defaultStatut}/></div>",
                    "sendMailBuyers" => "<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data' {$sendMailBuyers}/></div>",
                    "sendMailRequesters" => "<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data' {$sendMailRequesters}/></div>",
                    "sendMailDest" => "<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data' {$sendMailDest}/></div>",
                    "order" => "<input type='number' name='order' min='1' value='{$statut->getDisplayOrder()}' class='form-control data needed'/>",
                ];
            } else {
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => $statut->getNom(),
                    "state" => $statut->getState() == Statut::NOT_TREATED ? 'A traité' : ($statut->getState() == Statut::TREATED ? 'Traité' : 'Brouillon'),
                    "comment" => $statut->getComment(),
                    "defaultStatut" => $statut->isDefaultForCategory() ? 'Oui' : 'Non',
                    "sendMailBuyers" => $statut->getSendNotifToBuyer() ? 'Oui' : 'Non',
                    "sendMailRequesters" => $statut->getSendNotifToDeclarant() ? 'Oui' : 'Non',
                    "sendMailDest" => $statut->getSendNotifToRecipient() ? 'Oui' : 'Non',
                    "order" => $statut->getDisplayOrder(),
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
