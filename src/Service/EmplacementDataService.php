<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09.
 */

namespace App\Service;

use App\Repository\EmplacementRepository;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

class EmplacementDataService
{
    
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    private $em;

    public function __construct(EmplacementRepository $emplacementRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
    
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
        $this->emplacementRepository = $emplacementRepository;
    }

    
    public function getDataForDatatable($params = null)
    {
        $data = $this->getEmplacementDataByParams($params);
        $data['recordsTotal'] = (int)$this->emplacementRepository->countAll();
        return $data;
    }

    /**
     * @param null $params
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getEmplacementDataByParams($params = null)
    {
        $emplacements = $this->emplacementRepository->findByParams($params);

        $rows = [];
        foreach ($emplacements as $emplacement) {
            $rows[] = $this->dataRowEmplacement($emplacement);
        }
        return ['data' => $rows];
    }

    /**
     * @param Emplacement $emplacement
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function dataRowEmplacement($emplacement)
    {
        $url['edit'] = $this->router->generate('emplacement_edit', ['id' => $emplacement->getId()]);

        $row = [
                    'id' => ($emplacement->getId() ? $emplacement->getId() : 'Non défini'),
                    'Nom' => ($emplacement->getLabel() ? $emplacement->getLabel() : 'Non défini'),
                    'Description' => ($emplacement->getDescription() ? $emplacement->getDescription() : 'Non défini'),
                    'Actions' => $this->templating->render('emplacement/datatableEmplacementRow.html.twig', [
                        'url' => $url,
                        'emplacementId' => $emplacement->getId(),
                    ]),
                    ];
        return $row;
    }
}
