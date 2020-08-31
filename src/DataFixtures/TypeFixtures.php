<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use App\Entity\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class TypeFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager)
    {
    	$categoriesTypes = [
            CategoryType::DEMANDE_DISPATCH => [Type::LABEL_STANDARD],
            CategoryType::DEMANDE_HANDLING => [Type::LABEL_STANDARD],
            CategoryType::ARTICLE => [Type::LABEL_STANDARD],
			CategoryType::RECEPTION => [Type::LABEL_RECEPTION],
			CategoryType::DEMANDE_LIVRAISON => [Type::LABEL_STANDARD],
			CategoryType::DEMANDE_COLLECTE => [Type::LABEL_STANDARD],
			CategoryType::LITIGE => [
				Type::LABEL_MANQUE_BL,
				Type::LABEL_MANQUE_INFO_BL,
				Type::LABEL_ECART_QTE,
				Type::LABEL_ECART_QUALITE,
				Type::LABEL_PB_COMMANDE,
				Type::LABEL_DEST_NON_IDENT
			],
            CategoryType::ARRIVAGE => [Type::LABEL_STANDARD],
            CategoryType::MOUVEMENT_TRACA => [Type::LABEL_MVT_TRACA],
		];

        $typeRepository = $manager->getRepository(Type::class);
        $categoryTypeRepository = $manager->getRepository(CategoryType::class);

        foreach ($categoriesTypes as $categoryName => $typesNames) {
    		// création des catégories de types
			$categorie = $categoryTypeRepository->findOneBy(['label' => $categoryName]);

			if (empty($categorie)) {
				$categorie = new CategoryType();
				$categorie->setLabel($categoryName);
				$manager->persist($categorie);
				dump("création de la catégorie " . $categoryName);
			}
			$this->addReference('type-' . $categoryName, $categorie);

			$categoryHasType = count($typeRepository->findByCategoryLabel($categoryName)) > 0;

			// création des types
    		foreach ($typesNames as $typeName) {
				if (!$categoryHasType || ($typeName !== Type::LABEL_STANDARD && $typeName !== Type::LABEL_RECEPTION && $categoryName !== CategoryType::LITIGE)) {
                    $type = $typeRepository->findOneByCategoryLabelAndLabel($categoryName, $typeName);
                    if (empty($type)) {
                        $type = new Type();
                        $type
                            ->setCategory($this->getReference('type-' . $categoryName))
                            ->setLabel($typeName);
                        $manager->persist($type);
                        dump("création du type " . $typeName);
                    }
                }
			}
		}
    	$manager->flush();
    }

    public static function getGroups(): array
    {
        return ['types', 'fixtures'];
    }
}
