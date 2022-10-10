<?php

namespace App\Command;

use App\Entity\Language;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class FakeTranslationsGenerateCommand extends Command {

    protected static $defaultName = 'app:generate:fake-translations';

    #[Required]
    public EntityManagerInterface $entityManager;

    public function __construct() {
        parent::__construct(self::$defaultName);
    }

    protected function configure() {
        $this
            ->addArgument('language-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $languageRepository = $this->entityManager->getRepository(Language::class);
        $translationRepository = $this->entityManager->getRepository(TranslationSource::class);
        $languageId = $input->getArgument('language-id');
        $language = $languageId
            ? $languageRepository->find($languageId)
            : null;
        if (!$language) {
            throw new \Exception('Invalid language id');
        }

        $translationSources = $translationRepository->findAll();

        /** @var TranslationSource $translationSource */
        foreach($translationSources as $translationSource) {
            $original = $translationSource
                ->getTranslationIn(Language::FRENCH_SLUG, Language::FRENCH_DEFAULT_SLUG)
                ?->getTranslation();
            $languageTranslation = $translationSource->getTranslationIn($language);
            if (!$languageTranslation || $original === $languageTranslation) {
                $newTranslation = new Translation();
                $newTranslation
                    ->setLanguage($language)
                    ->setTranslation('TR ==== ' . $original)
                    ->setSource($translationSource);
                $this->entityManager->persist($newTranslation);
            }
        }

        $this->entityManager->flush();

        return 0;
    }

}
