<?php

namespace App\Service;

use App\Entity\TagTemplate;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class TagTemplateService
{

    public function serializeTagTemplates(EntityManagerInterface $entityManager, string $module)
    {
        $templates = $entityManager->getRepository(TagTemplate::class)->findBy([
            'module' => $module
        ]);

        return Stream::from($templates)
            ->map(fn(TagTemplate $tagTemplate) => $tagTemplate->getId())
            ->toArray();
    }
}
