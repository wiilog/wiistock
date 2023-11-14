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
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231025123138 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    public function getDescription(): string
    {
        return 'Creation du parametrage des champs fixes par type pour les acheminements en fonction du paramtrage des champs fixes standards';
    }

    public function up(Schema $schema): void {
        if (!$schema->hasTable('fixed_field_by_type')) {
            return;
        }

        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $fixedFieldStandardRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
        $types = new ArrayCollection($types);
        $emptyCollection = new ArrayCollection([]);

        $fieldsStandards = $fixedFieldStandardRepository->findByEntityForEntity(FixedFieldStandard::ENTITY_CODE_DISPATCH);
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
                ->setDisplayedEdit($field->isDisplayedEdit() ? $types : $emptyCollection)
                ->setOnMobile($field->isOnMobile() ? $types : $emptyCollection);

            if ($field->isDisplayedFilters()) {
                $onFilerFields[] = $fieldByType;
            }

            $entityManager->persist($fieldByType);
            $entityManager->remove($field);
        }
        $entityManager->flush();

        $onFilterFieldsSetting = (new Setting())
            ->setLabel(Setting::DISPATCH_FIXED_FIEDS_ON_FILTERS)
            ->setValue(Stream::from($onFilerFields)
                ->map(static fn(FixedFieldByType $field) => $field->getId())
                ->join(','));

        $entityManager->persist($onFilterFieldsSetting);
        $entityManager->flush();

    }

    public function down(Schema $schema): void {

    }
}
