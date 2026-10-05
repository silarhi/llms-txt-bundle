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

/**
 * The content of an llms.txt file (https://llmstxt.org): an H1 title, a blockquote summary, free Markdown details,
 * then H2 sections of links. Filled by the listeners of LlmsTxtPopulateEvent.
 */
final class Document
{
    /** @var array<string, Section> */
    private array $sections = [];

    public function __construct(
        private ?string $title = null,
        private ?string $summary = null,
        private ?string $details = null,
    ) {
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    /**
     * The section of this name, created on first use: sections keep the order of their first use.
     */
    public function section(string $name): Section
    {
        return $this->sections[$name] ??= new Section($name);
    }

    public function addLink(string $section, string $url, string $title, ?string $description = null): static
    {
        $this->section($section)->addLink(new Link($url, $title, $description));

        return $this;
    }

    /**
     * @param iterable<Link> $links read when the file is rendered, see Section::addLinks()
     */
    public function addLinks(string $section, iterable $links): static
    {
        $this->section($section)->addLinks($links);

        return $this;
    }

    /**
     * @return list<Section>
     */
    public function getSections(): array
    {
        return array_values($this->sections);
    }
}
