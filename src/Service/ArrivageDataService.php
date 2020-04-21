<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\ChampLibre;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\PieceJointe;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
    private $valeurChampLibreService;
    private $fieldsParamService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                MailerService $mailerService,
                                SpecificService $specificService,
                                StringService $stringService,
                                ValeurChampLibreService $valeurChampLibreService,
                                FieldsParamService $fieldsParamService,
                                TranslatorInterface $translator,
                                Twig_Environment $templating,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {

        $this->templating = $templating;
        $this->valeurChampLibreService = $valeurChampLibreService;
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
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForDatatable($params = null, $userId)
    {
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARRIVAGE, $this->security->getUser());

        $queryResult = $arrivageRepository->findByParamsAndFilters($params, $filters, $userId);

        $arrivages = $queryResult['data'];

        $rows = [];
        foreach ($arrivages as $arrivage) {
            $rows[] = $this->dataRowArrivage($arrivage);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Arrivage $arrivage
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function dataRowArrivage($arrivage)
    {
        $url = $this->router->generate('arrivage_show', [
            'id' => $arrivage->getId(),
        ]);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

        $acheteursUsernames = [];
        foreach ($arrivage->getAcheteurs() as $acheteur) {
            $acheteursUsernames[] = $acheteur->getUsername();
        }

        $row = [
            'id' => $arrivage->getId(),
            'NumeroArrivage' => $arrivage->getNumeroArrivage() ?? '',
            'Transporteur' => $arrivage->getTransporteur() ? $arrivage->getTransporteur()->getLabel() : '',
            'Chauffeur' => $arrivage->getChauffeur() ? $arrivage->getChauffeur()->getPrenomNom() : '',
            'NoTracking' => $arrivage->getNoTracking() ?? '',
            'NumeroCommandeList' => implode(',', $arrivage->getNumeroCommandeList()),
            'NbUM' => $arrivageRepository->countColisByArrivage($arrivage),
            'Duty' => $arrivage->getDuty() ? 'oui' : 'non',
            'Frozen' => $arrivage->getFrozen() ? 'oui' : 'non',
            'Fournisseur' => $arrivage->getFournisseur() ? $arrivage->getFournisseur()->getNom() : '',
            'Destinataire' => $arrivage->getDestinataire() ? $arrivage->getDestinataire()->getUsername() : '',
            'Acheteurs' => implode(', ', $acheteursUsernames),
            'Statut' => $arrivage->getStatut() ? $arrivage->getStatut()->getNom() : '',
            'Date' => $arrivage->getDate() ? $arrivage->getDate()->format('d/m/Y H:i:s') : '',
            'Utilisateur' => $arrivage->getUtilisateur() ? $arrivage->getUtilisateur()->getUsername() : '',
            'Urgent' => $arrivage->getIsUrgent() ? 'oui' : 'non',
            'url' => $url,
            'Actions' => $this->templating->render(
                'arrivage/datatableArrivageRow.html.twig',
                ['url' => $url, 'arrivage' => $arrivage]
            )
        ];

        return $row;
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
                    $email = $emergency->getBuyer()->getEmail();
                    if (!in_array($email, $carry)) {
                        $carry[] = $email;
                    }
                    return $carry;
                },
                []
            );
        } else if ($arrival->getDestinataire()) {
            $finalRecipents = [$arrival->getDestinataire()->getEmail()];
        }

        if (!empty($finalRecipents)) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Arrivage' . ($isUrgentArrival ? ' urgent' : ''),
                $this->templating->render(
                    'mails/mailArrivage.html.twig',
                    [
                        'title' => 'Arrivage ' . ($isUrgentArrival ? 'urgent ' : '') . 'reçu',
                        'arrival' => $arrival,
                        'emergencies' => $emergencies,
                        'isUrgentArrival' => $isUrgentArrival
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
            $this->entityManager->flush();
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

            $posts = array_map(
                function (Urgence $urgence) {
                    return $urgence->getPostNb();
                },
                $urgences
            );

            $nbPosts = count($posts);

            if ($nbPosts == 0) {
                $msgSedUrgent = "L'arrivage est-il urgent sur la commande $numeroCommande ?";
            } else {
                if ($nbPosts == 1) {
                    $msgSedUrgent = "
                        Le poste <span class='bold'>" . $posts[0] . "</span> est urgent sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    L'avez-vous reçu dans cet arrivage ?
					";
                } else {
                    $postsStr = implode(', ', $posts);
                    $msgSedUrgent = "
                        Les postes <span class=\"bold\">$postsStr</span> sont urgents sur la commande <span class=\"bold\">$numeroCommande</span>.<br/>
					    Les avez-vous reçus dans cet arrivage ?
                    ";
                }
            }
        } else {
            $numeroCommande = null;
        }

        return [
            'autoHide' => (!$askQuestion && !$isArrivalUrgent),
            'message' => ($isArrivalUrgent
                ? (!$askQuestion
                    ? 'Arrivage URGENT enregistré avec succès.'
                    : ($msgSedUrgent ?? ''))
                : 'Arrivage enregistré avec succès.'),
            'iconType' => $isArrivalUrgent ? 'warning' : 'success',
            'modalType' => ($askQuestion && $isArrivalUrgent) ? 'yes-no-question' : 'info',
            'emergencyAlert' => $isArrivalUrgent,
            'numeroCommande' => $numeroCommande,
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
                    $isSEDCurrentClient
                );

                if (!empty($urgencesMatching)) {
                    if (!$isSEDCurrentClient) {
                        $this->setArrivalUrgent($arrival, $urgencesMatching);
                    } else {
                        $alertConfigs[] = $this->createArrivalAlertConfig(
                            $arrival,
                            $isSEDCurrentClient,
                            $urgencesMatching
                        );
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
        $destinataire = $arrivage->getDestinataire();
        $buyers = $arrivage->getAcheteurs();
        $comment = $arrivage->getCommentaire();
        $attachments = $arrivage->getAttachements();

        $detailsChampLibres = $arrivage
            ->getValeurChampLibre()
            ->map(function (ValeurChampLibre $valeurChampLibre) {
                $champLibre = $valeurChampLibre->getChampLibre();
                $value = $this->valeurChampLibreService->formatValeurChampLibreForShow($valeurChampLibre);
                return [
                    'label' => $this->stringService->mbUcfirst($champLibre->getLabel()),
                    'value' => $value
                ];
            })
            ->toArray();

        $config = [
            [ 'label' => 'Statut', 'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : '' ],
            [
                'label' => 'Fournisseur',
                'value' => $provider ? $provider->getNom() : '',
                'show' => [ 'fieldName' => 'fournisseur', 'action' => 'displayed' ]
            ],
            [
                'label' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => [ 'fieldName' => 'transporteur', 'action' => 'displayed' ]
            ],
            [
                'label' => 'Chauffeur',
                'value' => $driver ? $driver->getNom() : '',
                'show' => [ 'fieldName' => 'chauffeur', 'action' => 'displayed' ]
            ],
            [
                'label' => 'N° tracking transporteur',
                'value' => $arrivage->getNoTracking(),
                'show' => [ 'fieldName' => 'noTracking', 'action' => 'displayed' ]
            ],
            [
                'label' => 'N° commandes / BL',
                'value' => !empty($numeroCommandeList) ? implode(', ', $numeroCommandeList) : '',
                'show' => [ 'fieldName' => 'numeroCommandeList', 'action' => 'displayed' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.destinataire'),
                'labelTitle' => 'destinataire',
                'value' => $destinataire ? $destinataire->getUsername() : '',
                'show' => [ 'fieldName' => 'destinataire', 'action' => 'displayed' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.acheteurs'),
                'labelTitle' => 'acheteurs',
                'value' => $buyers->count() > 0 ? implode(', ', $buyers->map(function (Utilisateur $buyer) {return $buyer->getUsername();})->toArray()) : '',
                'show' => [ 'fieldName' => 'acheteurs', 'action' => 'displayed' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.douane'),
                'labelTitle' => 'douane',
                'value' => $arrivage->getDuty() ? 'oui' : 'non',
                'show' => [ 'fieldName' => 'duty', 'action' => 'displayed' ]
            ],
            [
                'label' => $this->translator->trans('arrivage.congelé'),
                'labelTitle' => 'congelé',
                'value' => $arrivage->getFrozen() ? 'oui' : 'non',
                'show' => [ 'fieldName' => 'frozen', 'action' => 'displayed' ]
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
            $detailsChampLibres,
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayed')
                ? [[
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true
            ]]
                : [],
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'pj', 'displayed')
                ? [[
                    'label' => 'Pièces jointes',
                    'value' => $attachments->toArray(),
                    'isAttachments' => true
                ]]
                : []
        );
    }
}
