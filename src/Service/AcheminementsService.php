<?php


namespace App\Service;

use App\Entity\Acheminements;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

class AcheminementsService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Utilisateur
     */
    private $user;

    private $entityManager;
    private $freeFieldService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                FreeFieldService $champLibreService) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->freeFieldService = $champLibreService;
    }

    /**
     * @param null $params
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getDataForDatatable($params = null) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $acheminementsRepository = $this->entityManager->getRepository(Acheminements::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ACHEMINEMENTS, $this->user);
        $queryResult = $acheminementsRepository->findByParamAndFilters($params, $filters);

        $acheminementsArray = $queryResult['data'];

        $rows = [];
        foreach ($acheminementsArray as $acheminement) {
            $rows[] = $this->dataRowAcheminement($acheminement);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

	/**
	 * @param Acheminements $acheminement
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function dataRowAcheminement($acheminement)
    {
        $nbColis = count($acheminement->getPacks());
        $url = $this->router->generate('acheminement-show', ['id' => $acheminement->getId()]);

        return [
            'id' => $acheminement->getId() ?? 'Non défini',
            'Numero' => $acheminement->getNumeroAcheminement() ?? '',
            'Date' => $acheminement->getDate() ? $acheminement->getDate()->format('d/m/Y H:i:s') : 'Non défini',
            'Demandeur' => $acheminement->getRequester() ? $acheminement->getRequester()->getUserName() : '',
            'Destinataire' => $acheminement->getReceiver() ? $acheminement->getReceiver()->getUserName() : '',
            'Emplacement prise' => $acheminement->getLocationFrom() ? $acheminement->getLocationFrom()->getLabel() : '',
            'Emplacement de dépose' => $acheminement->getLocationTo() ? $acheminement->getLocationTo()->getLabel() : '',
            'Nb Colis' => $nbColis ?? 0,
            'Type' => $acheminement->getType() ? $acheminement->getType()->getLabel() : '',
            'Statut' => $acheminement->getStatut() ? $acheminement->getStatut()->getNom() : '',
            'Actions' => $this->templating->render('acheminements/datatableAcheminementsRow.html.twig', [
                'acheminement' => $acheminement,
                'url' => $url,
            ]),
        ];
    }

    public function createHeaderDetailsConfig(Acheminements $acheminement): array
    {
        $status = $acheminement->getStatut();
        $type = $acheminement->getType();
        $requester = $acheminement->getRequester();
        $locationFrom = $acheminement->getLocationFrom();
        $locationTo = $acheminement->getLocationTo();
        $creationDate = $acheminement->getDate();
        $comment = $acheminement->getCommentaire();

        $freeFieldArray = $this->freeFieldService->getFilledFreeFieldArray(
            $this->entityManager,
            $acheminement,
            CategorieCL::DEMANDE_ACHEMINEMENT,
            CategoryType::DEMANDE_ACHEMINEMENT
        );

        return array_merge(
            [
                ['label' => 'Statut', 'value' => $status ? $status->getNom() : ''],
                ['label' => 'Type', 'value' => $type ? $type->getLabel() : ''],
                ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
                ['label' => 'Emplacement de prise', 'value' => $locationFrom ? $locationFrom->getLabel() : ''],
                ['label' => 'Emplacement de dépose', 'value' => $locationTo ? $locationTo->getLabel() : ''],
                ['label' => 'Date de création', 'value' => $creationDate ? $creationDate->format('d/m/Y H:i:s') : ''],
            ],
            $freeFieldArray,
            [
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }

    public function createAcheminementNumber(EntityManagerInterface $entityManager,
                                        DateTime $date): string {

        $acheminementRepository = $entityManager->getRepository(Acheminements::class);

        $dateStr = $date->format('Ymd');

        $lastNumeroAcheminement = $acheminementRepository->getLastNumeroAcheminementByDate($dateStr);
        if ($lastNumeroAcheminement) {
            $lastCounter = (int) substr($lastNumeroAcheminement, -4, 4);
            $currentCounter = ($lastCounter + 1);
        }
        else {
            $currentCounter = 1;
        }

        $currentCounterStr = (
        $currentCounter < 10 ? ('000' . $currentCounter) :
            ($currentCounter < 100 ? ('00' . $currentCounter) :
                ($currentCounter < 1000 ? ('0' . $currentCounter) :
                    $currentCounter))
        );

        return ('A-' . $dateStr . $currentCounterStr);
    }
}
