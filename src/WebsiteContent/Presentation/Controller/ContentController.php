<?php

declare(strict_types=1);

namespace App\WebsiteContent\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContentController extends AbstractController
{
    #[Route(
        path   : '/',
        name   : 'website_content.presentation.homepage',
        methods: [Request::METHOD_GET]
    )]
    public function homepageAction(): Response
    {
        return $this->render('@website_content.presentation/homepage.html.twig');
    }

    #[Route(
        path   : '/about',
        name   : 'website_content.presentation.about',
        methods: [Request::METHOD_GET]
    )]
    public function aboutAction(): Response
    {
        return $this->render('@website_content.presentation/about.html.twig');
    }
}
