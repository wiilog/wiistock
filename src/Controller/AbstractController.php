<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\Cache\CacheService;
use App\Service\FormatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Contracts\Service\Attribute\Required;

class AbstractController extends SymfonyAbstractController {


    protected const GET = "GET";
    protected const POST = "POST";
    protected const PUT = "PUT";
    protected const PATCH = "PATCH";
    protected const DELETE = "DELETE";

    protected const IS_XML_HTTP_REQUEST = "request.isXmlHttpRequest()";

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public FormatService $formatService;

    public function getCache(): CacheService {
        return $this->cacheService;
    }

    public function getFormatter(): FormatService {
        return $this->formatService;
    }

    public function getUser(): ?Utilisateur {
        return parent::getUser();
    }

}
