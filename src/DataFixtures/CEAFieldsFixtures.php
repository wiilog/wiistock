<?php

namespace App\DataFixtures;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Repository\ArticleFournisseurRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class CEAFieldsFixtures extends Fixture implements FixtureGroupInterface {

    private const CONVERT = [
        "Ã‰" => "É"
    ];

    private $manager;
    /** @var Fournisseur[] */
    private $suppliers = [];

    private $toBeDeterminedSupplier = null;

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $this->cache($manager);
        $articleFournisseurRepository = $manager->getRepository(ArticleFournisseur::class);

        $this->flatMergeToBeDetermined($output, $manager, $articleFournisseurRepository);
        $output->writeln('----');

        $references = $manager->getRepository(ReferenceArticle::class)->findBy([
            "type" => 1
        ]);

        $count = 0;
        foreach($references as $reference) {
            $this->applyFirstRule($reference);
            $this->applySecondRule($reference);

            if(++$count % 5000 == 0) {
                $manager->flush();
                $output->writeln('Flush 5000 references');
            }
        }
        $output->writeln('Flush last references');
        $manager->flush();

        $output->writeln('----');

        $this->flatMergeToBeDetermined($output, $manager, $articleFournisseurRepository);
        $output->writeln('----');

        $referenceSupplierArticlesDuplicatesResult = $articleFournisseurRepository
            ->createQueryBuilder('article_fournisseur')
            ->select('article_fournisseur.reference AS reference')
            ->groupBy('article_fournisseur.reference')
            ->having('COUNT(article_fournisseur.reference) > 1')
            ->getQuery()
            ->getScalarResult();

        $supplierArticlesDuplicates = $articleFournisseurRepository->findBy([
            'reference' => array_column($referenceSupplierArticlesDuplicatesResult, "reference")
        ]);

        $treatedReferences = [];
        $countToDefine = 0;

        foreach($supplierArticlesDuplicates as $supplierArticle) {
            $countToDefine++;
            $referenceSanitized = $this->sanitize($supplierArticle->getReference());
            $reference = $supplierArticle->getReference();
            if (!isset($treatedReferences[$referenceSanitized])) {
                $treatedReferences[$referenceSanitized] = 0;
            }

            if (in_array($this->sanitize($reference, true), [$this->sanitize('CAISSE', true), $this->sanitize('CP-3.5 REF 100-90206', true)])) {

                $manager->flush();
                do {
                    $treatedReferences[$referenceSanitized]++;
                    $newReference = $reference . ' ' . $treatedReferences[$referenceSanitized];
                    $result = $articleFournisseurRepository->findOneBy(['reference' => $newReference]);
                }
                while(!empty($result));
            }
            else {
                $treatedReferences[$referenceSanitized]++;
                $newReference = $reference . ' ' . $treatedReferences[$referenceSanitized];
            }

            $supplierArticle
                ->setReference($newReference);

            if($countToDefine % 5000 == 0) {
                $output->writeln('Flush 5000 duplicates');
                $manager->flush();
            }
        }

        $output->writeln('Flush last duplicates');
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
        $oemReferenceInitial = $this->getOEMReference($reference);

        $oemReference = ($oemReferenceInitial === null || $oemReferenceInitial === '') ? 'A DETERMINER' : $oemReferenceInitial;

        if(($oem !== null || $oemReferenceInitial !== null)
            && !$this->getToBeDeterminedSupplierArticle($reference, $oem ? $oem->getCodeReference() : null)
            && !$this->getExistingArticle($reference)) {
            $supplier = $this->getToBeDeterminedSupplier($oem);
            $article = (new ArticleFournisseur())
                ->setReferenceArticle($reference)
                ->setReference($oemReference)
                ->setFournisseur($supplier)
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
        $oemReferenceInitial = $this->getOEMReference($reference);

        $oemReference = ($oemReferenceInitial === null || $oemReferenceInitial === '') ? 'A DETERMINER' : $oemReferenceInitial;

        if($oem !== null || $oemReferenceInitial !== null) {
            $article = $this->getToBeDeterminedSupplierArticle($reference, $oem ? $oem->getCodeReference() : null);
            if($article) {
                $article
                    ->setReference($oemReference)
                    ->setLabel($reference->getLibelle());
            }
        }
    }

