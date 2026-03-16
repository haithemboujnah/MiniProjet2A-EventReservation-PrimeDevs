<?php
// src/Command/LoadSampleEventsCommand.php

namespace App\Command;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:load-sample-events')]
class LoadSampleEventsCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $events = [
            [
                'id' => 1,
                'title' => 'Summer Beach Volleyball Tournament',
                'description' => 'Join us for an exciting day of beach volleyball! Teams of 4-6 players compete for prizes. All skill levels welcome.',
                'date' => new \DateTime('+2 weeks 14:00'),
                'location' => 'Main Beach, Santa Monica',
                'seats' => 50,
                'image' => null
            ],
            [
                'id' => 2,
                'title' => 'Yoga in the Park',
                'description' => 'Start your weekend with a relaxing yoga session in Central Park. Bring your own mat and water. All levels welcome.',
                'date' => new \DateTime('+5 days 09:00'),
                'location' => 'Central Park, NYC',
                'seats' => 30,
                'image' => null
            ],
            [
                'id' => 3,
                'title' => 'Hiking Adventure: Mountain Trail',
                'description' => 'Explore beautiful mountain trails with experienced guides. Moderate difficulty. Transportation included.',
                'date' => new \DateTime('+3 weeks 08:00'),
                'location' => 'Rocky Mountains, Colorado',
                'seats' => 20,
                'image' => null
            ],
            [
                'id' => 4,
                'title' => 'Basketball Clinic for Beginners',
                'description' => 'Learn the basics of basketball from professional coaches. Dribbling, shooting, and teamwork skills covered.',
                'date' => new \DateTime('+1 week 10:00'),
                'location' => 'Community Sports Center',
                'seats' => 25,
                'image' => null
            ]
        ];

        foreach ($events as $eventData) {
            $event = new Event();
            $event->setTitle($eventData['title']);
            $event->setDescription($eventData['description']);
            $event->setDate($eventData['date']);
            $event->setLocation($eventData['location']);
            $event->setSeats($eventData['seats']);
            $event->setImageUrl($eventData['image']);
            
            $this->entityManager->persist($event);
        }

        $this->entityManager->flush();
        
        $output->writeln('Sample events loaded successfully!');
        
        return Command::SUCCESS;
    }
}