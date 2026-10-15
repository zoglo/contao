<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Security\AccessToken;

use Contao\BackendUser;
use Contao\CoreBundle\Entity\PersonalAccessToken;
use Contao\CoreBundle\Repository\PersonalAccessTokenRepository;
use Contao\CoreBundle\Security\AccessToken\AccessTokenManager;
use Contao\CoreBundle\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class AccessTokenManagerTest extends TestCase
{
    public function testCreatesPersonalAccessTokenWithHashedSecret(): void
    {
        $user = $this->createClassWithPropertiesStub(BackendUser::class);
        $user->id = 42;

        $persistedToken = null;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(
                function (PersonalAccessToken $personalAccessToken) use (&$persistedToken) {
                    $persistedToken = $personalAccessToken;

                    $this->assertNull($personalAccessToken->getExpiresAt());
                    $this->assertNull($personalAccessToken->getLastUsed());

                    return true;
                },
            )
        ;

        $entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $accessTokenManager = new AccessTokenManager($entityManager);
        $plainToken = $accessTokenManager->createBackendToken($user, 'foobar');

        $this->assertStringStartsWith(AccessTokenManager::TOKEN_PREFIX, $plainToken);

        $parsedToken = $accessTokenManager->parseToken($plainToken);

        $this->assertSame($persistedToken->getId()->toBase32(), $parsedToken['id']);
        $this->assertSame(hash('sha256', $parsedToken['secret']), $persistedToken->getSecret());
    }

    public function testParserSkipsTokensWithWrongFormats(): void
    {
        $accessTokenManager = new AccessTokenManager($this->createStub(EntityManagerInterface::class));

        $this->assertNull($accessTokenManager->parseToken('foobar'));
        $this->assertNull($accessTokenManager->parseToken(AccessTokenManager::TOKEN_PREFIX.'foo'));
        $this->assertNull($accessTokenManager->parseToken(AccessTokenManager::TOKEN_PREFIX.'foo_bar'));
        $this->assertNull($accessTokenManager->parseToken(AccessTokenManager::TOKEN_PREFIX.Uuid::v7()->toBase32().'_'));
    }

    public function testParsesToken(): void
    {
        $accessTokenManager = new AccessTokenManager($this->createStub(EntityManagerInterface::class));

        $id = Uuid::v7()->toBase32();
        $parsedToken = $accessTokenManager->parseToken(AccessTokenManager::TOKEN_PREFIX.$id.'_bar');

        $this->assertSame(['id' => $id, 'secret' => 'bar'], $parsedToken);
    }

    public function testValidatesToken(): void
    {
        $personalAccessToken = new PersonalAccessToken('backend', 42, 'foobar', hash('sha256', 'secret'));
        $id = $personalAccessToken->getId()->toBase32();

        $repository = $this->createMock(PersonalAccessTokenRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('findValidByIdAndScope')
            ->with($id, 'backend')
            ->willReturn($personalAccessToken)
        ;

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->willReturn($repository)
        ;

        $accessTokenManager = new AccessTokenManager($entityManager);

        $this->assertSame($personalAccessToken, $accessTokenManager->parseAndValidateToken(AccessTokenManager::TOKEN_PREFIX.$id.'_secret', 'backend'));
        $this->assertNull($accessTokenManager->parseAndValidateToken(AccessTokenManager::TOKEN_PREFIX.$id.'_wrong', 'backend'));
    }

    public function testDoesNotQueryMalformedTokenIds(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('getRepository')
        ;

        $accessTokenManager = new AccessTokenManager($entityManager);

        $this->assertNull($accessTokenManager->parseAndValidateToken(AccessTokenManager::TOKEN_PREFIX.'foo_bar', 'backend'));
    }
}
