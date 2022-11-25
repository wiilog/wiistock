<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Livraison;

use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class LivraisonService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $security;

    private $entityManager;

    #[Required]
    public FormatService $formatService;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->security = $security;
    }

    public function getDataForDatatable($params = null, $filterDemandId = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $livraisonRepository = $this->entityManager->getRepository(Livraison::class);

        if ($filterDemandId) {
            $filters = [
                [
                    'field' => FiltreSup::FIELD_DEMANDE,
                    'value' => $filterDemandId
                ]
            ];
        }
        else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_LIVRAISON, $this->security->getUser());
        }
		$queryResult = $livraisonRepository->findByParamsAndFilters($params, $filters);

		$livraisons = $queryResult['data'];

		$rows = [];
		foreach ($livraisons as $livraison) {
			$rows[] = $this->dataRowLivraison($livraison);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

    public function dataRowLivraison(Livraison $livraison)
    {
		$url['show'] = $this->router->generate('livraison_show', ['id' => $livraison->getId()]);

		$preparation = $livraison->getPreparation() ?? null;
		$lastMessage = $preparation ? $livraison->getPreparation()->getLastMessage() : null;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper()) ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName() : null;
        $hasPairing = $preparation && !$preparation->getPairings()->isEmpty();

		$demande = $livraison->getDemande();

        return [
            'id' => $livraison->getId() ?? '',
            'Numéro' => $livraison->getNumero() ?? '',
            'Date' => $livraison->getDate() ? $livraison->getDate()->format('d/m/Y') : '',
            'Statut' => $livraison->getStatut() ? $this->formatService->status($livraison->getStatut()) : '',
            'Opérateur' => $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
            'Type' => $demande ? $this->formatService->type($demande->getType()) : '',
            'Actions' => $this->templating->render('livraison/datatableLivraisonRow.html.twig', ['url' => $url,
                'titleLogo' => !$livraison->getPreparation()->getPairings()->isEmpty() ? 'pairing' : null
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing,
            ]),
        ];
    }

    public function putLivraisonLine($handle,
                                      CSVExportService $csvService,
                                      Livraison $livraison)
    {
        $demande = $livraison->getDemande();
        $preparation = $livraison->getPreparation();
        if (isset($demande) && isset($preparation)) {
            $dataLivraison = [
                $livraison->getNumero() ?? '',
                FormatHelper::status($livraison->getStatut()),
                FormatHelper::datetime($livraison->getDate()),
                FormatHelper::datetime($livraison->getDateFin()),
                FormatHelper::date($demande->getValidatedAt()),
                FormatHelper::deliveryRequester($demande),
                FormatHelper::user($livraison->getUtilisateur()),
                FormatHelper::type($demande->getType()),
                FormatHelper::html($demande->getCommentaire())
            ];

            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                if ($referenceLine->getPickedQuantity() > 0) {
                    $referenceArticle = $referenceLine->getReference();
                    $line = array_merge($dataLivraison, [
                        $referenceArticle->getReference() ?? '',
                        $referenceArticle->getLibelle() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $referenceLine->getQuantityToPick() ?? 0,
                        $referenceArticle->getQuantiteStock() ?? 0,
                        $referenceArticle->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }

            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                if ($articleLine->getPickedQuantity() > 0) {
                    $article = $articleLine->getArticle();
                    $articleFournisseur = $article->getArticleFournisseur();
                    $referenceArticle = $articleFournisseur ? $articleFournisseur->getReferenceArticle() : null;
                    $reference = $referenceArticle ? $referenceArticle->getReference() : '';

                    $line = array_merge($dataLivraison, [
                        $reference,
                        $article->getLabel() ?? '',
                        $demande->getDestination() ? $demande->getDestination()->getLabel() : '',
                        $articleLine->getQuantityToPick() ?? 0,
                        $article->getQuantite() ?? 0,
                        $article->getBarCode(),
                    ]);
                    $csvService->putLine($handle, $line);
                }
            }
        }
    }

    public function createHeaderDetailsConfig(Dispatch $dispatch): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DISPATCH);

        /** @var Utilisateur $user */
        $user = $this->security->getUser();

        $status = $dispatch->getStatut();
        $type = $dispatch->getType();
        $carrier = $dispatch->getCarrier();
        $carrierTrackingNumber = $dispatch->getCarrierTrackingNumber();
        $commandNumber = $dispatch->getCommandNumber();
        $requester = $dispatch->getRequester();
        $receivers = $dispatch->getReceivers();
        $locationFrom = $dispatch->getLocationFrom();
        $locationTo = $dispatch->getLocationTo();
        $creationDate = $dispatch->getCreationDate();
        $validationDate = $dispatch->getValidationDate() ?: null;
        $treatmentDate = $dispatch->getTreatmentDate() ?: null;
        $startDate = $dispatch->getStartDate();
        $endDate = $dispatch->getEndDate();
        $startDateStr = $this->formatService->date($startDate, "", $user);
        $endDateStr = $this->formatService->date($endDate, "", $user);
        $projectNumber = $dispatch->getProjectNumber();
        $comment = $dispatch->getCommentaire() ?? '';
        $treatedBy = $dispatch->getTreatedBy() ? $dispatch->getTreatedBy()->getUsername() : '';
        $attachments = $dispatch->getAttachments();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $dispatch,
            ['type' => $dispatch->getType()],
            $this->security->getUser()
        );
        $receiverDetails = [
            "label" => $this->translationService->translate('Demande', 'Général', 'Destinataire(s)', false),
            "value" => "",
            "isRaw" => true
        ];

        foreach ($receivers as $receiver) {
            $receiverLine = "<div>";

            $receiverLine .= $receiver ? $receiver->getUsername() : "";
            if ($receiver && $receiver->getAddress()) {
                $receiverLine .= '
                    <span class="pl-2"
                          data-toggle="popover"
                          data-trigger="click hover"
                          title="Adresse du destinataire"
                          data-content="' . htmlspecialchars($receiver->getAddress()) . '">
                        <i class="fas fa-search"></i>
                    </span>';
            }
            $receiverLine .= '</div>';
            $receiverDetails['value'] .= $receiverLine;
        }

        $config = [
            [
                'label' => $this->translationService->translate('Demande', 'Général', 'Statut', false),
                'value' => $status ? $this->formatService->status($status) : ''
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Général', 'Type', false),
                'value' => $this->formatService->type($type),
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Transporteur', false),
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° tracking transporteur', false),
                'value' => $carrierTrackingNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Général', 'Demandeur', false),
                'value' => $requester ? $requester->getUsername() : ''
            ],
            $receiverDetails ?? [],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'N° projet', false),
                'value' => $projectNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_PROJECT_NUMBER]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Business unit', false),
                'value' => $dispatch->getBusinessUnit() ?? '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_BUSINESS_UNIT]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false),
                'value' => $commandNumber,
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de prise', false),
                'value' => $locationFrom ? $locationFrom->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_PICK]
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de dépose', false),
                'value' => $locationTo ? $locationTo->getLabel() : '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_LOCATION_DROP]
            ],
            [
                'label' => $this->translationService->translate('Général', null, 'Zone liste', 'Date de création', false),
                'value' => $this->formatService->datetime($creationDate, "", $user)
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de validation', false),
                'value' => $this->formatService->datetime($validationDate, "", $user)
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', "Dates d'échéance", false),
                'value' => ($startDate || $endDate)
                    ? $this->translationService->translate('Général', null, 'Zone liste', "Du {1} au {2}", [
                        1 => $startDateStr,
                        2 => $endDateStr
                    ], false)
                    : ''
            ],
            [
                'label' => $this->translationService->translate('Général', null, 'Zone liste', 'Traité par', false),
                'value' => $treatedBy
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Général', 'Date de traitement', false),
                'value' => $this->formatService->datetime($treatmentDate, "", false, $this->security->getUser())
            ],
            [
                'label' => $this->translationService->translate('Demande', 'Acheminements', 'Champs fixes', 'Destination', false),
                'value' => $dispatch->getDestination() ?: '',
                'show' => ['fieldName' => FieldsParam::FIELD_CODE_DESTINATION]
            ],
        ];
        $configFiltered = $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_DISPATCH);
        return array_merge(
            $configFiltered,
            $freeFieldArray,
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'comment', 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'comment', 'displayedEdit'))
                ? [[
                'label' => $this->translationService->translate('Général', null, 'Modale', "Commentaire"),
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'attachments', 'displayedCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachments', 'displayedEdit'))
                ? [[
                'label' => $this->translationService->translate('Général', null, 'Modale', 'Pièces jointes', false),
                'value' => $attachments->toArray(),
                'isAttachments' => true,
                'isNeededNotEmpty' => true
            ]]
                : []
        );
    }
}
