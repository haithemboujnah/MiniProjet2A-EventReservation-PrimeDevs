<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixtures extends Fixture implements FixtureGroupInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public static function getGroups(): array
    {
        return ['admin'];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new Admin();
        $admin->setUsername('admin');
        $admin->setEmail('admin@gmail.com');
        $admin->setFullName('System Administrator');
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin123')
        );
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $manager->persist($admin);

        $admin2 = new Admin();
        $admin2->setUsername('manager');
        $admin2->setEmail('manager@gmail.com');
        $admin2->setFullName('Event Manager');
        $admin2->setPassword(
            $this->passwordHasher->hashPassword($admin2, 'manager123')
        );
        $admin2->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin2);

        $manager->flush();

        $this->addReference('admin-user', $admin);
        $this->addReference('manager-user', $admin2);
    }
}