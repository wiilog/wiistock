<?php

namespace App\Service;

use App\Entity\KeptFieldValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class KeptFieldService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public Security $security;

    public function getAll(string $entity): array {
        $repository = $this->manager->getRepository(KeptFieldValue::class);
        $user = $this->security->getUser();

        return Stream::from($repository->findBy(["entity" => $entity, "user" => $user]) ?? [])
            ->keymap(fn(KeptFieldValue $field) => [$field->getField(), json_decode($field->getValue())])
            ->toArray();
    }

    public function save(string $entity, string $field, mixed $value) {
        //TODO: sauvegarder uniquement si le paramétrage l'autorise
        $repository = $this->manager->getRepository(KeptFieldValue::class);
        $user = $this->security->getUser();

        $keptField = $repository->findOneBy(["entity" => $entity, "field" => $field, "user" => $user]);
        if(!$keptField) {
            $keptField = new KeptFieldValue();
            $keptField->setEntity($entity)
                ->setField($field)
                ->setUser($user);

            $this->manager->persist($keptField);
        }

        $keptField->setValue(json_encode($value));
    }

}
