<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoMode;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_projects');
        }

        $error = $authentication->getLastAuthenticationError();

        return $this->render('security/login.html.twig', [
            'last_username' => $authentication->getLastUsername(),
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/demo', name: 'app_demo', methods: ['GET'])]
    public function demo(DemoMode $demo, UserRepository $users, Security $security): Response
    {
        $user = $demo->isEnabled() ? $users->findOneByUsername(DemoMode::USERNAME) : null;
        if (null === $user) {
            throw $this->createNotFoundException();
        }

        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_projects');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('The firewall handles logout.');
    }
}
