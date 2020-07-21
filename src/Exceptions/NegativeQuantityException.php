<?php


namespace App\Exceptions;


use App\Entity\Article;
use App\Entity\ReferenceArticle;
use Exception;

class NegativeQuantityException extends Exception
{
    /** @var ReferenceArticle|Article|null */
    private $article;

    /**
     * NegativeQuantityException constructor.
     * @param ReferenceArticle|Article $article
     */
    public function __construct($article) {
        parent::__construct();
        $this->article = $article;
    }

    /**
     * @return Article|ReferenceArticle|null
     */
    public function getArticle() {
        return $this->article;
    }

}
