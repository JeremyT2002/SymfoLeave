<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TeamFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Name'])
            ->add('lead', EntityType::class, [
                'label' => 'Teamleitung',
                'class' => User::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '– keine –',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('u')->orderBy('u.name'),
                'help' => 'Die Person benötigt zusätzlich die Rolle „Teamleitung“.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Team::class]);
    }
}
