<?php

namespace App\DataFixtures;

use App\Entity\ParamClient;
use App\Repository\ParamClientRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class ceaRecFixture extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ParamClientRepository
     */
    private $paramClientRepository;

    public function __construct(ParamClientRepository $paramClientRepository)
    {
        $this->paramClientRepository = $paramClientRepository;
    }

    public function load(ObjectManager $manager)
    {
        $paramClient = $this->paramClientRepository->findOne();
        $paramClient->setClient(ParamClient::CEA_LETI);
        $paramClient->setDomainName(ParamClient::CEA_REC);
        $manager->flush();
    }

    public static function getGroups():array {
        return ['cea-rec'];
    }

}
