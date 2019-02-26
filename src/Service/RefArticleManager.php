<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ReferenceArticle;

class RefArticleManager
{
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function create($index)
    {
        $batchSize = 50;

        $em = $this->em;
        // $referencesArticle = new ReferencesArticles();
        // $referencesArticle->setLibelle("Reference " . $index);
        // $referencesArticle->setReference(rand(1000000, 10000000));
        // $array1 = array(
        //     "name" => "Custom 1",
        //     "value" => '' . rand(1000000, 10000000),
        // );
        // $array2 = array(
        //     "name" => "Custom 2",
        //     "value" => 'canard '. rand(100, 1000),
        // );
        // $array = array();
        // array_push($array, $array1);
        // array_push($array, $array2);
        // $referencesArticle->setCustom($array);
        // $em->persist($referencesArticle);
        // if (($index % $batchSize) === 0) {
        //     $em->flush();
        //     $em->clear();
        // }

        $rawSql = "INSERT INTO `reference_article` (`libelle`, `photo_article`, `reference`, `custom`) VALUES ('Reference ". $index ."', NULL, '". rand(1000000, 10000000) ."', '[{\"name\": \"Custom 1\", \"value\": ". rand(1000000, 10000000) ."}, {\"name\": \"Custom 2\", \"value\": \"canard ". rand(100, 1000) ."\"}]');";
        $stmt = $em->getConnection()->prepare($rawSql);
        $stmt->execute([]);

        return "Référence article n°" . $index . " créée";
    }

}