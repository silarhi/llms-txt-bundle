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

interface DumperInterface
{
    /**
     * Writes the llms.txt file into the directory, the configured dump directory by default.
     *
     * @return string the path of the written file
     */
    public function dump(?string $directory = null): string;
}
