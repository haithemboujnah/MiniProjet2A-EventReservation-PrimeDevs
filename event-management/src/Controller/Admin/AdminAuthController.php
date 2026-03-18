<?php

namespace App\Controller\Admin;

use App\Entity\Admin;
use App\Form\Admin\AdminLoginType;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin')]
class AdminAuthController extends AbstractController
{
    #[Route('/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If already logged in as admin, redirect to dashboard
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $form = $this->createForm(AdminLoginType::class);

        return $this->render('admin/login.html.twig', [
            'form' => $form->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/create-admin', name: 'admin_create')]
    public function createAdmin(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // This route should be disabled in production or protected by IP restriction
        // For now, we'll check if any admin exists
        $adminRepo = $entityManager->getRepository(Admin::class);
        $adminCount = $adminRepo->count([]);

        if ($adminCount > 0 && $this->getParameter('kernel.environment') === 'prod') {
            throw $this->createNotFoundException('Admin creation is disabled');
        }

        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
            $password = $request->request->get('password');
            $email = $request->request->get('email');
            $fullName = $request->request->get('full_name');

            $admin = new Admin();
            $admin->setUsername($username);
            $admin->setEmail($email);
            $admin->setFullName($fullName);
            $admin->setPassword($passwordHasher->hashPassword($admin, $password));
            $admin->setRoles(['ROLE_ADMIN']);

            $entityManager->persist($admin);
            $entityManager->flush();

            $this->addFlash('success', 'Admin account created successfully!');
            return $this->redirectToRoute('admin_login');
        }

        return $this->render('admin/create_admin.html.twig');
    }
}