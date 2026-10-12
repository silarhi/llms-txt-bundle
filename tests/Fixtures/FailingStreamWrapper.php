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

/**
 * A filesystem which creates files, then fails to reopen ("failing-open://") or to write ("failing-write://") them.
 */
final class FailingStreamWrapper
{
    public const OPEN = 'failing-open';
    public const WRITE = 'failing-write';

    /** @var resource|null */
    public $context;

    public static function register(): void
    {
        stream_wrapper_register(self::OPEN, self::class);
        stream_wrapper_register(self::WRITE, self::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister(self::OPEN);
        stream_wrapper_unregister(self::WRITE);
    }

    public function stream_open(string $path, string $mode): bool
    {
        // "x+" is how Filesystem::tempnam() creates a file on a stream wrapper
        return 'x+' === $mode || !str_starts_with($path, self::OPEN . '://');
    }

    public function stream_write(): false
    {
        return false;
    }

    public function mkdir(): bool
    {
        return true;
    }

    public function url_stat(): false
    {
        return false;
    }
}
