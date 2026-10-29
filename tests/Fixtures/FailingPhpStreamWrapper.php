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
 * Takes the place of the "php" stream wrapper, so that php://temp fails to open (OPEN) or to write (WRITE).
 */
final class FailingPhpStreamWrapper
{
    public const OPEN = 'open';
    public const WRITE = 'write';

    /** @var resource|null */
    public $context;

    private static string $failure = self::OPEN;

    public static function register(string $failure): void
    {
        self::$failure = $failure;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_restore('php');
    }

    public function stream_open(): bool
    {
        return self::WRITE === self::$failure;
    }

    public function stream_write(): false
    {
        return false;
    }
}
