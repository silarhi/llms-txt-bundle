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

namespace Silarhi\LlmsTxtBundle\Attribute;

use Attribute;

/**
 * Registers a method returning the links of a section, the way #[AsEventListener] registers a listener:
 *
 *     #[AsLlmsTxtLinkProvider(section: 'Products')]
 *     public function products(): iterable // of Link, read when the file is rendered
 *
 * On a class, the provider is its __invoke() method, or the one "method" names. The attribute adds the
 * "llms_txt.link_provider" tag, which services can also carry without it.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsLlmsTxtLinkProvider
{
    /**
     * @param string      $section  the H2 section of the links
     * @param string|null $method   on a class, the method returning the links, "__invoke" by default
     * @param int         $priority the priority of the LlmsTxtPopulateEvent listener calling it: sections keep the
     *                              order of their first use, and the route options listener runs at 128
     */
    public function __construct(
        public string $section,
        public ?string $method = null,
        public int $priority = 0,
    ) {
    }
}
