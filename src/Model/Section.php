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

namespace Silarhi\LlmsTxtBundle\Model;

use Generator;

/**
 * An H2 section of the file: a list of links.
 */
final class Section
{
    /**
     * The spec gives this section a meaning of its own: its links can be skipped when a shorter context is needed.
     */
    public const OPTIONAL = 'Optional';

    /** @var list<Link|iterable<Link>> */
    private array $entries = [];

    public function __construct(
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isOptional(): bool
    {
        return self::OPTIONAL === $this->name;
    }

    public function addLink(Link $link): static
    {
        $this->entries[] = $link;

        return $this;
    }

    /**
     * Adds links read only when the file is rendered, one at a time: with a generator, a section of any size keeps
     * the memory flat. A generator can be read once: the section can be rendered once.
     *
     * @param iterable<Link> $links
     */
    public function addLinks(iterable $links): static
    {
        $this->entries[] = $links;

        return $this;
    }

    /**
     * @return Generator<int, Link>
     */
    public function getLinks(): Generator
    {
        foreach ($this->entries as $entry) {
            if ($entry instanceof Link) {
                yield $entry;

                continue;
            }

            // not "yield from": the keys of the iterables would collide
            foreach ($entry as $link) {
                yield $link;
            }
        }
    }
}
