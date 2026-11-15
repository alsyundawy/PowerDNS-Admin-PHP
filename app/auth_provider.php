<?php

/**
 * PowerDNS-Admin-PHP Authentication Extension Point (OIDC / SAML Federation).
 * Provides a modular, zero-framework hook architecture for external Identity Providers (IdP).
 */

declare(strict_types=1);

/**
 * Contract that all external authentication providers must implement.
 */
interface AuthProviderInterface
{
    /**
     * Unique machine identifier for provider (e.g. 'oidc', 'saml').
     */
    public function getId(): string;

    /**
     * Human-readable display label (e.g. 'Single Sign-On (OIDC)').
     */
    public function getTitle(): string;

    /**
     * FontAwesome icon class.
     */
    public function getIcon(): string;

    /**
     * Whether this provider is enabled in configuration or settings.
     */
    public function isEnabled(): bool;

    /**
     * Generate the authorization redirect URL to initiate SSO flow.
     */
    public function getAuthorizationUrl(string $redirectUri, string $state): string;

    /**
     * Process authentication callback parameters and return normalized user profile.
     *
     * @param array<string, mixed> $params
     * @param string $redirectUri
     * @return array{username: string, email: string, display_name: string, role: string, external_id: string}|null
     */
    public function handleCallback(array $params, string $redirectUri): ?array;
}

/**
 * Extension Point Registry and SSO User Provisioning Service.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
final class AuthManager
{
    /** @var array<string, AuthProviderInterface> */
    private static array $providers = [];
    private static bool $booted = false;

    private static function ensureBooted(): void
    {
        if (!self::$booted) {
            self::$booted = true;
            self::$providers['oidc'] = new OidcAuthProvider();
        }
    }

    /**
     * Register an authentication provider plugin.
     */
    public static function registerProvider(AuthProviderInterface $provider): void
    {
        self::ensureBooted();
        self::$providers[$provider->getId()] = $provider;
    }

    /**
     * Get all active, enabled authentication providers.
     *
     * @return array<string, AuthProviderInterface>
     */
    public static function getEnabledProviders(): array
    {
        self::ensureBooted();
        $enabled = [];
        foreach (self::$providers as $id => $p) {
            if ($p->isEnabled()) {
                $enabled[$id] = $p;
            }
        }
        return $enabled;
    }

    /**
     * Get provider by ID.
     */
    public static function getProvider(string $id): ?AuthProviderInterface
    {
        self::ensureBooted();
        return self::$providers[$id] ?? null;
    }

    /**
     * Automatically map or provision an authenticated external SSO user in local database.
     *
     * @param array{username: string, email: string, display_name: string, role: string, external_id: string} $profile
     * @return array<string, mixed>
     */
    public static function provisionExternalUser(array $profile): array
    {
        ensureEnterpriseSchema();
        $username = trim($profile['username']);
        $email = trim($profile['email']);
        $displayName = trim($profile['display_name']) !== '' ? trim($profile['display_name']) : $username;
        $role = in_array($profile['role'], ['admin', 'operator', 'user'], true) ? $profile['role'] : 'user';

        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user) {
            // Update email / display name from authoritative IdP
            $up = db()->prepare('UPDATE users SET email = ?, display_name = ? WHERE id = ?');
            $up->execute([$email, $displayName, (int) $user['id']]);
            $st->execute([$username]);
            $updated = $st->fetch();
            return is_array($updated) ? $updated : $user;
        }

        // Provision new SSO user account with locked password hash
        $lockedHash = '*SSO_EXTERNAL_AUTH_LOCKED*';
        $ins = db()->prepare(
            'INSERT INTO users (username, password_hash, display_name, email, role, active) '
            . 'VALUES (?, ?, ?, ?, ?, 1)'
        );
        $ins->execute([$username, $lockedHash, $displayName, $email, $role]);
        $newId = (int) db()->lastInsertId();

        $st->execute([$username]);
        $fresh = $st->fetch();
        return is_array($fresh) ? $fresh : ['id' => $newId, 'username' => $username, 'role' => $role];
    }
}

