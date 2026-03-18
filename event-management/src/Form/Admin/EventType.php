<?php

namespace App\Form\Admin;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\NotBlank;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Event Title',
                'attr' => [
                    'placeholder' => 'Enter event title',
                    'class' => 'form-control-lg'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an event title'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Enter event description',
                    'rows' => 6,
                    'class' => 'form-control-lg'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter an event description'])
                ]
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Date & Time',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control-lg',
                    'min' => (new \DateTime())->format('Y-m-d\TH:i')
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a date']),
                    new \Symfony\Component\Validator\Constraints\GreaterThan([
                        'value' => 'today',
                        'message' => 'Event date must be in the future'
                    ])
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'attr' => [
                    'placeholder' => 'Enter event location',
                    'class' => 'form-control-lg'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a location'])
                ]
            ])
            ->add('seats', IntegerType::class, [
                'label' => 'Total Seats',
                'attr' => [
                    'placeholder' => 'Enter number of seats',
                    'min' => 1,
                    'class' => 'form-control-lg'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter number of seats']),
                    new Positive(['message' => 'Number of seats must be positive'])
                ]
            ])
            ->add('imageUrl', UrlType::class, [
                'label' => 'Image URL',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/image.jpg',
                    'class' => 'form-control-lg'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}