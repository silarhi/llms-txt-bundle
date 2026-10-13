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

namespace Silarhi\LlmsTxtBundle\Service;

use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Symfony\Component\Filesystem\Exception\IOException;

interface DumperInterface
{
    /**
     * Writes the llms.txt file into the directory, the configured dump directory by default.
     *
     * @return string the path of the written file
     *
     * @throws MissingTitleException when the document has no title, before anything is written
     * @throws IOException           when the directory or the file cannot be written
     */
    public function dump(?string $directory = null): string;
}
