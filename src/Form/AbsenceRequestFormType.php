<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Absence;
use App\Entity\AbsenceType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AbsenceRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $live = ['data-action' => 'change->working-days#update'];
        $builder
            ->add('type', EntityType::class, [
                'label' => 'form.type',
                'class' => AbsenceType::class,
                'choice_label' => 'name',
                'choice_translation_domain' => 'absence_types',
                'placeholder' => 'form.choose',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('t')->orderBy('t.id'),
            ])
            ->add('startDate', DateType::class, [
                'label' => 'form.start',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => $live + ['data-working-days-target' => 'start'],
            ])
            ->add('startHalfDay', CheckboxType::class, [
                'label' => 'form.start_half',
                'required' => false,
                'attr' => $live + ['data-working-days-target' => 'startHalf'],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'form.end',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => $live + ['data-working-days-target' => 'end'],
            ])
            ->add('endHalfDay', CheckboxType::class, [
                'label' => 'form.end_half',
                'required' => false,
                'attr' => $live + ['data-working-days-target' => 'endHalf'],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'form.comment',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Absence::class]);
    }
}
