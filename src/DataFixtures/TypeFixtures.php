<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use App\Entity\Type;
use App\Repository\CategoryTypeRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TypeFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

	/**
	 * @var CategoryTypeRepository
	 */
    private $categoryTypeRepository;


    public function __construct(CategoryTypeRepository $categoryTypeRepository, TypeRepository $typeRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
    }

    public function load(ObjectManager $manager)
    {
    	$categoriesTypes = [
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
		];

    	foreach ($categoriesTypes as $categoryName => $typesNames) {
    		// création des catégories de types
			$categorie = $this->categoryTypeRepository->findOneBy(['label' => $categoryName]);

			if (empty($categorie)) {
				$categorie = new CategoryType();
				$categorie->setLabel($categoryName);
				$manager->persist($categorie);
				dump("création de la catégorie " . $categoryName);
			}
			$this->addReference('type-' . $categoryName, $categorie);

			$categoryHasType = count($this->typeRepository->findByCategoryLabel($categoryName)) > 0;

			// création des types
    		foreach ($typesNames as $typeName) {
				if (!$categoryHasType || ($typeName !== Type::LABEL_STANDARD && $typeName !== Type::LABEL_RECEPTION)) {
                    $type = $this->typeRepository->findOneByCategoryLabelAndLabel($categoryName, $typeName);
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
