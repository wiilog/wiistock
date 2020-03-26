<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Reception;
use App\Entity\Utilisateur;
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
     * @var Utilisateur
     */
    private $user;

    private $entityManager;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
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
}
