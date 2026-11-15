<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth_provider.php';

// Test 1: Provider Registry
$oidc = AuthManager::getProvider('oidc');
assertEq(is_object($oidc), true, 'Default OIDC provider is registered in AuthManager');
assertEq($oidc->getId(), 'oidc', 'OIDC provider ID is "oidc"');

// Test 2: Custom Provider implementation & registration
class MockSamlProvider implements AuthProviderInterface
{
    public function getId(): string
    {
        return 'saml_test';
    }

    public function getTitle(): string
    {
        return 'Test SAML IdP';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-key';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return 'https://idp.example.com/sso?redirect=' . urlencode($redirectUri) . '&state=' . urlencode($state);
    }

    public function handleCallback(array $params, string $redirectUri): ?array
    {
        if (empty($params['token'])) {
            return null;
        }
        return [
            'username' => 'saml_alice',
            'email' => 'alice@example.com',
            'display_name' => 'Alice SAML',
            'role' => 'operator',
            'external_id' => 'saml-sub-12345',
        ];
    }
}

$saml = new MockSamlProvider();
AuthManager::registerProvider($saml);

$retrieved = AuthManager::getProvider('saml_test');
assertEq(is_object($retrieved), true, 'Custom SAML provider registered successfully');
assertEq($retrieved->getTitle(), 'Test SAML IdP', 'Provider title matches');
assertEq($retrieved->isEnabled(), true, 'Custom SAML provider is enabled');

$authUrl = $retrieved->getAuthorizationUrl('https://app.example.com/login/callback', 'state_xyz123');
assertEq(str_contains($authUrl, 'state=state_xyz123'), true, 'Auth URL contains state parameter');
$encodedCallback = urlencode('https://app.example.com/login/callback');
assertEq(str_contains($authUrl, $encodedCallback), true, 'Auth URL contains urlencoded redirect URI');

// Test 3: Callback handling contract
$profile = $retrieved->handleCallback(['token' => 'valid_token_value'], 'https://app.example.com/login/callback');
assertEq(is_array($profile), true, 'Callback returns array user profile');
assertEq($profile['username'], 'saml_alice', 'Profile username matches');
assertEq($profile['role'], 'operator', 'Profile role matches');
assertEq($profile['external_id'], 'saml-sub-12345', 'Profile external_id matches');

// Test 4: Callback rejection on missing token
$failed = $retrieved->handleCallback([], 'https://app.example.com/login/callback');
assertEq($failed, null, 'Callback returns null when token missing');

echo "All Authentication Provider & OIDC/SAML hook tests passed successfully!\n";
