<?php

namespace App\DataFixtures;

use App\Repository\ActionRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class PatchRenameChampsLibreToChampLibreFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

    public function __construct(ActionRepository $actionRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->actionRepository = $actionRepository;
    }

    public function load(ObjectManager $manager)
    {
    	$query = "RENAME TABLE `champs_libre` TO `champ_libre`;";
    	$query .= "RENAME TABLE `demande_valeur_champs_libre` TO `demande_valeur_champ_libre`;";
    	$query .= "RENAME TABLE `reception_valeur_champs_libre` TO `reception_valeur_champ_libre`;";
    	$query .= "RENAME TABLE `valeur_champs_libre` TO `valeur_champ_libre`;";
    	$query .= "RENAME TABLE `valeur_champs_libre_article` TO `valeur_champ_libre_article`;";
    	$query .= "RENAME TABLE `valeur_champs_libre_reference_article` TO `valeur_champ_libre_reference_article`;";

    	$manager->getConnection()->exec($query);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['champLibreWithoutS'];
    }
}
