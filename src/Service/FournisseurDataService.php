<?php

namespace App\Service;

use App\Entity\Fournisseur;

use App\Helper\FormatHelper;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class FournisseurDataService
{

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FormatService $formatService;

    public function getFournisseurDataByParams(?InputBag $params = null): array
    {
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $fournisseursData = $fournisseurRepository->getByParams($params);

        $fournisseursData['data'] = array_map(
            function($fournisseur) {
                return $this->dataRowFournisseur($fournisseur);
            },
            $fournisseursData['data']
        );

        return $fournisseursData;
    }

    public function dataRowFournisseur(Fournisseur $supplier): array
    {
        $supplierId = $supplier->getId();
        $url['edit'] = $this->router->generate('supplier_edit', ['id' => $supplierId]);

        return [
            "name" => $supplier->getNom(),
            "code" => $supplier->getCodeReference(),
            "possibleCustoms" => $this->formatService->bool($supplier->isPossibleCustoms()),
            "urgent" => $this->formatService->bool($supplier->isUrgent()),
            "address" => $supplier->getAddress(),
            "email" => $supplier->getEmail(),
            "phoneNumber" => $this->formatService->phone($supplier->getPhoneNumber()),
            "receiver" => $this->formatService->user($supplier->getReceiver()),
            'Actions' => $this->templating->render('fournisseur/datatableFournisseurRow.html.twig', [
                'url' => $url,
                'supplierId' => $supplierId
            ]),
        ];
    }
}


