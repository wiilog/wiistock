<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use App\Entity\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class PatchLitigesSafranFixtures extends Fixture implements FixtureGroupInterface
{
	// spécifique SAFRAN CERAMICS messages de commentaires pour litiges
	const MSG_MANQUE_BL = "Nous venons de recevoir un colis à votre attention sans bordereau de livraison.\n" .
	"Dans l’attente du document votre colis est placé en litige.\n" .
	"Nous rappelons que le BL doit être émis au titre d’une commande ou à titre gracieux.";

	const MSG_MANQUE_INFO_BL = "Nous venons de recevoir un colis à votre attention.\n" .
	"Pour pouvoir finaliser la réception nous avons besoin d’un BL au titre d’une commande ou à titre gracieux.\n" .
	"Dans l’attente du document votre colis est placé en litige.";

	const MSG_ECART_QTE = "Nous venons de recevoir un colis à votre attention, nous avons constaté un écart en quantité,\n" .
	"[décrire la quantité de l’écart]\n" .
	"Dans l’attente de vos instructions la quantité en écart est placée en litige.";

	const MSG_ECART_QUALITE = "Nous venons de recevoir un colis à votre attention et nous avons constaté un problème qualité.\n" .
	"[décrire le problème qualité et joindre une ou plusieurs photos du problème constaté]\n" .
	"Dans l’attente de vos instructions le colis est placé en zone litige.";

	const MSG_PB_COMMANDE = "Nous venons de recevoir un colis au titre de la commande [rentrer le numéro de commande]\n" .
	"[décrire le problème constaté].\n" .
	"Dans l’attente de vos instructions le colis est placé en zone litige.";

	const MSG_DEST_NON_IDENT = "Nous venons de recevoir un colis à titre gracieux et nous sommes dans l’incapacité d’identifier un destinataire.\n" .
	"Dans l’attente de vos instructions le colis est placé en zone litige.";

    public function load(ObjectManager $manager)
    {
		$typesAndMsg = [
			Type::LABEL_MANQUE_BL => self::MSG_MANQUE_BL,
			Type::LABEL_MANQUE_INFO_BL => self::MSG_MANQUE_INFO_BL,
			Type::LABEL_ECART_QTE => self::MSG_ECART_QTE,
			Type::LABEL_ECART_QUALITE => self::MSG_ECART_QUALITE,
			Type::LABEL_PB_COMMANDE => self::MSG_PB_COMMANDE,
			Type::LABEL_DEST_NON_IDENT => self::MSG_DEST_NON_IDENT
		];

        $typeRepository = $manager->getRepository(Type::class);

		foreach ($typesAndMsg as $typeLabel => $msg) {
			$type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::LITIGE, $typeLabel);
			if (!$type) {
				dump('il manque le type ' . $typeLabel);
				break;
			}

			$type->setDescription($msg);
			$manager->flush();
		}

    }

    public static function getGroups(): array
    {
        return ['litigesSafran', 'safran'];
    }
}
