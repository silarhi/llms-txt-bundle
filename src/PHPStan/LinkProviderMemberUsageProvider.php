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

namespace Silarhi\LlmsTxtBundle\PHPStan;

use Override;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Silarhi\LlmsTxtBundle\Attribute\AsLlmsTxtLinkProvider;

/**
 * The bundle calls the #[AsLlmsTxtLinkProvider] methods: shipmonk/dead-code-detector must not report them as dead. On
 * a method, the attribute marks it; on a class, it marks the method it names, __invoke() by default.
 *
 * Registered by shipmonk.neon, which applications using the detector include: the bundle cannot register it itself,
 * since this class needs the detector.
 */
final class LinkProviderMemberUsageProvider extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        if ([] !== $method->getAttributes(AsLlmsTxtLinkProvider::class)) {
            return VirtualUsageData::withNote('llms.txt link provider, via #[AsLlmsTxtLinkProvider]');
        }

        foreach ($method->getDeclaringClass()->getAttributes(AsLlmsTxtLinkProvider::class) as $attribute) {
            if (0 === strcasecmp($attribute->newInstance()->method ?? '__invoke', $method->getName())) {
                return VirtualUsageData::withNote('llms.txt link provider, via #[AsLlmsTxtLinkProvider] on its class');
            }
        }

        return null;
    }
}
