<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ChampsPersonnalises;

class CustomField
{
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function edit($cible, $field, $value)
    {
        $rawSql = "UPDATE " . $cible . " JSON_MODIFY(custome, '$[*]." . $field . "', '" . $value . "') WHERE JSON_CONTAINS(custom->'$[*]." . $field . "', '" . $value . "') ";

        $res = $em->getConnection()->prepare($rawSql);
        $res->execute();
        $res = $res->fetchAll();
    }

    public function getId($cible, $field, $value)
    {
        $rawSql = "SELECT id AS id FROM " . $cible . " WHERE JSON_CONTAINS(custom->'$[*]." . $field . "', '" . $value . "') ";

        $res = $em->getConnection()->prepare($rawSql);
        $res->execute();
        $res = $res->fetchAll();
        return ($res);
    }
}