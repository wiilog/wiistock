<?php


namespace App\Entity\Traits;

use App\Entity\Utilisateur;
use App\Service\CacheService;
use App\Service\FormatService;
use Symfony\Contracts\Service\Attribute\Required;

trait AbstractControllerTrait {

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

    private ?Utilisateur $user = null;

    public function setUser(?Utilisateur $user): void {
        $this->user = $user;
    }

    public function getCache(): CacheService {
        return $this->cacheService;
    }

    public function getFormatter(): FormatService {
        return $this->formatService;
    }
}
