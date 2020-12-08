<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CEAFieldsFixtures extends Fixture implements FixtureGroupInterface {

    private const CONVERT = [
        "Ã‰" => "É"
    ];

    private $manager;
    private $suppliers = [];

    public function load(ObjectManager $manager) {
        $this->cache($manager);

        $references = $manager->getRepository(ReferenceArticle::class)->findBy([
            "type" => 1
        ]);

        $count = 0;
        foreach($references as $reference) {
            $this->applyFirstRule($reference);
            $this->applySecondRule($reference);

            if(++$count % 5000 == 0) {
                $manager->flush();
            }
        }

        $manager->flush();
    }

    private function cache(ObjectManager $manager) {
        $this->manager = $manager;
        $suppliers = $manager->getRepository(Fournisseur::class)->findAll();

        foreach($suppliers as $supplier) {
            $this->suppliers[$this->sanitize($supplier->getNom(), true)] = $supplier;
            $this->suppliers[$this->sanitize($supplier->getCodeReference(), true)] = $supplier;
        }
    }

    // Rassemble les deux premières règles de Jade :
    // - Si "Fournisseur" = null et "Ref article fournisseur" = null
    //   ET "Equipementier" = non null et "Ref Equipementier" = non null
    // - Si "Fournisseur" = non null et "Ref article fournisseur" = non null
    //   ET "Equipementier" = non null et "Ref Equipementier" = non null
    //
    // Création d'un "Article fournisseur" avec :
    // * Nom fournisseur = Equipementier
    // * Code fournisseur = Nom fournisseur
    // * Ref article fournisseur = Ref Equipementier
    // * Libellé article = Libellé Référence
    private function applyFirstRule(ReferenceArticle $reference) {
        $oem = $this->getOEM($reference);
        $oemReference = $this->getOEMReference($reference);

        if($oem !== null && $oemReference !== null) {
            $article = (new ArticleFournisseur())
                ->setReferenceArticle($reference)
                ->setReference($oemReference)
                ->setFournisseur($oem)
                ->setLabel($reference->getLibelle());

            $this->manager->persist($article);
        }
    }

    // Cinquième règle de Jade :
    // Si "Fournisseur" = A DETERMINER et "Ref article fournisseur" = A DETERMINER
    // ET "Equipementier" = non null et "Ref Equipementier" = non null
    //
    // Ecraser les valeurs de la ligne "Article fournisseur" existante avec :
    // * Nom fournisseur = Equipementier
    // * Code fournisseur = Nom fournisseur
    // * Ref article fournisseur = Ref Equipementier
    // * Libellé article = Libellé Référence
    private function applySecondRule(ReferenceArticle $reference) {
        $oem = $this->getOEM($reference);
        $oemReference = $this->getOEMReference($reference);

        if($oem == null && $oemReference == null) {
            return;
        }

        foreach($reference->getArticlesFournisseur() as $article) {
            $concerned = $this->sanitize($article->getReference()) == "A DETERMINER" &&
                $this->sanitize($article->getLabel()) == "A DETERMINER";

            if($concerned) {
                $article->setFournisseur($oem)
                    ->setReference($oemReference)
                    ->setLabel($reference->getLibelle());

                break;
            }
        }
    }

    private function getOEM(ReferenceArticle $reference): ?Fournisseur {
        $superSanitized = $this->sanitize($reference->getFreeFields()[3] ?? null, true);
        $sanitized = $this->sanitize($reference->getFreeFields()[3] ?? null, false);
        if(!$sanitized) {
            return null;
        }

        if(!isset($this->suppliers[$superSanitized])) {
            $this->suppliers[$superSanitized] = (new Fournisseur())
                ->setNom($sanitized)
                ->setCodeReference($sanitized);
        }

        return $this->suppliers[$superSanitized];
    }

    private function getOEMReference(ReferenceArticle $reference): ?string {
        return $this->sanitize($reference->getFreeFields()[4] ?? null);
    }

    private function sanitize(?string $value, bool $stripAccents = false): ?string {
        if($value) {
            $sanitized = str_replace(array_keys(self::CONVERT), array_values(self::CONVERT), $value);

            if($stripAccents) {
                $sanitized = strtr(utf8_decode($sanitized), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
            }

            return strtoupper(trim($sanitized));
        } else {
            return null;
        }
    }

    public static function getGroups(): array {
        return ["cea-fields"];
    }

}
