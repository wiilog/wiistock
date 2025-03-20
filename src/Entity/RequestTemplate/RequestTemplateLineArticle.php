<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Article;

class RequestTemplateLineArticle extends RequestTemplateLine{
    private ?Article $article = null;

    public function getArticle(): ?Article{
        return $this->article;
    }

    public function setArticle(?Article $article): void{
        $this->article = $article;
    }
}
