<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Reception;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\ReceptionRepository;
use App\Repository\ReferenceArticleRepository;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ReceptionService
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
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    /**
     * @var Utilisateur
     */
    private $user;

    private $em;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                FiltreSupRepository $filtreSupRepository,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                ReceptionRepository $receptionRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->receptionRepository = $receptionRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null)
    {
        $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RECEPTION, $this->user);
        $queryResult = $this->receptionRepository->findByParamAndFilters($params, $filters);

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
                "Date" => ($reception->getDate() ? $reception->getDate() : '')->format('d/m/Y'),
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
}
