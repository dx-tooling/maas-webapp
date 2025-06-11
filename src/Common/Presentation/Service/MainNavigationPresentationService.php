<?php

declare(strict_types=1);

namespace App\Common\Presentation\Service;

use EnterpriseToolingForSymfony\WebuiBundle\Entity\MainNavigationEntry;
use EnterpriseToolingForSymfony\WebuiBundle\Service\AbstractMainNavigationService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use ValueError;

readonly class MainNavigationPresentationService extends AbstractMainNavigationService
{
    public function __construct(
        RouterInterface               $router,
        RequestStack                  $requestStack,
        private ParameterBagInterface $parameterBag,
    ) {
        $symfonyEnvironment = $this->parameterBag->get('kernel.environment');

        if (!is_string($symfonyEnvironment)) {
            throw new ValueError('Symfony environment is not a string.');
        }

        parent::__construct(
            $router,
            $requestStack,
            $symfonyEnvironment
        );
    }

    public function secondaryMainNavigationIsPartOfDropdown(): bool
    {
        return true;
    }

    public function getPrimaryMainNavigationTitle(): string
    {
        return 'Main';
    }

    /**
     * @return MainNavigationEntry[]
     */
    public function getPrimaryMainNavigationEntries(): array
    {
        $entries = [
            $this->generateEntry(
                'Home',
                'website_content.presentation.homepage',
            )
        ];

        return $entries;
    }

    public function getSecondaryMainNavigationTitle(): string
    {
        return 'Other';
    }

    /**
     * @return MainNavigationEntry[]
     */
    protected function getSecondaryMainNavigationEntries(): array
    {
        $entries = [
            $this->generateEntry(
                'About',
                'website_content.presentation.about',
            )
        ];

        return $entries;
    }

    public function getFinalSecondaryMainNavigationEntries(): array
    {
        return $this->getSecondaryMainNavigationEntries();
    }

    public function getTertiaryMainNavigationTitle(): string
    {
        return 'Utilities';
    }

    /**
     * @return MainNavigationEntry[]
     */
    public function getTertiaryMainNavigationEntries(): array
    {
        $entries = [
            $this->generateEntry(
                'Living Styleguide',
                'webui.living_styleguide.show',
            )
        ];

        return $entries;
    }

    public function getBrandLogoHtml(): string
    {
        return '<strong class="etfswui-h1-gradient-span">Playwright MCP Cloud</strong>';
    }
}
