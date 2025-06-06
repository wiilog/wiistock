<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Emergency\Emergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fournisseur;

use App\Entity\Menu;
use App\Entity\Reception;
use App\Exceptions\FormException;
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

    #[Required]
    public UserService $userService;

    public function getFournisseurDataByParams(?InputBag $params = null): array
    {
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $fournisseursData = $fournisseurRepository->getByParams($params);

        $fournisseursData['data'] = array_map(
            function ($fournisseur) {
                return $this->dataRowFournisseur($fournisseur);
            },
            $fournisseursData['data']
        );

        return $fournisseursData;
    }

    public function dataRowFournisseur(Fournisseur $supplier): array
    {
        return [
            FixedFieldEnum::name->name => $supplier->getNom(),
            FixedFieldEnum::code->name => $supplier->getCodeReference(),
            FixedFieldEnum::possibleCustoms->name => $this->formatService->bool($supplier->isPossibleCustoms()),
            FixedFieldEnum::urgent->name => $this->formatService->bool($supplier->isUrgent()),
            FixedFieldEnum::address->name => $supplier->getAddress(),
            FixedFieldEnum::email->name => $supplier->getEmail(),
            FixedFieldEnum::phoneNumber->name => $this->formatService->phone($supplier->getPhoneNumber()),
            FixedFieldEnum::receiver->name => $supplier->getReceiver(),
            'Actions' => $this->templating->render("utils/action-buttons/dropdown.html.twig", [
                "actions" => [
                    [
                        "title" => "Modifier",
                        "hasRight" => $this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT),
                        "actionOnClick" => true,
                        "icon" => "fas fa-pencil-alt",
                        "attributes" => [
                            "data-id" => $supplier->getId(),
                            "data-target" => "#modalEditFournisseur",
                            "data-toggle" => "modal",
                        ],
                    ],
                    [
                        "title" => "Supprimer",
                        "icon" => "wii-icon wii-icon-trash-black",
                        "class" => "delete-supplier",
                        "attributes" => [
                            "data-id" => $supplier->getId(),
                        ],
                    ],
                ],
            ]),
        ];
    }

    public function isSupplierUsed(Fournisseur $supplier, EntityManagerInterface $entityManager): array {
        $receptionRepository = $entityManager->getRepository(Reception::class);
        $emergencyRepository = $entityManager->getRepository(Emergency::class);

        $usedBy = [];

        if (!$supplier->getArticlesFournisseur()->isEmpty()) {
            $usedBy[] = 'articles fournisseur';
        }

        if ($receptionRepository->count(['fournisseur' => $supplier]) > 0) {
            $usedBy[] = 'réceptions';
        }

        if (!$supplier->getReceptionReferenceArticles()->isEmpty()) {
            $usedBy[] = 'lignes de réception';
        }

        if (!$supplier->getArrivages()->isEmpty()) {
            $usedBy[] = 'arrivages';
        }

        if ($emergencyRepository->count(['supplier' => $supplier]) > 0) {
            $usedBy[] = 'urgences';
        }

        return $usedBy;
    }

    public function editSupplier(Fournisseur $supplier, InputBag $data, EntityManagerInterface $entityManager): Fournisseur {
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $codeAlreadyUsed = $supplier->getCodeReference() !== $data->get(FixedFieldEnum::code->name) && $fournisseurRepository->count(["codeReference" => $data->get(FixedFieldEnum::code->name)]) > 0;
        if ($codeAlreadyUsed) {
            throw new FormException("Ce " . lcfirst(FixedFieldEnum::code->value) . " est déjà utilisé.");
        }

        $supplier
            ->setNom($data->get(FixedFieldEnum::name->name))
            ->setCodeReference($data->get(FixedFieldEnum::code->name))
            ->setPossibleCustoms($data->getBoolean(FixedFieldEnum::possibleCustoms->name))
            ->setUrgent($data->getBoolean(FixedFieldEnum::urgent->name))
            ->setAddress($data->get(FixedFieldEnum::address->name))
            ->setReceiver($data->get(FixedFieldEnum::receiver->name))
            ->setPhoneNumber($data->get(FixedFieldEnum::phoneNumber->name))
            ->setEmail($data->get(FixedFieldEnum::email->name));

        return $supplier;
    }

}


