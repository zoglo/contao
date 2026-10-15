<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\E2eTests\Security;

use Contao\E2eTesting\Http\HttpRequest;
use Contao\E2eTesting\ManagedEdition\ManagedEditionConfig;
use Contao\E2eTests\AbstractContaoMonorepoE2ETestCase;
use Contao\InstallationRecipe\Recipe\InstallationRecipe;

class PersonalAccessTokenTest extends AbstractContaoMonorepoE2ETestCase
{
    private const string TWO_FACTOR_TOKEN = 'ct_pat_0000000000E008000000000043_twofactor';

    private const string NO_TWO_FACTOR_TOKEN = 'ct_pat_0000000000E008000000000044_notwofactor';

    public function testAuthenticatesApiRequests(): void
    {
        $response = self::managedEdition()->send(self::createApiRequest(self::TWO_FACTOR_TOKEN));
        $data = $response->toArray(false);

        // The template does not exist, so a 404 proves the request was authenticated
        $this->assertSame(404, $response->getStatusCode(), json_encode($data, JSON_PRETTY_PRINT));
        $this->assertSame('Given identifier does not exist.', $data['detail']);
    }

    public function testAuthenticatesMcpRequests(): void
    {
        $response = self::managedEdition()->send(self::createMcpRequest(self::TWO_FACTOR_TOKEN));
        $content = $response->getContent(false);

        $this->assertSame(200, $response->getStatusCode(), $content);
        $this->assertStringContainsString('"serverInfo"', $content);
    }

    public function testRejectsMalformedTokens(): void
    {
        $response = self::managedEdition()->send(self::createApiRequest('ct_pat_foo_bar'));

        $this->assertSame(401, $response->getStatusCode(), $response->getContent(false));

        $response = self::managedEdition()->send(self::createMcpRequest('ct_pat_foo_bar'));

        $this->assertSame(401, $response->getStatusCode(), $response->getContent(false));
    }

    public function testRejectsTokensIfTheMandatoryTwoFactorSetupIsMissing(): void
    {
        $response = self::managedEdition()->send(self::createApiRequest(self::NO_TWO_FACTOR_TOKEN));

        $this->assertSame(401, $response->getStatusCode(), $response->getContent(false));

        $response = self::managedEdition()->send(self::createMcpRequest(self::NO_TWO_FACTOR_TOKEN));

        $this->assertSame(401, $response->getStatusCode(), $response->getContent(false));
    }

    protected static function createApplicationConfig(): ManagedEditionConfig
    {
        $composer = self::createMonorepoComposerConfig('api-bundle', 'core-bundle', 'mcp-bundle', 'oauth-server-bundle');

        $recipe = InstallationRecipe::create($composer)
            ->withConfigFile(self::projectDirectory().'/tests/E2E/Fixtures/Security/enforce-two-factor.yaml')
            ->withFixtureFile(self::projectDirectory().'/tests/E2E/Fixtures/Security/personal-access-tokens.yaml')
        ;

        return ManagedEditionConfig::create($recipe, self::projectDirectory());
    }

    private static function createApiRequest(string $token): HttpRequest
    {
        return HttpRequest::get('/contao/api/user_templates/unknown_template')
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/ld+json',
            ])
        ;
    }

    private static function createMcpRequest(string $token): HttpRequest
    {
        return HttpRequest::json('POST', '/contao/mcp')
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json, text/event-stream',
            ])
            ->withJson([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'e2e', 'version' => '1.0'],
                ],
            ])
        ;
    }
}
