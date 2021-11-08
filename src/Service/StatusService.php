<?php

namespace App\Service;

use App\Entity\CategorieStatut;
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

    public function updateStatus(EntityManagerInterface $entityManager, Statut $status, array $data): Statut {
        $typeRepository = $entityManager->getRepository(Type::class);
        $type = $typeRepository->find($data['type']);

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

    public function validateStatusData(EntityManagerInterface $entityManager, array $data, ?Statut $status = null): array {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $categoryStatusRepository = $entityManager->getRepository(CategorieStatut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $category = $status
            ? $status->getCategorie()
            : $categoryStatusRepository->find($data['category']);

        $type = $typeRepository->find($data['type']);

        $defaults = $statusRepository->countDefaults($category, $type, $status);
        $drafts = $statusRepository->countDrafts($category, $type, $status);
        $disputes = $statusRepository->countDisputes($category, $type, $status);

        if ($statusRepository->countSimilarLabels($category, $data['label'], $data['type'])) {
            $message = 'Le statut "' . $data['label'] . '" existe déjà pour cette catégorie. Veuillez en choisir un autre.';
        } else if ($data['defaultForCategory'] && $defaults > 0) {
            $message = 'Vous ne pouvez pas créer un statut par défaut pour cette entité et ce type, il en existe déjà un.';
        } else if (((int) $data['state']) === Statut::DRAFT && $drafts > 0) {
            $message = 'Vous ne pouvez pas créer un statut brouillon pour cette entité et ce type, il en existe déjà un.';
        } else if (((int) $data['state']) === Statut::DISPUTE && $disputes > 0) {
            $message = 'Vous ne pouvez pas créer un statut litige pour cette entité et ce type, il en existe déjà un.';
        }

        return [
            'success' => empty($message),
            'message' => $message ?? null
        ];
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
