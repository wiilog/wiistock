<?php

namespace App\Service;


use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;


class TypeService {

    public function editType(Type $type,
                             EntityManagerInterface $entityManager,
                             array $data) {
        $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $category = $categoryTypeRepository->find($data['category']);

        $isDispatch = ($category->getLabel() === CategoryType::DEMANDE_DISPATCH);

        $type
            ->setLabel($data['label'])
            ->setSendMail($data["sendMail"] ?? false)
            ->setCategory($category)
            ->setDescription($data['description'])
            ->setNotificationsEnabled($data["notificationsEnabled"] ?? false)
            ->setNotificationsEmergencies($data["notificationsEmergencies"] ?? []);

        if ($isDispatch) {
            $dropLocation = $data["depose"] ? $emplacementRepository->find($data["depose"]) : null;
            $pickLocation = $data["prise"] ? $emplacementRepository->find($data["prise"]) : null;

            $type
                ->setDropLocation($dropLocation)
                ->setPickLocation($pickLocation);
        }
    }
}
