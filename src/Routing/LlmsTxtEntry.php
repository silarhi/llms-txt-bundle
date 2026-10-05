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

namespace Silarhi\LlmsTxtBundle\Routing;

use function is_string;

use Silarhi\LlmsTxtBundle\Exception\InvalidRouteOptionException;

use function sprintf;

/**
 * The "llms_txt" option of a route: the page joins the file, with this title.
 *
 *     #[Route('/cgu', name: 'cgu', options: ['llms_txt' => new LlmsTxtEntry(title: 'CGU', section: Section::OPTIONAL)])]
 */
final readonly class LlmsTxtEntry
{
    private const KEYS = ['title', 'description', 'section'];

    /**
     * @param string      $title       the text of the link
     * @param string|null $description rendered after the link
     * @param string|null $section     the H2 section of the link, "llms_txt.route_section" by default
     *
     * @throws InvalidRouteOptionException on an empty title or section
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $section = null,
    ) {
        if ('' === trim($title)) {
            throw new InvalidRouteOptionException('An llms.txt entry needs a non-empty "title".');
        }

        if (null !== $section && '' === trim($section)) {
            throw new InvalidRouteOptionException('The "section" of an llms.txt entry must be a non-empty string, or null for "llms_txt.route_section".');
        }
    }

    /**
     * The array form of the option, for the routes YAML or XML declare: {title: …, description: …, section: …}.
     *
     * @param array<mixed> $option
     *
     * @throws InvalidRouteOptionException on unknown keys or values of the wrong type
     */
    public static function fromArray(array $option): self
    {
        $unknownKeys = array_diff(array_keys($option), self::KEYS);
        if ([] !== $unknownKeys) {
            throw new InvalidRouteOptionException(sprintf('An llms.txt entry has unknown keys "%s", allowed: "%s".', implode('", "', $unknownKeys), implode('", "', self::KEYS)));
        }

        $title = $option['title'] ?? null;
        $description = $option['description'] ?? null;
        $section = $option['section'] ?? null;
        if (!is_string($title)) {
            throw new InvalidRouteOptionException('An llms.txt entry needs a non-empty "title".');
        }
        if (null !== $description && !is_string($description)) {
            throw new InvalidRouteOptionException(sprintf('The "description" of an llms.txt entry must be a string, "%s" given.', get_debug_type($description)));
        }
        if (null !== $section && !is_string($section)) {
            throw new InvalidRouteOptionException(sprintf('The "section" of an llms.txt entry must be a non-empty string, "%s" given.', get_debug_type($section)));
        }

        return new self($title, $description, $section);
    }
}
