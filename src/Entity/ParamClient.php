<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParamClientRepository")
 */
class ParamClient
{
	const CEA_LETI = 'CEA LETI';
	const SAFRAN_CERAMICS = 'SAFRAN CERAMICS';

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