    private function getToBeDeterminedSupplierArticle(ReferenceArticle $reference, $oem): ?ArticleFournisseur {

        foreach($reference->getArticlesFournisseur() as $article) {
            $supplier = $article->getFournisseur();
            $concerned = (
                (
                    ($oem && $this->sanitize($supplier->getCodeReference()) == $this->sanitize($oem))
                    || (!$oem && $this->sanitize($supplier->getCodeReference()) == "A_DETERMINER")
                )
                && $this->sanitize($article->getReference()) == "A DETERMINER"
            );

            if($concerned) {
                return $article;
            }
        }

        return null;
    }

    private function getToBeDeterminedSupplier($oem): ?Fournisseur {
        $supplier = null;
        if ($oem) {
            $supplier = $oem;
        }
        else if ($this->toBeDeterminedSupplier) {
            $supplier = $this->toBeDeterminedSupplier;
        }
        else {
            $indexSupplier = 0;
            $suppliers = array_values($this->suppliers);
            $supplierCount = count($suppliers);
            while(!$supplier && $indexSupplier < $supplierCount) {
                if ($suppliers[$indexSupplier]->getCodeReference() === 'A_DETERMINER') {
                    $supplier = $suppliers[$indexSupplier];
                }
                $indexSupplier++;
            }

            if ($this->toBeDeterminedSupplier) {
                $this->toBeDeterminedSupplier = $supplier;
            }
        }

        return $supplier;
    }

    private function getExistingArticle(ReferenceArticle $reference): ?ArticleFournisseur {
        $oem = $this->getOEM($reference);
        $oemReference = $this->getOEMReference($reference);

        foreach($reference->getArticlesFournisseur() as $article) {
            $supplier = $article->getFournisseur();
            $concerned = (
                (!$oem || $this->sanitize($supplier->getCodeReference()) == $oem->getCodeReference())
                && $this->sanitize($article->getReference()) == $oemReference
            );

            if($concerned) {
                return $article;
            }
        }

        return null;
    }

    private function getOEM(ReferenceArticle $reference): ?Fournisseur {
        $superSanitized = $this->sanitize($reference->getFreeFields()[3] ?? null, true);
        $sanitized = $this->sanitize($reference->getFreeFields()[3] ?? null, false);
        if(!$sanitized) {
            return null;
        }

        $superSanitized = ($superSanitized == 'A DETERMINER') ? 'A_DETERMINER' : $superSanitized;

        if(!isset($this->suppliers[$superSanitized])) {
            $this->suppliers[$superSanitized] = (new Fournisseur())
                ->setNom($sanitized)
                ->setCodeReference($sanitized);
        }

        return $this->suppliers[$superSanitized] ?? null;
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

    public function flatMergeToBeDetermined(ConsoleOutput $output, ObjectManager $manager, ArticleFournisseurRepository $articleFournisseurRepository) {

        $supplierArticlesToDefine = $articleFournisseurRepository->findBy([
            "reference" => [
                '.',
                '*',
                'a    determiner',
                'A DETER',
                ''
            ]
        ]);

        $countToDefine = 0;

        foreach($supplierArticlesToDefine as $supplierArticle) {
            $countToDefine++;

            $supplierArticle
                ->setReference('A DETERMINER');

            if($countToDefine % 5000 == 0) {
                $output->writeln('Flush 5000 "A DETERMINER"');
                $manager->flush();
            }
        }
        $output->writeln('Flush last "A DETERMINER"');
        $manager->flush();
    }

}
