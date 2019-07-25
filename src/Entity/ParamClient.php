<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParamClientRepository")
 */
class ParamClient
{
    const CEA_LETI = 'CEA LETI';
    const DOMAIN_NAME_CEA_PROD = 'https://cl2-prod.follow-gt.fr/';
    const DOMAIN_NAME_CEA_REC = 'https://cl1-rec.follow-gt.fr/';

	const SAFRAN_CERAMICS = 'SAFRAN CERAMICS';
    const DOMAIN_NAME_SAFRAN_REC = 'https://scs1-rec.follow-gt.fr/';
    const DOMAIN_NAME_SAFRAN_PROD = 'https://scs1-prod.follow-gt.fr/';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $client;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $domainName;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
    private $backgroundImg;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?string
    {
        return $this->client;
    }

    public function setClient(?string $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getDomainName(): ?string
    {
        return $this->domainName;
    }

    public function setDomainName(?string $domainName): self
    {
        $this->domainName = $domainName;

        return $this;
    }

    public function getBackgroundImg(): ?string
    {
        return $this->backgroundImg;
    }

    public function setBackgroundImg(?string $backgroundImg): self
    {
        $this->backgroundImg = $backgroundImg;

        return $this;
    }
}
