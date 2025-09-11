<?php

declare(strict_types=1);

namespace App\WebsiteContent\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ContentController extends AbstractController
{
    #[Route(
        path   : '/',
        name   : 'website_content.presentation.root',
        methods: [Request::METHOD_GET]
    )]
    public function rootAction(): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('account.presentation.sign_in');
        }

        return $this->redirectToRoute('mcp_instances_management.presentation.dashboard');
    }
}
