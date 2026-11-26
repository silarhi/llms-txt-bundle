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

namespace Silarhi\LlmsTxtBundle\Debug;

use JsonSerializable;
use Override;

/**
 * A listener of LlmsTxtPopulateEvent, as debug:llms-txt lists it.
 *
 * @internal
 */
final readonly class Source implements JsonSerializable
{
    public const PROVIDER = 'provider';

    public const ROUTE_OPTIONS = 'route options';

    public const LISTENER = 'listener';

    /**
     * @param self::PROVIDER|self::ROUTE_OPTIONS|self::LISTENER $kind
     * @param string|null                                       $section the section of a link provider
     */
    public function __construct(
        public int $priority,
        public string $kind,
        public string $name,
        public ?string $section,
    ) {
    }

    /**
     * @return array{priority: int, kind: string, name: string, section: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['priority' => $this->priority, 'kind' => $this->kind, 'name' => $this->name, 'section' => $this->section];
    }
}
