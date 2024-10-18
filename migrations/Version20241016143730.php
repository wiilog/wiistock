<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Setting;
use App\Entity\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241016143730 extends AbstractMigration implements ContainerAwareInterface{

    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void {
        if (!$schema->hasTable('fixed_field_by_type')) {
            return;
        }

        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $fixedFieldStandardRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $types = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);
        $types = new ArrayCollection($types);
        $emptyCollection = new ArrayCollection([]);

        $fieldsStandards = $fixedFieldStandardRepository->findByEntityCode(FixedFieldStandard::ENTITY_CODE_PRODUCTION);
        $onFilerFields = [];
        foreach ($fieldsStandards as $field) {
            $fieldByType = (new FixedFieldByType())
                ->setEntityCode($field->getEntityCode())
                ->setFieldCode($field->getFieldCode())
                ->setFieldLabel($field->getFieldLabel())
                ->setElements($field->getElements())
                ->setElementsType($field->getElementsType())
                ->setRequiredCreate($field->isRequiredCreate() ? $types : $emptyCollection)
                ->setRequiredEdit($field->isRequiredEdit() ? $types : $emptyCollection)
                ->setKeptInMemory($field->isKeptInMemory() ? $types : $emptyCollection)
                ->setDisplayedCreate($field->isDisplayedCreate() ? $types : $emptyCollection)
                ->setDisplayedEdit($field->isDisplayedEdit() ? $types : $emptyCollection);

            if ($field->isDisplayedFilters()) {
                $onFilerFields[] = $field->getFieldCode();
            }

            $entityManager->persist($fieldByType);
            $entityManager->remove($field);
        }

        $entityManager->flush();

        $this->addSql("INSERT INTO setting (label, value) VALUES (':label', ':value')", [
            ":label" => Setting::PRODUCTION_FIXED_FIELDS_ON_FILTERS,
            ":value" => join(',', $onFilerFields),
        ]);
    }

    public function down(Schema $schema): void {}
}
