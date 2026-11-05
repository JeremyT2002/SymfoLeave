<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Team;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

final class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.name'])
            ->add('email', EmailType::class, ['label' => 'form.email'])
            ->add('team', EntityType::class, [
                'label' => 'form.team',
                'class' => Team::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'form.no_team',
            ])
            ->add('storedRoles', ChoiceType::class, [
                'label' => 'form.roles',
                'property_path' => 'roles',
                'choices' => ['role.team_lead' => User::ROLE_TEAM_LEAD, 'role.admin' => User::ROLE_ADMIN],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'getter' => static fn (User $u): array => $u->getStoredRoles(),
            ])
            ->add('annualVacationDays', IntegerType::class, [
                'label' => 'form.vacation_days',
                'attr' => ['min' => 0, 'max' => 60],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['require_password'] ? 'form.password' : 'form.new_password',
                'mapped' => false,
                'required' => $options['require_password'],
                'constraints' => [new Length(min: 6, max: 4096)],
                'attr' => ['autocomplete' => 'new-password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'require_password' => false]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
