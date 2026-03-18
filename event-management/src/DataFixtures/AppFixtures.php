<?php

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create test user
        $user = new User();
        $user->setEmail('   ');
        $user->setFullName('haithem');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'haithem123'));
        $manager->persist($user);

        // Create events
        $events = [
            [
                'title' => 'Summer Music Festival',
                'description' => 'Enjoy a day of live music from top artists',
                'date' => new \DateTime('+2 weeks'),
                'location' => 'Central Park',
                'seats' => 500,
                'image' => 'https://offloadmedia.feverup.com/secretnyc.co/wp-content/uploads/2025/08/04154957/Andrew-Rauner-1024x683.jpeg'
            ],
            [
                'title' => 'Basketball Tournament',
                'description' => 'Amateur basketball tournament for all levels',
                'date' => new \DateTime('+1 month'),
                'location' => 'Sports Arena',
                'seats' => 200,
                'image' => 'https://images.sidearmdev.com/crop?url=https%3A%2F%2Fdxbhsrqyrr690.cloudfront.net%2Fsidearm.nextgen.sites%2Fgoduke.com%2Fimages%2F2026%2F3%2F12%2FCH_DUKE_POSTGAME_64032_cdzBa.JPG&width=720&height=405&type=webp'
            ],
            [
                'title' => 'Yoga in the Park',
                'description' => 'Morning yoga session for beginners and experts',
                'date' => new \DateTime('+1 week'),
                'location' => 'Botanical Garden',
                'seats' => 50,
                'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRESPehT3nBbsY99rzsNuG8w_7iJUiXA7Gg5g&s'
            ],
            [
                'title' => '5K Fun Run',
                'description' => 'Join a community 5K run suitable for all ages and levels',
                'date' => new \DateTime('+3 weeks'),
                'location' => 'Riverfront Park',
                'seats' => 150,
                'image' => 'https://api.army.mil/e2/c/images/2018/03/29/511787/max1200.jpg'
            ],
            [
                'title' => 'Rock Climbing Workshop',
                'description' => 'Learn rock climbing techniques in a safe environment',
                'date' => new \DateTime('+2 months'),
                'location' => 'Mountain Gym',
                'seats' => 30,
                'image' => 'https://climbbase5.com/wp-content/uploads/2025/07/ClimbBase5-Workshop2-SM.png'
            ],
            [
                'title' => 'Soccer Skills Camp',
                'description' => 'Improve your soccer skills with professional coaches',
                'date' => new \DateTime('+4 weeks'),
                'location' => 'Greenfield Stadium',
                'seats' => 100,
                'image' => 'https://www.allsportskids.com/wp-content/uploads/2021/04/evening-soccer-classes-1024x683.jpg'
            ],
            [
                'title' => 'Kayaking Adventure',
                'description' => 'Explore the river on a guided kayaking trip',
                'date' => new \DateTime('+1 month'),
                'location' => 'Sunset River',
                'seats' => 25,
                'image' => 'https://www.bkadventure.com/wp-content/uploads/2025/01/IMG_7613.jpg'
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
            $manager->persist($event);
        }

        $manager->flush();
    }
}