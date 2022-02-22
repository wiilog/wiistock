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


/**
 * @Route("/parametrage")
 */
class DisputeStatusController extends AbstractController {

    /**
     * @Route("/dispute-statuses-api", name="settings_dispute_statuses_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK})
     */
    public function disputeStatusesApi(Request $request,
                                       UserService $userService,
                                       EntityManagerInterface $manager): JsonResponse {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $mode = $request->query->get("mode");
        if (!in_array($mode, ['arrival', 'reception'])) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $hasAccess = $mode === 'arrival'
            ? $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_TRACKING)
            : $userService->hasRightFunction(Menu::PARAM, Action::SETTINGS_STOCK);

        if (!$hasAccess) {
            throw new BadRequestHttpException();
        }

        $canDelete = $userService->hasRightFunction(Menu::PARAM, Action::DELETE);

        $data = [];

        $treated = Statut::TREATED;
        $notTreated = Statut::NOT_TREATED;
        $statutRepository = $manager->getRepository(Statut::class);

        $statuses = $mode === 'arrival'
            ? $statutRepository->findByCategorieName(CategorieStatut::DISPUTE_ARR)
            : $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT);


        foreach($statuses as $statut) {
            $actionColumn = $canDelete
                ? "<button class='btn btn-silent delete-row' data-id='{$statut->getId()}'>
                       <i class='wii-icon wii-icon-trash text-primary'></i>
                   </button>
                   <input type='hidden' name='statusId' class='data' value='{$statut->getId()}'/>
                   <input type='hidden' name='mode' class='data' value='{$mode}'/>"
                : "";

            if($edit) {
                $checkedNotTreated = ($statut->getState() === Statut::NOT_TREATED) ? 'selected' : '';
                $checkedTreated = ($statut->getState() === Statut::TREATED) ? 'selected' : '';
                $optionsSelect = "
                    <option/>
                    <option value='{$treated}' {$checkedTreated}>Traité</option>
                    <option value='{$notTreated}' {$checkedNotTreated}>A traité</option>
                ";
                $defaultStatut = $statut->isDefaultForCategory() == 1 ? 'checked' : "";
                $sendMailBuyers = $statut->getSendNotifToBuyer()== 1 ? 'checked' : "";
                $sendMailRequesters = $statut->getSendNotifToDeclarant() == 1 ? 'checked' : "";
                $sendMailDest = $statut->getSendNotifToRecipient() == 1 ? 'checked' : "";
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => "<input type='text' name='label' value='{$statut->getNom()}' class='form-control data needed'/>",
                    "state"=> "<select name='state' class='data form-control needed select-size'>{$optionsSelect}</select>",
                    "comment"=> "<input type='text' name='comment' value='{$statut->getComment()}' class='form-control data'/>",
                    "defaultStatut"=> "<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data' {$defaultStatut}/></div>",
                    "sendMailBuyers"=> "<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data' {$sendMailBuyers}/></div>",
                    "sendMailRequesters"=> "<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data' {$sendMailRequesters}/></div>",
                    "sendMailDest"=> "<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data' {$sendMailDest}/></div>",
                    "order"=> "<input type='number' name='order' min='1' value='{$statut->getState()}' class='form-control data needed'/>",
                ];
            } else {
                $data[] = [
                    "actions" => $actionColumn,
                    "label" => $statut->getNom(),
                    "state"=> $statut->getState() == Statut::NOT_TREATED ? 'A traité' : 'Traité',
                    "comment"=> $statut->getComment(),
                    "defaultStatut"=> $statut->isDefaultForCategory() ? 'Oui' : 'Non',
                    "sendMailBuyers"=> $statut->getSendNotifToBuyer() ? 'Oui' : 'Non',
                    "sendMailRequesters"=> $statut->getSendNotifToDeclarant() ? 'Oui' : 'Non',
                    "sendMailDest"=> $statut->getSendNotifToRecipient() ? 'Oui' : 'Non',
                    "order"=> $statut->getDisplayOrder(),
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
     * @Route("/dispute-status/{entity}/supprimer", name="settings_delete_dispute_status", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteDisputeStatus(EntityManagerInterface $manager, Statut $entity): JsonResponse {
        if ($entity->getDisputes()->isEmpty()){
            $manager->remove($entity);
            $manager->flush();
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Impossible de supprimer le statut car il est associé à des litiges",
            ]);
        }
        return $this->json([
            "success" => true,
            "msg" => "Le statut a été supprimé",
        ]);
    }

}