/**
 * OpenID Connect (OIDC) Reference Implementation & Extension Hook.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class OidcAuthProvider implements AuthProviderInterface
{
    public function getId(): string
    {
        return 'oidc';
    }

    public function getTitle(): string
    {
        return (string) setting('auth_oidc_title', 'Single Sign-On (OIDC)');
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-id-badge';
    }

    public function isEnabled(): bool
    {
        return setting('auth_oidc_enabled', '0') === '1'
            && trim((string) setting('auth_oidc_issuer', '')) !== ''
            && trim((string) setting('auth_oidc_client_id', '')) !== '';
    }

    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        $authEndpoint = (string) setting('auth_oidc_auth_endpoint', '');
        if ($authEndpoint === '') {
            $issuer = rtrim((string) setting('auth_oidc_issuer', ''), '/');
            $authEndpoint = $issuer . '/protocol/openid-connect/auth';
        }

        $clientId = (string) setting('auth_oidc_client_id', '');
        $scopes = (string) setting('auth_oidc_scopes', 'openid profile email');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
        ]);

        return $authEndpoint . (str_contains($authEndpoint, '?') ? '&' : '?') . $query;
    }

    public function handleCallback(array $params, string $redirectUri): ?array
    {
        $code = (string) ($params['code'] ?? '');
        if ($code === '') {
            return null;
        }

        $tokenEndpoint = (string) setting('auth_oidc_token_endpoint', '');
        if ($tokenEndpoint === '') {
            $issuer = rtrim((string) setting('auth_oidc_issuer', ''), '/');
            $tokenEndpoint = $issuer . '/protocol/openid-connect/token';
        }

        $clientId = (string) setting('auth_oidc_client_id', '');
        $clientSecretEnc = (string) setting('auth_oidc_client_secret', '');
        $clientSecret = $clientSecretEnc !== '' ? secretDecrypt($clientSecretEnc) : '';

        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $ch = curl_init($tokenEndpoint);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: PowerDNS-Admin-PHP/' . APP_VERSION,
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !is_string($resp)) {
            return null;
        }

        $tokenData = json_decode($resp, true);
        if (!is_array($tokenData) || empty($tokenData['access_token'])) {
            return null;
        }

        // Fetch UserInfo
        $userInfoEndpoint = (string) setting('auth_oidc_userinfo_endpoint', '');
        if ($userInfoEndpoint === '') {
            $issuer = rtrim((string) setting('auth_oidc_issuer', ''), '/');
            $userInfoEndpoint = $issuer . '/protocol/openid-connect/userinfo';
        }

        $chUi = curl_init($userInfoEndpoint);
        if ($chUi === false) {
            return null;
        }

        curl_setopt_array($chUi, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . (string) $tokenData['access_token'],
                'Accept: application/json',
                'User-Agent: PowerDNS-Admin-PHP/' . APP_VERSION,
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $uiResp = curl_exec($chUi);
        $uiStatus = (int) curl_getinfo($chUi, CURLINFO_HTTP_CODE);
        curl_close($chUi);

        if ($uiStatus !== 200 || !is_string($uiResp)) {
            return null;
        }

        $claims = json_decode($uiResp, true);
        if (!is_array($claims)) {
            return null;
        }

        $username = (string) ($claims['preferred_username'] ?? ($claims['email'] ?? ($claims['sub'] ?? '')));
        if ($username === '') {
            return null;
        }

        $email = (string) ($claims['email'] ?? '');
        $displayName = (string) ($claims['name'] ?? ($claims['given_name'] ?? $username));
        $externalId = (string) ($claims['sub'] ?? $username);

        $defaultRole = (string) setting('auth_oidc_default_role', 'user');

        return [
            'username' => $username,
            'email' => $email,
            'display_name' => $displayName,
            'role' => $defaultRole,
            'external_id' => $externalId,
        ];
    }
}
