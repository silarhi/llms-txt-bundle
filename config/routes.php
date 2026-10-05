<?php

declare(strict_types=1);

/*
 * This file is part of the LLMs.txt Bundle package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add(LlmsTxtBundle::ROUTE, '/' . LlmsTxtBundle::FILENAME)
        ->controller('llms_txt.controller')
        ->methods(['GET', 'HEAD']);
};
