<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class WebUiTestHelper
{
    public static function createTwigEnvironment(
        string $featureFolderName
    ): Environment {
        $featureSnakeCaseName = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $featureFolderName));
        $templateDir          = __DIR__ . "/../../src/$featureFolderName/Presentation/Resources/templates";

        $fs = new FilesystemLoader();

        $fs->addPath($templateDir, "$featureSnakeCaseName.presentation");

        $array = new ArrayLoader([
            '@Webui/base_appshell.html.twig' => <<<'TWIG'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Base</title></head>
<body>
{% block body %}{% endblock %}
</body>
</html>
TWIG,
        ]);

        $loader = new ChainLoader([$array, $fs]);
        $twig   = new Environment($loader);

        $twig->addFunction(new TwigFunction('path', function (string $name, array $params = []): string {
            return '/' . $name;
        }));

        $twig->addFunction(new TwigFunction('component', function (string $name, array $props = []): string {
            return '<div data-component="' . htmlspecialchars($name, ENT_QUOTES) . '"></div>';
        }, ['is_safe' => ['html']]));

        $app = new class {
            /**
             * @return array<int,string>
             */
            public function flashes(string $type): array
            {
                return [];
            }
        };
        $twig->addGlobal('app', $app);

        return $twig;
    }
}
