<?php


namespace App\DataFixtures;

use App\Entity\FieldsParam;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class ChampsFixesReceptionFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct()
    {

    }

    public function load(ObjectManager $manager)
    {
        $fieldFournisseur = new FieldsParam();
        $fieldFournisseur
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Fournisseur');
        $manager->persist($fieldFournisseur);

        $fieldNumero = new FieldsParam();
        $fieldNumero
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Numero de commande');
        $manager->persist($fieldNumero);

        $fieldWaitedDate = new FieldsParam();
        $fieldWaitedDate
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Date attendue');
        $manager->persist($fieldWaitedDate);

        $fieldOrderDate = new FieldsParam();
        $fieldOrderDate
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Date commande');
        $manager->persist($fieldOrderDate);

        $fieldComment = new FieldsParam();
        $fieldComment
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Commentaire');
        $manager->persist($fieldComment);


        $fieldUser = new FieldsParam();
        $fieldUser
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Utilisateur');
        $manager->persist($fieldUser);

        $fieldReception = new FieldsParam();
        $fieldReception
            ->setEntityCode(FieldsParam::RECEPTION)
            ->setFieldCode('Numero reception');
        $manager->persist($fieldReception);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['setFields'];
    }
}
