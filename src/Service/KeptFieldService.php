<?php

namespace App\Service;

use App\Entity\FieldsParam;
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

    public array $cache;

    public function getAll(string $entity): array {
        $fieldsParamRepository = $this->manager->getRepository(FieldsParam::class);
        $repository = $this->manager->getRepository(KeptFieldValue::class);
        $user = $this->security->getUser();

        $keptFields = Stream::from($fieldsParamRepository->findByEntityForEntity($entity))
            ->filter(fn(FieldsParam $field) => $field->isKeptInMemory())
            ->map(fn(FieldsParam $field) => $field->getFieldCode())
            ->toArray();

        return Stream::from($repository->findBy(["entity" => $entity, "user" => $user]) ?? [])
            ->filter(fn(KeptFieldValue $field) => in_array($field->getField(), $keptFields))
            ->keymap(fn(KeptFieldValue $field) => [$field->getField(), json_decode($field->getValue())])
            ->toArray();
    }

    public function save(string $entity, string $field, mixed $value): void {
        if(!isset($this->cache[$entity])) {
            $this->loadKeptFieldsCache($entity);
        }

        if($this->cache[$entity][$field]['keptInMemory'] ?? false) {
            $keptFieldValueRepository = $this->manager->getRepository(KeptFieldValue::class);
            $user = $this->security->getUser();
            $keptField = $keptFieldValueRepository->findOneBy(["entity" => $entity, "field" => $field, "user" => $user]);
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

    private function loadKeptFieldsCache(string $entity): void {
        $fixedFieldRepository = $this->manager->getRepository(FieldsParam::class);
        $this->cache[$entity] = $fixedFieldRepository->getByEntity($entity);
    }

}
