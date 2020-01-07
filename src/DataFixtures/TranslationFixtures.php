<?php

namespace App\DataFixtures;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class TranslationFixtures extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var TranslationRepository
	 */
	private $translationRepository;

    public function __construct(TranslationRepository $translationRepository)
    {
    	$this->translationRepository = $translationRepository;
    }

	/**
	 * @param ObjectManager $manager
	 */
    public function load(ObjectManager $manager)
    {
    	$databaseFilled = $this->translationRepository->findAll();

    	if (!$databaseFilled) {
			$translations = [
				'arrivage' => [
					'many' => 'arrivages',
					'one' => 'arrivages',
					'this' => 'cet arrivage',
					'd-arrivage' => "d'arrivage",
					'new' => 'nouvel arrivage',
					'de-colis' => 'de colis',
					'colis' => 'articles'
				],
				'reception' => [
					'many' => 'réceptions',
					'one' => 'réception',
					'de-reception' => 'de réception',
					'this' => 'cette réception',
					'new' => 'nouvelle réception',
					'le-la' => 'la',
					'une-reception' => 'une réception',
					'la-reception' => 'la réception',
					'article' => 'article',
					'articles' => 'articles',
					'l-article' => "l'article",
					'd-article' => "d'article",
					'd-articles' => "d'articles"
				]
			];

			foreach ($translations as $menu => $translation) {
				foreach ($translation as $label => $translatedLabel) {
					$translationObject = new Translation();
					$translationObject
						->setLabel($menu . '.' . $label)
						->setTranslation($translatedLabel)
						->setUpdated(true);
					$manager->persist($translationObject);
				}
			}
			$manager->flush();
		}
    }

    public static function getGroups(): array
    {
        return ['translation', 'fixtures'];
    }
}
