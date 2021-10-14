<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;

class StatusService {

    private $entityManager;
    private $security;
    private $templating;
    private $router;

    public function __construct(EntityManagerInterface $entityManager,
                                Security $security,
                                Twig_Environment $templating,
                                RouterInterface $router) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->templating = $templating;
        $this->router = $router;
    }

    public function updateStatus(Statut $status, ?Type $type, array $data): Statut {
        $status
            ->setNom($data['label'])
            ->setState($data['state'])
            ->setDefaultForCategory((bool)$data['defaultForCategory'])
            ->setSendNotifToBuyer((bool)$data['sendMails'])
            ->setCommentNeeded((bool)$data['commentNeeded'])
            ->setNeedsMobileSync((bool)$data['needsMobileSync'])
            ->setSendNotifToDeclarant((bool)$data['sendMailsDeclarant'])
            ->setSendNotifToRecipient((bool)$data['sendMailsRecipient'])
            ->setAutomaticReceptionCreation((bool)$data['automaticReceptionCreation'])
            ->setDisplayOrder((int)$data['displayOrder'])
            ->setComment($data['comment'])
            ->setType($type);
        return $status;
    }

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $statusRepository = $this->entityManager->getRepository(Statut::class);

        $statusFilter = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_STATUS, $this->security->getUser());
        $queryResult = $statusRepository->findByParamsAndFilters($params, $statusFilter);

        $statusArray = $queryResult['data'];

        $rows = [];
        foreach ($statusArray as $status) {
            $rows[] = $this->dataRowStatus($status);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowStatus($status) {

        $url['edit'] = $this->router->generate('status_api_edit', ['id' => $status->getId()]);
        return [
            'id' => $status->getId() ?? '',
            'category' => $status->getCategorie() ? $status->getCategorie()->getNom() : '',
            'label' => $status->getNom() ?: '',
            'comment' => $status->getComment() ?: '',
            'state' => $this->getStatusStateLabel($status->getState()),
            'defaultStatus' => $status->isDefaultForCategory() ? 'oui' : 'non',
            'notifToDeclarant' => $status->getSendNotifToDeclarant() ? 'oui' : 'non',
            'order' => $status->getDisplayOrder() ?? '',
            'type' => $status->getType() ? $status->getType()->getLabel() : '',
            'actions' => $this->templating->render('status/datatableStatusRow.html.twig', [
                'url' => $url,
                'statusId' => $status->getId(),
            ]),
        ];
    }

    public function getStatusStatesValues(): array {
        return [
            [
                'label' => 'Brouillon',
                'id' => Statut::DRAFT,
                'code' => 'draft'
            ],
            [
                'label' => 'À traiter',
                'id' => Statut::NOT_TREATED,
                'code' => 'notTreated'
            ],
            [
                'label' => 'En cours',
                'id' => Statut::IN_PROGRESS,
                'code' => 'inProgress'
            ],
            [
                'label' => 'Traité',
                'id' => Statut::TREATED,
                'code' => 'treated'
            ],
            [
                'label' => 'Litige',
                'id' => Statut::DISPUTE,
                'code' => 'dispute'
            ],
            [
                'label' => 'Partiel',
                'id' => Statut::PARTIAL,
                'code' => 'partial'
            ]
        ];
    }

    public function getStatusStateLabel(int $stateId): ?string {
        $states = $this->getStatusStatesValues();
        $label = null;
        foreach ($states as $state) {
            if ($state['id'] === $stateId) {
                $label = $state['label'];
                break;
            }
        }
        return $label;
    }

    public function getStatusStateCode(int $stateId): ?string {
        $states = $this->getStatusStatesValues();
        $label = null;
        foreach ($states as $state) {
            if ($state['id'] === $stateId) {
                $label = $state['code'];
                break;
            }
        }
        return $label;
    }

}
