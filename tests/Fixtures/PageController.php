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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class PageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function html(): Response
    {
        return new Response($this->twig->render('page.html.twig'));
    }

    /**
     * A Markdown version at /guide.md, and at /guide for an Accept header preferring it; at /faq for that header only.
     * The "&" of the title checks the Markdown template is not escaped as HTML.
     */
    public function guide(Request $request): Response
    {
        return new Response($this->twig->render('guide.' . $request->getRequestFormat() . '.twig', ['title' => 'Guide & FAQ']));
    }

    public function json(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
