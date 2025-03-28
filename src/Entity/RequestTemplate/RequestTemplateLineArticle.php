<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Article;

/**
 * No needs to be persisted, only used to pass data to the template for Sleeping Stock Form Submission
 * @see \IndexController::submit
 */
class RequestTemplateLineArticle extends RequestTemplateLine{
    private ?Article $article = null;

    public function getArticle(): ?Article{
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        $this->article = $article;

        return $this;
    }
}
