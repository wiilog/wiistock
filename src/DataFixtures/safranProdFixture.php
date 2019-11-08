<?php

namespace App\DataFixtures;

use App\Entity\ParamClient;
use App\Repository\ParamClientRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class safranProdFixture extends Fixture implements FixtureGroupInterface
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

        if (!$paramClient) {
        	$paramClient = new ParamClient();
        	$manager->persist($paramClient);
		}

        $paramClient
			->setClient(ParamClient::SAFRAN_CERAMICS)
			->setDomainName(ParamClient::DOMAIN_NAME_SAFRAN_PROD)
			->setNbWeeksInventory(1);
        $manager->flush();
    }

    public static function getGroups():array {
        return ['safran-prod'];
    }

}
