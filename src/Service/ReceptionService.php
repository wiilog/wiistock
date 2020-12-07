<?php


namespace App\Service;


use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Demande;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Reception;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ReceptionService
{
    private $templating;
    private $router;
    private $entityManager;
    private $fieldsParamService;
    private $stringService;
    private $translator;
    private $freeFieldService;
    private $uniqueNumberService;

    public function __construct(RouterInterface $router,
                                FieldsParamService $fieldsParamService,
                                StringService $stringService,
                                FreeFieldService $champLibreService,
                                TranslatorInterface $translator,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                UniqueNumberService $uniqueNumberService)
    {
        $this->templating = $templating;
        $this->freeFieldService = $champLibreService;
        $this->entityManager = $entityManager;
        $this->stringService = $stringService;
        $this->fieldsParamService = $fieldsParamService;
        $this->router = $router;
        $this->translator = $translator;
        $this->uniqueNumberService = $uniqueNumberService;
    }

    public function getDataForDatatable(Utilisateur $user, $params = null)
    {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receptionRepository = $this->entityManager->getRepository(Reception::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEPTION, $user);
        $queryResult = $receptionRepository->findByParamAndFilters($params, $filters);

        $receptions = $queryResult['data'];

        $rows = [];
        foreach ($receptions as $reception) {
            $rows[] = $this->dataRowReception($reception);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }


    /**
     * @param EntityManagerInterface $entityManager
     * @param Utilisateur|null $currentUser
     * @param array $data
     * @param bool $fromImport
     * @param $rowIndex
     * @return Reception
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function createAndPersistReception(EntityManagerInterface $entityManager,
                                              ?Utilisateur $currentUser,
                                              array $data,
                                              $fromImport = false): Reception {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $ransporteurRepository = $entityManager->getRepository(Transporteur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        if(!empty($data['anomalie'])) {
            $anomaly = filter_var($data['anomalie'], FILTER_VALIDATE_BOOLEAN);

            $statusCode = $anomaly
                ? Reception::STATUT_ANOMALIE
                : Reception::STATUT_EN_ATTENTE;
        } else {
            $statusCode = Reception::STATUT_EN_ATTENTE;
        }

        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);
        $type = $typeRepository->findOneByCategoryLabel(CategoryType::RECEPTION);

        $reception = new Reception();
        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

        $numero = $this->uniqueNumberService->createUniqueNumber($entityManager, Reception::PREFIX_NUMBER, Reception::class, UniqueNumberService::DATE_COUNTER_FORMAT_RECEPTION);

        if(!empty($data['fournisseur'])) {
            if($fromImport) {
                $fournisseur = $fournisseurRepository->findOneBy(['codeReference' => $data['fournisseur']]);
            } else {
                $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
            }
            $reception
                ->setFournisseur($fournisseur);
        }

        if(!empty($data['location'])) {
            if($fromImport) {
                $location = $emplacementRepository->findOneBy(['label' => $data['location']]);
            } else {
                $location = $emplacementRepository->find(intval($data['location']));
            }
            $reception
                ->setStorageLocation($location);
        }

        if(!empty($data['transporteur'])) {
            if($fromImport) {
                $transporteur = $ransporteurRepository->findOneBy(['code' => $data['transporteur']]);
            } else {
                $transporteur = $ransporteurRepository->find(intval($data['transporteur']));
            }
            $reception
                ->setTransporteur($transporteur);
        }

        if(!empty($data['storageLocation'])) {
            $storageLocation = $emplacementRepository->find(intval($data['storageLocation']));
            $reception->setStorageLocation($storageLocation);
        }

        if(!empty($data['manualUrgent'])) {
            $reception->setManualUrgent(filter_var($data['manualUrgent'], FILTER_VALIDATE_BOOLEAN));
        }

        $reception
            ->setOrderNumber(!empty($data['orderNumber']) ? $data['orderNumber'] : null)
            ->setCommentaire(!empty($data['commentaire']) ? $data['commentaire'] : null)
            ->setStatut($statut)
            ->setNumeroReception($numero)
            ->setDate($date)
            ->setOrderNumber(!empty($data['orderNumber']) ? $data['orderNumber'] : null)
            ->setUtilisateur($currentUser)
            ->setType($type)
            ->setCommentaire(!empty($data['commentaire']) ? $data['commentaire'] : null);

        // Date commande provenant des imports de réception
        if ($fromImport && isset($data['orderDate'])) {
            $reception->setDateCommande(DateTime::createFromFormat('d/m/Y', $data['orderDate'], new DateTimeZone("Europe/Paris")) ?: null);
        }
        // Date commande pour création d'une réception standard
        else {
            $reception->setDateCommande(
                !empty($data['dateCommande'])
                    ? new DateTime(str_replace('/', '-', $data['dateCommande']), new DateTimeZone("Europe/Paris"))
                    : null);
        }

        // Date attendue provenant des imports de réception
        if ($fromImport && isset($data['expectedDate'])) {
            $reception->setDateAttendue(DateTime::createFromFormat('d/m/Y', $data['expectedDate'], new DateTimeZone("Europe/Paris")) ?: null);
        }
        // Date attendue pour création d'une réception standard
        else {
            $reception->setDateAttendue(
                !empty($data['dateAttendue'])
                    ? new DateTime(str_replace('/', '-', $data['dateAttendue']), new DateTimeZone("Europe/Paris"))
                    : null);
        }

        $entityManager->persist($reception);
        return $reception;
    }

    /**
     * @param Reception $reception
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowReception(Reception $reception)
    {
        return [
            "id" => ($reception->getId()),
            "Statut" => ($reception->getStatut() ? $reception->getStatut()->getNom() : ''),
            "Date" => ($reception->getDate() ? $reception->getDate() : '')->format('d/m/Y H:i'),
            "dateAttendue" => ($reception->getDateAttendue() ? $reception->getDateAttendue()->format('d/m/Y H:i'): '' ),
            "DateFin" => ($reception->getDateFinReception() ? $reception->getDateFinReception()->format('d/m/Y H:i') : ''),
            "Fournisseur" => ($reception->getFournisseur() ? $reception->getFournisseur()->getNom() : ''),
            "Commentaire" => ($reception->getCommentaire() ? $reception->getCommentaire() : ''),
            "receiver" => implode(', ', array_unique(
                $reception->getDemandes()
                    ->map(function (Demande $request) {
                        return $request->getUtilisateur() ? $request->getUtilisateur()->getUsername() : '';
                    })
                    ->filter(function (string $username) {
                        return !empty($username);
                    })
                    ->toArray())
            ),
            "numeroReception" => ($reception->getNumeroReception() ? $reception->getNumeroReception() : ''),
            "orderNumber" => ($reception->getOrderNumber() ? $reception->getOrderNumber() : ''),
            "storageLocation" => ($reception->getStorageLocation() ? $reception->getStorageLocation()->getLabel() : ''),
            "emergency" => $reception->isManualUrgent() || $reception->hasUrgentArticles(),
            'Actions' => $this->templating->render(
                'reception/datatableReceptionRow.html.twig',
                ['reception' => $reception]
            ),
        ];
    }

    public function createHeaderDetailsConfig(Reception $reception): array {
        $fieldsParamRepository = $this->entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

        $status = $reception->getStatut();
        $provider = $reception->getFournisseur();
        $carrier = $reception->getTransporteur();
        $location = $reception->getLocation();
        $dateCommande = $reception->getDateCommande();
        $dateAttendue = $reception->getDateAttendue();
        $dateEndReception = $reception->getDateFinReception();
        $creationDate = $reception->getDate();
        $orderNumber = $reception->getOrderNumber();
        $comment = $reception->getCommentaire();
        $storageLocation = $reception->getStorageLocation();
        $attachments = $reception->getAttachments();
        $receivers = implode(', ', array_unique(
                $reception->getDemandes()
                    ->map(function (Demande $request) {
                        return $request->getUtilisateur() ? $request->getUtilisateur()->getUsername() : '';
                    })
                    ->filter(function (string $username) {
                        return !empty($username);
                    })
                    ->toArray())
        );

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $reception,
            null,
            CategoryType::RECEPTION
        );

        $config = [
            [
                'label' => 'Statut',
                'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : ''
            ],
            [
                'label' => $this->translator->trans('réception.n° de réception'),
                'title' => 'n° de réception',
                'value' => $reception->getNumeroReception(),
                'show' => [ 'fieldName' => 'numeroReception' ]
            ],
            [
                'label' => 'Fournisseur',
                'value' => $provider ? $provider->getNom() : '',
                'show' => [ 'fieldName' => 'fournisseur' ]
            ],
            [
                'label' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => [ 'fieldName' => 'transporteur' ]
            ],
            [
                'label' => 'Emplacement',
                'value' => $location ? $location->getLabel() : '',
                'show' => [ 'fieldName' => 'emplacement' ]
            ],
            [
                'label' => 'Date commande',
                'value' => $dateCommande ? $dateCommande->format('d/m/Y') : '',
                'show' => [ 'fieldName' => 'dateCommande' ]
            ],
            [
                'label' => 'Numéro de commande',
                'value' => $orderNumber ?: '',
                'show' => [ 'fieldName' => 'numCommande' ]
            ],
            [
                'label' => 'Destinataire(s)',
                'value' => $receivers ?: ''
            ],
            [
                'label' => 'Date attendue',
                'value' => $dateAttendue ? $dateAttendue->format('d/m/Y') : '',
                'show' => [ 'fieldName' => 'dateAttendue' ]
            ],
            [ 'label' => 'Date de création', 'value' => $creationDate ? $creationDate->format('d/m/Y H:i') : '' ],
            [ 'label' => 'Date de fin', 'value' => $dateEndReception ? $dateEndReception->format('d/m/Y H:i') : '' ],
            [
                'label' => 'Emplacement de stockage',
                'value' => $storageLocation ?: '',
                'show' => [ 'fieldName' => 'storageLocation' ]
            ],
        ];

        $configFiltered =  $this->fieldsParamService->filterHeaderConfig($config, FieldsParam::ENTITY_CODE_RECEPTION);

        return array_merge(
            $configFiltered,
            $freeFieldArray,
            ($this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedFormsCreate')
                || $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedFormsEdit'))
                ? [[
                'label' => 'Commentaire',
                'value' => $comment ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]]
                : [],
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachment', 'displayedFormsCreate')
            || $this->fieldsParamService->isFieldRequired($fieldsParam, 'attachment', 'displayedFormsEdit')
                ? [[
                'label' => 'Pièces jointes',
                'value' => $attachments->toArray(),
                'isAttachments' => true,
                'isNeededNotEmpty' => true
            ]]
                : []
        );
    }
}
