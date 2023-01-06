<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\FiltreSup;
use App\Entity\Language;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\Reception;
use App\Entity\TrackingMovement;
use App\Entity\Nature;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Repository\PackRepository;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class PackService {

    #[Required]
    public ProjectHistoryRecordService $projectHistoryRecordService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public Security $security;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public ArrivageService $arrivageDataService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public ReceptionService $receptionService;

    #[Required]
    public PDFGeneratorService $PDFGeneratorService;

    #[Required]
    public ReceptionLineService $receptionLineService;

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);

        $filters = $params->get("codeUl") ? [["field"=> "UL", "value"=> $params->get("codeUl")]] : $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->security->getUser()->getLanguage() ?: $defaultLanguage;
        $queryResult = $packRepository->findByParamsAndFilters($params, $filters, PackRepository::PACKS_MODE, [
            'defaultLanguage' => $defaultLanguage,
            'language' => $language
        ]);

        $packs = $queryResult["data"];

        $rows = [];
        foreach ($packs as $pack) {
            $rows[] = $this->dataRowPack($pack);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['count'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    public function getGroupHistoryForDatatable($pack, $params) {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $queryResult = $trackingMovementRepository->findTrackingMovementsForGroupHistory($pack, $params);

        $trackingMovements = $queryResult["data"];

        $rows = [];
        foreach ($trackingMovements as $trackingMovement) {
            $rows[] = $this->dataRowGroupHistory($trackingMovement);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult['filtered'],
            "recordsTotal" => $queryResult['total'],
        ];
    }

    public function dataRowPack(Pack $pack)
    {
        $firstMovement = $pack->getTrackingMovements('ASC')->first();
        $fromColumnData = $this->trackingMovementService->getFromColumnData($firstMovement ?: null);
        $user = $this->security->getUser();
        $prefix = $user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y';
        $lastMessage = $pack->getLastMessage();
        $hasPairing = !$pack->getPairings()->isEmpty() || $lastMessage;
        $sensorCode = ($lastMessage && $lastMessage->getSensor() && $lastMessage->getSensor()->getAvailableSensorWrapper())
            ? $lastMessage->getSensor()->getAvailableSensorWrapper()->getName()
            : null;

        /** @var TrackingMovement $lastPackMovement */
        $lastPackMovement = $pack->getLastTracking();
        return [
            'actions' => $this->templating->render('pack/datatablePackRow.html.twig', [
                'pack' => $pack,
                'hasPairing' => $hasPairing
            ]),
            'cart' => $this->templating->render('pack/cart-column.html.twig', [
                'pack' => $pack,
            ]),
            'pairing' => $this->templating->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            'packNum' => $this->templating->render("pack/logisticUnitColumn.html.twig", [
                "pack" => $pack,
            ]),
            'packNature' => $this->formatService->nature($pack->getNature()),
            'quantity' => $pack->getQuantity() ?: 1,
            'project' => $pack->getProject()?->getCode(),
            'packLastDate' => $lastPackMovement
                ? ($lastPackMovement->getDatetime()
                    ? $lastPackMovement->getDatetime()->format($prefix . ' \à H:i:s')
                    : '')
                : '',
            'packOrigin' => $this->templating->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', $fromColumnData),
            'packLocation' => $lastPackMovement
                ? ($lastPackMovement->getEmplacement()
                    ? $lastPackMovement->getEmplacement()->getLabel()
                    : '')
                : ''
        ];
    }

    public function dataRowGroupHistory(TrackingMovement $trackingMovement) {
        return [
            'group' => $trackingMovement->getPackParent() ? ($this->formatService->pack($trackingMovement->getPackParent()) . '-' . $trackingMovement->getGroupIteration()) : '',
            'date' => $this->formatService->datetime($trackingMovement->getDatetime(), "", false, $this->security->getUser()),
            'type' => $this->formatService->status($trackingMovement->getType())
        ];
    }

    public function checkPackDataBeforeEdition(array $data): array
    {
        $quantity = $data['quantity'] ?? null;
        $weight = !empty($data['weight']) ? str_replace(",", ".", $data['weight']) : null;
        $volume = !empty($data['volume']) ? str_replace(",", ".", $data['volume']) : null;

        if ($quantity <= 0) {
            return [
                'success' => false,
                'msg' => 'La quantité doit être supérieure à 0.'
            ];
        }

        if (!empty($weight) && (!is_numeric($weight) || ((float)$weight) <= 0)) {
            return [
                'success' => false,
                'msg' => 'Le poids doit être un nombre valide supérieur à 0.'
            ];
        }

        if (!empty($volume) && (!is_numeric($volume) || ((float)$volume) <= 0)) {
            return [
                'success' => false,
                'msg' => 'Le volume doit être un nombre valide supérieur à 0.'
            ];
        }

        return [
            'success' => true,
            'msg' => 'OK',
        ];
    }

    public function editPack(EntityManagerInterface $entityManager,
                             array                  $data,
                             Pack                   $pack): void {
        $natureRepository = $entityManager->getRepository(Nature::class);
        $projectRepository = $entityManager->getRepository(Project::class);

        $natureId = $data['nature'] ?? null;
        $projectId = $data['projects'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $comment = $data['comment'] ?? null;
        $weight = !empty($data['weight']) ? str_replace(",", ".", $data['weight']) : null;
        $volume = !empty($data['volume']) ? str_replace(",", ".", $data['volume']) : null;

        $nature = $natureRepository->find($natureId);
        if (!empty($nature)) {
            $pack->setNature($nature);
        }

        $project = $projectRepository->findOneBy(["id" => $projectId]);

        $recordDate = new DateTime();
        $this->projectHistoryRecordService->changeProject($entityManager, $pack, $project, $recordDate);

        foreach($pack->getChildArticles() as $article) {
            $this->projectHistoryRecordService->changeProject($entityManager, $article, $project, $recordDate);
        }

        $pack
            ->setQuantity($quantity)
            ->setWeight($weight)
            ->setVolume($volume)
            ->setComment(StringHelper::cleanedComment($comment));
    }

    public function createPack(EntityManager $entityManager, array $options = []): Pack
    {
        if (!empty($options['code'])) {
            $pack = $this->createPackWithCode($options['code']);
        } else {
            /** @var ?Project $project */
            $project = $options['project'] ?? null;

            if(isset($options['arrival'])) {
                /** @var Arrivage $arrival */
                $arrival = $options['arrival'];

                /** @var Nature $nature */
                $nature = $options['nature'];

                $arrivalNum = $arrival->getNumeroArrivage();
                $counter = $this->getNextPackCodeForArrival($arrival) + 1;
                $counterStr = sprintf("%03u", $counter);

                $code = (($nature->getPrefix() ?? '') . $arrivalNum . $counterStr ?? '');
                $pack = $this
                    ->createPackWithCode($code)
                    ->setNature($nature);
                if (isset($options['reception'])) {
                    /** @var Reception $reception */
                    $reception = $options['reception'];
                    $this->receptionLineService->persistReceptionLine($entityManager, $reception, $pack);
                }

                $arrival->addPack($pack);
            }
            else if (isset($options['orderLine'])) {
                /** @var Nature $nature */
                $nature = $options['nature'];

                /** @var TransportDeliveryOrderPack $orderLine */
                $orderLine = $options['orderLine'];
                $order = $orderLine->getOrder();
                $request = $order->getRequest();

                $requestNumber = $request->getNumber();
                $naturePrefix = $nature->getPrefix() ?? '';
                $counter = $order->getPacks()->count();
                $counterStr = sprintf("%03u", $counter);

                $code = $naturePrefix . $requestNumber . $counterStr;
                $pack = $this
                    ->createPackWithCode($code)
                    ->setNature($nature);

                $orderLine->setPack($pack);
            }
            else {
                throw new RuntimeException('Unhandled pack configuration');
            }

            if ($project) {
                $recordDate = new DateTime();
                $this->projectHistoryRecordService->changeProject($entityManager, $pack, $project, $recordDate);

                foreach($pack->getChildArticles() as $article) {
                    $this->projectHistoryRecordService->changeProject($entityManager, $article, $project, $recordDate);
                }
            }
        }
        return $pack;
    }

    public function createPackWithCode(string $code): Pack
    {
        $pack = new Pack();
        $pack->setCode(str_replace("    ", " ", $code));
        return $pack;
    }

    public function persistPack(EntityManagerInterface $entityManager,
                                                       $packOrCode,
                                                       $quantity,
                                                       $natureId = null,
                                bool                   $onlyPack = false): Pack {
        $packRepository = $entityManager->getRepository(Pack::class);

        $codePack = $packOrCode instanceof Pack ? $packOrCode->getCode() : $packOrCode;

        $pack = ($packOrCode instanceof Pack)
            ? $packOrCode
            : $packRepository->findOneBy(['code' => $packOrCode]);

        if ($onlyPack && $pack && $pack->isGroup()) {
            throw new Exception(Pack::PACK_IS_GROUP);
        }

        if (!isset($pack)) {
            $pack = $this->createPackWithCode($codePack);
            $pack->setQuantity($quantity);
            $entityManager->persist($pack);
        }

        if (!empty($natureId)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $nature = $natureRepository->find($natureId);

            if (!empty($nature)) {
                $pack->setNature($nature);
            }
        }

        return $pack;
    }

    public function persistMultiPacks(EntityManagerInterface $entityManager,
                                      Arrivage               $arrivage,
                                      array                  $packByNatures,
                                                             $user,
                                      bool                   $persistTrackingMovements = true,
                                      Project                $project = null,
                                      Reception              $reception = null): array
    {
        $natureRepository = $entityManager->getRepository(Nature::class);

        $location = $persistTrackingMovements
            ? $this->arrivageDataService->getLocationForTracking($entityManager, $arrivage)
            : null;

        $totalPacks = Stream::from($packByNatures)->sum();
        if($totalPacks > 500) {
            throw new FormException("Vous ne pouvez pas ajouter plus de 500 UL");
        }

        $now = new DateTime('now');
        $createdPacks = [];
        foreach ($packByNatures as $natureId => $number) {
            $nature = $natureRepository->find($natureId);
            for ($i = 0; $i < $number; $i++) {
                $pack = $this->createPack($entityManager, ['arrival' => $arrivage, 'nature' => $nature, 'project' => $project, 'reception' => $reception]);
                if ($persistTrackingMovements && isset($location)) {
                    $this->trackingMovementService->persistTrackingForArrivalPack(
                        $entityManager,
                        $pack,
                        $location,
                        $user,
                        $now,
                        $arrivage
                    );
                }
                $entityManager->persist($pack);
                $createdPacks[] = $pack;
            }
        }
        return $createdPacks;
    }

    public function launchPackDeliveryReminder(EntityManagerInterface $entityManager): void {
        $packRepository = $entityManager->getRepository(Pack::class);
        $waitingDaysRequested = [7, 15, 30, 42];
        $ongoingPacks = $packRepository->findOngoingPacksOnDeliveryPoints($waitingDaysRequested);
        foreach ($ongoingPacks as $packData) {
            $pack = $packData[0];
            $waitingDays = $packData['packWaitingDays'];

            $remindPosition = array_search($waitingDays, $waitingDaysRequested);
            $titleSuffix = match($remindPosition) {
                0 => ' - 1ère relance',
                1 => ' - 2ème relance',
                2 => ' - 3ème relance',
                3 => ' - dernière relance',
                default => ''
            };
            $arrival = $pack->getArrivage();
            $lastDrop = $pack->getLastDrop();

            $this->mailerService->sendMail(
                "Follow GT // Unité logistique non récupéré$titleSuffix",
                $this->templating->render('mails/contents/mailPackDeliveryDone.html.twig', [
                    'title' => 'Votre unité logistique est toujours présente dans votre magasin',
                    'orderNumber' => implode(', ', $arrival->getNumeroCommandeList()),
                    'pack' => $this->formatService->pack($pack),
                    'emplacement' => $lastDrop->getEmplacement(),
                    'date' => $lastDrop->getDatetime(),
                    'fournisseur' => $this->formatService->supplier($arrival->getFournisseur()),
                    'pjs' => $arrival->getAttachments()
                ]),
                $arrival->getDestinataire()
            );
        }
    }

    public function getNextPackCodeForArrival(Arrivage $arrival): int {
        $lastPack = $arrival->getPacks()->last();

        $counter = 0;
        if($lastPack) {
            $counter = (int) substr($lastPack->getCode(), -3);
        }

        return $counter;
    }

    public function getColumnVisibleConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['arrivalPack'];
        return $this->visibleColumnService->getArrayConfig(
            [
                ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true, "searchable" => true],
                ["name" => 'nature', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Nature'), "searchable" => true],
                ["name" => 'code', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Unités logistiques'), "searchable" => true],
                ["name" => 'project', 'title' => 'Projet', "searchable" => true],
                ["name" => 'lastMvtDate', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Date dernier mouvement'), "searchable" => true],
                ["name" => 'lastLocation', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Dernier emplacement'), "searchable" => true],
                ["name" => 'operator', 'title' => $this->translation->translate('Traçabilité', 'Général', 'Opérateur'), "searchable" => true],
            ],
            [],
            $columnsVisible
        );
    }

    public function updateArticlePack(EntityManagerInterface $entityManager,
                                      Article                $article): Pack {
        $trackingPack = $article->getTrackingPack();
        if(!isset($trackingPack)) {
            $packRepository = $entityManager->getRepository(Pack::class);
            $trackingPack = $packRepository->findOneBy(["code" => $article->getBarCode()])
                ?? $this->persistPack($entityManager, $article->getBarCode(), $article->getQuantite());
            $article->setTrackingPack($trackingPack);
        }
        return $trackingPack;
    }

    public function getBarcodePackConfig(Pack         $pack,
                                         ?Utilisateur $destinataire = null,
                                         ?string      $packIndex = '',
                                         ?bool        $typeArrivalParamIsDefined = false,
                                         ?bool        $usernameParamIsDefined = false,
                                         ?bool        $dropzoneParamIsDefined = false,
                                         ?bool        $packCountParamIsDefined = false,
                                         ?bool        $commandAndProjectNumberIsDefined = false,
                                         ?array       $firstCustomIconConfig = null,
                                         ?array       $secondCustomIconConfig = null,
                                         ?bool        $businessUnitParam = false,
                                         ?bool $projectParam = false,
    ): array {

        $arrival = $pack->getArrivage();

        $businessUnit = $businessUnitParam
            ? $arrival->getBusinessUnit()
            : '';

        $project = $projectParam
            ? $pack->getProject()?->getCode()
            : '';

        $arrivalType = $typeArrivalParamIsDefined
            ? $this->formatService->type($arrival->getType())
            : '';

        $recipientUsername = ($usernameParamIsDefined && $destinataire)
            ? $destinataire->getUsername()
            : '';

        $dropZoneLabel = ($dropzoneParamIsDefined && $destinataire)
            ? ($destinataire->getDropzone()
                ? $destinataire->getDropzone()->getLabel()
                : '')
            : '';

        $arrivalCommand = [];
        $arrivalLine = "";
        $i = 0;
        foreach($arrival?->getNumeroCommandeList() ?? [] as $command) {
            $arrivalLine .= $command;

            if(++$i % 4 == 0) {
                $arrivalCommand[] = $arrivalLine;
                $arrivalLine = "";
            } else {
                $arrivalLine .= " ";
            }
        }

        if(!empty($arrivalLine)) {
            $arrivalCommand[] = $arrivalLine;
        }

        $arrivalProjectNumber = $arrival
            ? ($arrival->getProjectNumber() ?? '')
            : '';

        $packLabel = ($packCountParamIsDefined ? $packIndex : '');

        $usernameSeparator = ($recipientUsername && $dropZoneLabel) ? ' / ' : '';

        $labels = [$arrivalType];

        $labels[] = $recipientUsername . $usernameSeparator . $dropZoneLabel;

        if ($commandAndProjectNumberIsDefined) {
            if ($arrivalCommand && $arrivalProjectNumber) {
                if(count($arrivalCommand) > 1) {
                    $labels = array_merge($labels, $arrivalCommand);
                    $labels[] = $arrivalProjectNumber;
                } else if(count($arrivalCommand) == 1) {
                    $labels[] = $arrivalCommand[0] . ' / ' . $arrivalProjectNumber;
                }
            } else if ($arrivalCommand) {
                $labels = array_merge($labels, $arrivalCommand);
            } else if ($arrivalProjectNumber) {
                $labels[] = $arrivalProjectNumber;
            }
        }

        if($businessUnitParam) {
            $labels[] = $businessUnit;
        }

        if($projectParam) {
            $labels[] = $project;
        }

        if ($packLabel) {
            $labels[] = $packLabel;
        }

        return [
            'code' => $pack->getCode(),
            'labels' => $labels,
            'firstCustomIcon' => $arrival?->getCustoms() ? $firstCustomIconConfig : null,
            'secondCustomIcon' => $arrival?->getIsUrgent() ? $secondCustomIconConfig : null
        ];
    }
}
