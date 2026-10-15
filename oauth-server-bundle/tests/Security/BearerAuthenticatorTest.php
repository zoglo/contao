<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\OAuthServerBundle\Tests\Security;

use Contao\OAuthServerBundle\Security\AccessTokenValidator;
use Contao\OAuthServerBundle\Security\BearerAuthenticator;
use Contao\OAuthServerBundle\Security\BearerChallenge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class BearerAuthenticatorTest extends TestCase
{
    #[DataProvider('getAuthorizationHeaders')]
    public function testSupportsBearerTokens(string|null $header, bool $expected): void
    {
        $request = new Request();

        if (null !== $header) {
            $request->headers->set('Authorization', $header);
        }

        $authenticator = new BearerAuthenticator($this->createStub(AccessTokenValidator::class), $this->createStub(BearerChallenge::class));

        $this->assertSame($expected, $authenticator->supports($request));
    }

    public static function getAuthorizationHeaders(): iterable
    {
        yield 'no header' => [null, false];
        yield 'basic auth' => ['Basic Zm9vOmJhcg==', false];
        yield 'empty bearer' => ['Bearer ', false];
        yield 'access token' => ['Bearer eyJhbGciOiJIUzI1NiJ9.e30.signature', true];
        yield 'personal access token' => ['Bearer ct_pat_0000000000E008000000000042_secret', false];
    }
}
