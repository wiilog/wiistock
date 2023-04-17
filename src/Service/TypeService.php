<?php

namespace App\Service;


use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;


class TypeService {

    public function editType(Type $type,
                             EntityManagerInterface $entityManager,
                             array $data): ?string {
        $categoryTypeRepository = $entityManager->getRepository(CategoryType::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $category = $categoryTypeRepository->find($data['category']);

        $isDispatch = ($category->getLabel() === CategoryType::DEMANDE_DISPATCH);
        $isArticle = ($category->getLabel() === CategoryType::ARTICLE);

        if(preg_match("[[,;]]", $data['label'])) {
            return "Le label d'un type ne peut pas contenir ; ou ,";
        }

        $type
            ->setLabel($data['label'])
            ->setSendMailRequester(filter_var($data["sendMail"] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->setSendMailReceiver(filter_var($data["sendMailReceiver"] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->setCategory($category)
            ->setDescription($data['description'])
            ->setNotificationsEnabled(filter_var($data["notificationsEnabled"] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->setNotificationsEmergencies($data["notificationsEmergencies"] ?? []);

        if ($isDispatch) {
            $dropLocation = $data["depose"] ? $emplacementRepository->find($data["depose"]) : null;
            $pickLocation = $data["prise"] ? $emplacementRepository->find($data["prise"]) : null;

            $type
                ->setDropLocation($dropLocation)
                ->setPickLocation($pickLocation);
        } else if ($isArticle) {
            $type->setColor($data['color']);
        }

        return null;
    }
}
