<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Entity\TransferRequest;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddArticleToTransferType extends AbstractType {

    private $manager;

    public function __construct(EntityManagerInterface $manager) {
        $this->manager = $manager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder
            ->add("reference", EntitySelectType::class, [
                "class" => ReferenceArticle::class,
                "choice_name" => "libelle",
                "placeholder" => "Sélectionnez une référence article...",
            ])
            ->add("submit", SubmitType::class, [
                "label" => "Ajouter l'article",
                "attr" => ["class" => "btn data btn-primary"]
            ]);

        $builder->get("reference")->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) use ($builder) {
            $form = $event->getForm()->getParent();
            $data = $event->getData();

            $reference = $this->manager->getRepository(ReferenceArticle::class)->find($data);

            if($reference->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                dump("ok?");
                $form->add("article", EntityType::class, [
                    "class" => Article::class,
                    "choice_label" => "barCode",
                    "query_builder" => function(ArticleRepository $repository) use ($reference) {
                        return $repository->createQueryBuilder("a")
                            ->join("a.articleFournisseur", "af")
                            ->where("af.referenceArticle = :reference")
                            ->setParameter("reference", $reference);
                    }
                ]);
            }
        });
    }

}
