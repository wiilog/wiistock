<?php


namespace App\Service;


use App\Entity\CategoryType;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Reception;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ReceptionService
{
    private $templating;
    private $router;
    private $user;
    private $entityManager;
    private $fieldsParamService;
    private $stringService;
    private $translator;
    private $freeFieldService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                FieldsParamService $fieldsParamService,
                                StringService $stringService,
                                FreeFieldService $champLibreService,
                                TranslatorInterface $translator,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->freeFieldService = $champLibreService;
        $this->entityManager = $entityManager;
        $this->stringService = $stringService;
        $this->fieldsParamService = $fieldsParamService;
        $this->router = $router;
        $this->translator = $translator;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null)
    {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receptionRepository = $this->entityManager->getRepository(Reception::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEPTION, $this->user);
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
	 * @param Reception $reception
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowReception(Reception $reception)
    {
        $row =
            [
                'id' => ($reception->getId()),
                "Statut" => ($reception->getStatut() ? $reception->getStatut()->getNom() : ''),
                "Date" => ($reception->getDate() ? $reception->getDate() : '')->format('d/m/Y H:i'),
                "DateFin" => ($reception->getDateFinReception() ? $reception->getDateFinReception()->format('d/m/Y H:i') : ''),
                "Fournisseur" => ($reception->getFournisseur() ? $reception->getFournisseur()->getNom() : ''),
                "Commentaire" => ($reception->getCommentaire() ? $reception->getCommentaire() : ''),
                "Référence" => ($reception->getNumeroReception() ? $reception->getNumeroReception() : ''),
                "Numéro de commande" => ($reception->getReference() ? $reception->getReference() : ''),
                'Actions' => $this->templating->render(
                    'reception/datatableReceptionRow.html.twig',
                    ['reception' => $reception]
                ),
                'urgence' => $reception->getEmergencyTriggered()
        ];
        return $row;
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
        $reference = $reception->getReference();
        $comment = $reception->getCommentaire();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $reception,
            null,
            CategoryType::RECEPTION
        );

        $config = [
            [ 'label' => 'Statut', 'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : '' ],
            [
                'label' => $this->translator->trans('réception.n° de réception'),
                'title' => 'n° de réception',
                'value' => $reception->getNumeroReception(),
                'show' => [ 'fieldName' => 'numeroReception', 'action' => 'displayed' ]
            ],
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
                'label' => 'Emplacement',
                'value' => $location ? $location->getLabel() : '',
                'show' => [ 'fieldName' => 'emplacement', 'action' => 'displayed' ]
            ],
            [
                'label' => 'Date commande',
                'value' => $dateCommande ? $dateCommande->format('d/m/Y') : '',
                'show' => [ 'fieldName' => 'dateCommande', 'action' => 'displayed' ]
            ],
            [
                'label' => 'Numéro de commande',
                'value' => $reference ?: '',
                'show' => [ 'fieldName' => 'numCommande', 'action' => 'displayed' ]
            ],
            [
                'label' => 'Date attendue',
                'value' => $dateAttendue ? $dateAttendue->format('d/m/Y') : '',
                'show' => [ 'fieldName' => 'dateAttendue', 'action' => 'displayed' ]
            ],
            [ 'label' => 'Date de création', 'value' => $creationDate ? $creationDate->format('d/m/Y H:i') : '' ],
            [ 'label' => 'Date de fin', 'value' => $dateEndReception ? $dateEndReception->format('d/m/Y H:i') : '' ],
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
            $this->fieldsParamService->isFieldRequired($fieldsParam, 'commentaire', 'displayedForms')
                ? [[
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]]
                : []
        );
    }
}
