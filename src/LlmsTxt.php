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

namespace Silarhi\LlmsTxtBundle;

/**
 * The names of the files, routes and format. LlmsTxtBundle repeats them, but loading the bundle class needs
 * symfony/dependency-injection: the services read them here, and so does an application without the bundle.
 */
final class LlmsTxt
{
    public const FILENAME = 'llms.txt';

    public const ROUTE = 'llms_txt';

    public const FULL_FILENAME = 'llms-full.txt';

    public const FULL_ROUTE = 'llms_txt_full';

    /**
     * The request format of the Markdown versions of the pages ("llms_txt.markdown_versions"), served as text/markdown.
     */
    public const MARKDOWN_FORMAT = 'md';

    private function __construct()
    {
    }
}
