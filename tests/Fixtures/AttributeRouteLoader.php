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

namespace Silarhi\LlmsTxtBundle\Tests\Fixtures;

use Override;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;

/** The attribute loader of the applications, without FrameworkBundle's: it only reads the routes */
final class AttributeRouteLoader extends AttributeClassLoader
{
    #[Override]
    protected function configureRoute(Route $route, ReflectionClass $class, ReflectionMethod $method, object $attr): void
    {
    }
}
