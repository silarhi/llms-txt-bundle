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

namespace Silarhi\LlmsTxtBundle\Exception;

use LogicException;

/**
 * The H1 title is the only section the spec requires.
 */
final class MissingTitleException extends LogicException implements LlmsTxtExceptionInterface
{
    public function __construct()
    {
        parent::__construct('An llms.txt file needs a title: set the "llms_txt.title" option, or call Document::setTitle() from a LlmsTxtPopulateEvent listener.');
    }
}
