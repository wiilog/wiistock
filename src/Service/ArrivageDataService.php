<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\ParametrageGlobal;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class ArrivageDataService
{
    private $templating;
    private $router;
    private $userService;
    private $security;
    private $mailerService;
    private $entityManager;
    private $specificService;
    private $stringService;
    private $translator;
    private $freeFieldService;
    private $fieldsParamService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                MailerService $mailerService,
                                SpecificService $specificService,
                                StringService $stringService,
                                FreeFieldService $champLibreService,
                                FieldsParamService $fieldsParamService,
                                TranslatorInterface $translator,
                                Twig_Environment $templating,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {

        $this->templating = $templating;
        $this->freeFieldService = $champLibreService;
        $this->fieldsParamService = $fieldsParamService;
        $this->translator = $translator;
        $this->stringService = $stringService;
        $this->stringService = $stringService;
        $this->router = $router;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->security = $security;
        $this->mailerService = $mailerService;
        $this->specificService = $specificService;
    }

    /**
     * @param array $params
     * @param int|null $userId
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForDatatable($params, $userId)
    {
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $supFilterRepository = $this->entityManager->getRepository(FiltreSup::class);
        $categorieCLRepository = $this->entityManager->getRepository(CategorieCL::class);
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);

        $filters = $supFilterRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARRIVAGE, $this->security->getUser());

        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::ARRIVAGE);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARRIVAGE, $categorieCL);


        $queryResult = $arrivalRepository->findByParamsAndFilters(
            $params,
            $filters,
            $userId,
            array_reduce($freeFields, function (array $accumulator, array $freeField) {
                $accumulator[trim(mb_strtolower($freeField['label']))] = $freeField['id'];
                return $accumulator;
            }, [])
        );

        $arrivals = $queryResult['data'];

        $rows = [];
        foreach ($arrivals as $arrival) {
            $rows[] = $this->dataRowArrivage(is_array($arrival) ? $arrival[0] : $arrival);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Arrivage $arrival
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowArrivage($arrival)
    {
        $url = $this->router->generate('arrivage_show', [
            'id' => $arrival->getId(),
        ]);
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $categoryFFRepository = $this->entityManager->getRepository(CategorieCL::class);
        $freeFieldsRepository = $this->entityManager->getRepository(ChampLibre::class);
        $categoryFF = $categoryFFRepository->findOneByLabel(CategorieCL::ARRIVAGE);

        $category = CategoryType::ARRIVAGE;
        $freeFields = $freeFieldsRepository->getByCategoryTypeAndCategoryCL($category, $categoryFF);

        $rowCL = [];
        /** @var ChampLibre $freeField */
        foreach ($freeFields as $freeField) {
            $rowCL[$freeField['label']] = $this->freeFieldService->formatValeurChampLibreForDatatable([
                'valeur' => $arrival->getFreeFieldValue($freeField['id']),
                "typage" => $freeField['typage'],
            ]);
        }

        $acheteursUsernames = [];
        foreach ($arrival->getAcheteurs() as $acheteur) {
            $acheteursUsernames[] = $acheteur->getUsername();
        }

        $row = [
            'id' => $arrival->getId(),
            'arrivalNumber' => $arrival->getNumeroArrivage() ?? '',
            'carrier' => $arrival->getTransporteur() ? $arrival->getTransporteur()->getLabel() : '',
            'driver' => $arrival->getChauffeur() ? $arrival->getChauffeur()->getPrenomNom() : '',
            'trackingCarrierNumber' => $arrival->getNoTracking() ?? '',
            'orderNumber' => implode(',', $arrival->getNumeroCommandeList()),
            'type' => $arrival->getType() ? $arrival->getType()->getLabel() : '',
            'nbUm' => $arrivalRepository->countColisByArrivage($arrival),
            'custom' => $arrival->getDuty() ? 'oui' : 'non',
            'frozen' => $arrival->getFrozen() ? 'oui' : 'non',
            'provider' => $arrival->getFournisseur() ? $arrival->getFournisseur()->getNom() : '',
            'receiver' => $arrival->getDestinataire() ? $arrival->getDestinataire()->getUsername() : '',
            'buyers' => implode(', ', $acheteursUsernames),
            'status' => $arrival->getStatut() ? $arrival->getStatut()->getNom() : '',
            'date' => $arrival->getDate() ? $arrival->getDate()->format('d/m/Y H:i:s') : '',
            'user' => $arrival->getUtilisateur() ? $arrival->getUtilisateur()->getUsername() : '',
            'emergency' => $arrival->getIsUrgent() ? 'oui' : 'non',
            'projectNumber' => $arrival->getProjectNumber() ?? '',
            'businessUnit' => $arrival->getBusinessUnit() ?? '',
            'url' => $url,
            'actions' => $this->templating->render(
                'arrivage/datatableArrivageRow.html.twig',
                ['url' => $url, 'arrivage' => $arrival]
            )
        ];

        $rows = array_merge($rowCL, $row);
        return $rows;
    }

    /**
     * @param Arrivage $arrival
     * @param Urgence[] $emergencies
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendArrivalEmails(Arrivage $arrival,
                                      array $emergencies = []): void
    {

        $isUrgentArrival = !empty($emergencies);
        $finalRecipents = [];
        if ($isUrgentArrival) {
            $finalRecipents = array_reduce(
                $emergencies,
                function (array $carry, Urgence $emergency) {
                    $emails = $emergency->getBuyer()->getMainAndSecondaryEmails();
                    foreach ($emails as $email) {
                        if (!in_array($email, $carry)) {
                            $carry[] = $email;
                        }
                    }
                    return $carry;
                },
                []
            );
        } else if ($arrival->getDestinataire()) {
            $recipient = $arrival->getDestinataire();
            $finalRecipents = $recipient ? $recipient->getMainAndSecondaryEmails() : [];
        }

        if (!empty($finalRecipents)) {
            $title = 'Arrivage reçu : ' . $arrival->getNumeroArrivage() . ', le ' . $arrival->getDate()->format('d/m/Y à H:i');

            $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                $this->entityManager,
                $arrival,
                null,
                CategoryType::ARRIVAGE
            );

            $this->mailerService->sendMail(
                'FOLLOW GT // Arrivage' . ($isUrgentArrival ? ' urgent' : ''),
                $this->templating->render(
                    'mails/contents/mailArrivage.html.twig',
                    [
                        'title' => $title,
                        'arrival' => $arrival,
                        'emergencies' => $emergencies,
                        'isUrgentArrival' => $isUrgentArrival,
                        'freeFields' => $freeFields
                    ]
                ),
                $finalRecipents
            );
        }
    }

    /**
     * @param Arrivage $arrivage
     * @param Urgence[] $emergencies
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function setArrivalUrgent(Arrivage $arrivage, array $emergencies): void
    {
        if (!empty($emergencies)) {
            $arrivage->setIsUrgent(true);
            foreach ($emergencies as $emergency) {
                $emergency->setLastArrival($arrivage);
            }
            $this->sendArrivalEmails($arrivage, $emergencies);
        }
    }

    /**
     * @param Arrivage $arrivage
     * @param bool $askQuestion
     * @param Urgence[] $urgences
     * @return array
     */
    public function createArrivalAlertConfig(Arrivage $arrivage,
                                             bool $askQuestion,
                                             array $urgences = []): array
    {
        $isArrivalUrgent = count($urgences);

        if ($askQuestion && $isArrivalUrgent) {
            $numeroCommande = $urgences[0]->getCommande();
            $postNb = $urgences[0]->getPostNb();

            $posts = array_map(
                function (Urgence $urgence) {
                    return $urgence->getPostNb();
                },
                $urgences
            );

            $nbPosts = count($posts);

            if ($nbPosts == 0) {
                $msgSedUrgent = "L'arrivage est-il urgent sur la commande $numeroCommande ?";
            }
            else {
                if ($nbPosts == 1) {
                    $msgSedUrgent = "
                        Le poste <span class='bold'>" . $posts[0] . "</span> est urgent sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    L'avez-vous reçu dans cet arrivage ?
					";
                }
                else {
                    $postsStr = implode(', ', $posts);
                    $msgSedUrgent = "
                        Les postes <span class=\"bold\">$postsStr</span> sont urgents sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    Les avez-vous reçus dans cet arrivage ?
                    ";
                }
            }
        }
        else {
            $numeroCommande = null;
            $postNb = null;
        }
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);

        return [
            'autoHide' => (!$askQuestion && !$isArrivalUrgent),
            'message' => ($isArrivalUrgent
                ? (!$askQuestion
                    ? 'Arrivage URGENT enregistré avec succès.'
                    : ($msgSedUrgent ?? ''))
                : 'Arrivage enregistré avec succès.'),
            'iconType' => $isArrivalUrgent ? 'warning' : 'success',
            'modalType' => ($askQuestion && $isArrivalUrgent) ? 'yes-no-question' : 'info',
            'autoPrint' => !$parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL),
            'emergencyAlert' => $isArrivalUrgent,
            'numeroCommande' => $numeroCommande,
            'postNb' => $postNb,
            'arrivalId' => $arrivage->getId()
        ];
    }

    /**
     * @param Arrivage $arrival
     * @return array List of alertConfig to display to the client
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function processEmergenciesOnArrival(Arrivage $arrival): array
    {
        $numeroCommandeList = $arrival->getNumeroCommandeList();
        $alertConfigs = [];
        $isSEDCurrentClient = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

        if (!empty($numeroCommandeList)) {
            $urgenceRepository = $this->entityManager->getRepository(Urgence::class);

            foreach ($numeroCommandeList as $numeroCommande) {
                $urgencesMatching = $urgenceRepository->findUrgencesMatching(
                    $arrival->getDate(),
                    $arrival->getFournisseur(),
                    $numeroCommande,
                    null,
                    $isSEDCurrentClient
                );

                if (!empty($urgencesMatching)) {
                    if (!$isSEDCurrentClient) {
                        $this->setArrivalUrgent($arrival, $urgencesMatching);
                    } else {
                        $currentAlertConfig = array_map(function (Urgence $urgence) use ($arrival, $isSEDCurrentClient) {
                            return $this->createArrivalAlertConfig(
                                $arrival,
                                $isSEDCurrentClient,
                                [$urgence]
                            );
                        }, $urgencesMatching);
                        array_push($alertConfigs, ...$currentAlertConfig);
                    }
                }
            }
        }

        if (empty($alertConfigs) || !$isSEDCurrentClient) {
            $alertConfigs[] = $this->createArrivalAlertConfig($arrival, $isSEDCurrentClient);
        }

        return $alertConfigs;
    }

    public function createHeaderDetailsConfig(Arrivage $arrivage): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        $provider = $arrivage->getFournisseur();
        $carrier = $arrivage->getTransporteur();
        $driver = $arrivage->getChauffeur();
        $numeroCommandeList = $arrivage->getNumeroCommandeList();
        $status = $arrivage->getStatut();
        $type = $arrivage->getType();
        $destinataire = $arrivage->getDestinataire();
        $buyers = $arrivage->getAcheteurs();
        $comment = $arrivage->getCommentaire();
        $attachments = $arrivage->getAttachments();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $arrivage,
            null,
            CategoryType::ARRIVAGE
        );

        $config = [
            [
                'label' => 'Type',
                'value' => $type ? $this->stringService->mbUcfirst($type->getLabel()) : ''
            ],
            [
                'label' => 'Statut',
                'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : ''
            ],
            [
                'label' => 'Fournisseur',
                'value' => $provider ? $provider->getNom() : ''
            ],
            [
                'label' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : ''
            ],
            [
                'label' => 'Chauffeur',
                'value' => $driver ? $driver->getNom() : ''
            ],
            [
                'label' => 'N° tracking transporteur',
                'value' => $arrivage->getNoTracking()
            ],
            [
                'label' => 'N° commandes / BL',
                'value' => !empty($numeroCommandeList) ? implode(', ', $numeroCommandeList) : ''
            ],
            [
                'label' => $this->translator->trans('arrivage.destinataire'),
                'labelTitle' => 'destinataire',
                'value' => $destinataire ? $destinataire->getUsername() : ''
            ],
            [
                'label' => $this->translator->trans('arrivage.acheteurs'),
                'labelTitle' => 'acheteurs',
                'value' => $buyers->count() > 0 ? implode(', ', $buyers->map(function (Utilisateur $buyer) {return $buyer->getUsername();})->toArray()) : ''
            ],
            [
                'label' => 'Numéro de projet',
                'value' => $arrivage->getProjectNumber()
            ],
            [
                'label' => 'Business unit',
                'value' => $arrivage->getBusinessUnit()
            ],
            [
                'label' => $this->translator->trans('arrivage.douane'),
                'labelTitle' => 'douane',
                'value' => $arrivage->getDuty() ? 'oui' : 'non'
            ],
            [
                'label' => $this->translator->trans('arrivage.congelé'),
                'labelTitle' => 'congelé',
                'value' => $arrivage->getFrozen() ? 'oui' : 'non'
            ]
        ];

        $configFiltered =  array_filter($config, function ($fieldConfig) use ($fieldsParam) {
            return (
                !isset($fieldConfig['show'])
                || $this->fieldsParamService->isFieldRequired($fieldsParam, $fieldConfig['show']['fieldName'], $fieldConfig['show']['action'])
            );
        });

        return array_merge(
            $configFiltered,
            $freeFieldArray,
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayed')
                ? [[
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'pj', 'displayed')
                ? [[
                    'label' => 'Pièces jointes',
                    'value' => $attachments->toArray(),
                    'isAttachments' => true,
                    'isNeededNotEmpty' => true
                ]]
                : []
        );
    }

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur $currentUser): array {


        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $columnsVisible = $currentUser->getColumnsVisibleForArrivage();
        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::ARRIVAGE);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARRIVAGE, $categorieCL);

        $columns = [
            ['title' => 'Actions', 'name' => 'actions', 'class' => 'display', 'alwaysVisible' => true],
            ['title' => 'Date', 'name' => 'date'],
            ['title' => 'arrivage.n° d\'arrivage',  'name' => 'arrivalNumber', 'translated' => true],
            ['title' => 'Transporteur', 'name' => 'carrier'],
            ['title' => 'Chauffeur', 'name' => 'driver'],
            ['title' => 'N° tracking transporteur', 'name' => 'trackingCarrierNumber'],
            ['title' => 'N° commande / bl', 'name' => 'orderNumber'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Fournisseur', 'name' => 'provider'],
            ['title' => 'arrivage.destinataire', 'name' => 'receiver', 'translated' => true],
            ['title' => 'arrivage.acheteurs', 'name' => 'buyers', 'translated' => true],
            ['title' => 'Nb um', 'name' => 'nbUm'],
            ['title' => 'Douane', 'name' => 'custom'],
            ['title' => 'Congelé', 'name' => 'frozen'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Utilisateur', 'name' => 'user'],
            ['title' => 'Urgent', 'name' => 'emergency'],
            ['title' => 'Numéro de projet', 'name' => 'projectNumber'],
            ['title' => 'Business Unit', 'name' => 'businessUnit'],
        ];

        return array_merge(
            array_map(function (array $column) use ($columnsVisible) {
                return [
                    'title' => $column['title'],
                    'alwaysVisible' => $column['alwaysVisible'] ?? false,
                    'data' => $column['name'],
                    'name' => $column['name'],
                    'translated' => $column['translated'] ?? false,
                    'class' => $column['class'] ?? (in_array($column['name'], $columnsVisible) ? 'display' : 'hide')
                ];
            }, $columns),
            array_map(function (array $freeField) use ($columnsVisible) {
                return [
                    'title' => ucfirst(mb_strtolower($freeField['label'])),
                    'data' => $freeField['label'],
                    'name' => $freeField['label'],
                    'class' => (in_array($freeField['label'], $columnsVisible) ? 'display' : 'hide'),
                ];
            }, $freeFields)
        );
    }
}
