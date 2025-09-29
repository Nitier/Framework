<?php

declare(strict_types=1);

namespace Test\Http\Session;

use Framework\Http\Session\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class SessionManagerTest extends TestCase
{
    public function testSetAndGetValues(): void
    {
        $session = new SessionManager();
        $session->start();
        $session->set('foo', 'bar');

        self::assertTrue($session->has('foo'));
        self::assertSame('bar', $session->get('foo'));
        self::assertSame('default', $session->get('missing', 'default'));
    }

    public function testPullRemovesValue(): void
    {
        $session = new SessionManager();
        $session->start();
        $session->set('token', 'abc');

        self::assertSame('abc', $session->pull('token'));
        self::assertFalse($session->has('token'));
    }

    public function testInvalidateClearsDataAndRegeneratesId(): void
    {
        $session = new SessionManager();
        $session->start();
        $session->set('key', 'value');
        $originalId = $session->id();

        $session->invalidate();

        self::assertNotSame($originalId, $session->id());
        self::assertFalse($session->has('key'));
    }
}
