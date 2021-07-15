<?php

namespace App\Entity;

use App\Entity\IOT\AlertTemplate;
use App\Repository\NotificationRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NotificationRepository::class)
 */
class Notification {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToMany(targetEntity=Utilisateur::class, inversedBy="unreadNotifications")
     */
    private $users;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $triggered;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $content;

    /**
     * @ORM\ManyToOne(targetEntity=IOT\AlertTemplate::class, inversedBy="notifications")
     */
    private $template;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $source;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(Utilisateur $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
        }

        return $this;
    }

    public function removeUser(Utilisateur $user): self
    {
        $this->users->removeElement($user);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTriggered(): DateTime
    {
        return $this->triggered;
    }

    /**
     * @param mixed $triggered
     */
    public function setTriggered($triggered): self
    {
        $this->triggered = $triggered;
        return $this;
    }

    /**
     * @return AlertTemplate
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param AlertTemplate $template
     */
    public function setTemplate(AlertTemplate $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     */
    public function setSource($source): self
    {
        $this->source = $source;
        return $this;
    }



}
