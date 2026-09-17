<?php


function getEnvValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== "") {
        return $value;
    }

    static $envValues = null;
    if ($envValues === null) {
        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ".env";
        $envValues = is_file($envPath)
            ? parse_ini_file($envPath, false, INI_SCANNER_RAW)
            : [];
    }

    if (isset($envValues[$key]) && $envValues[$key] !== "") {
        return (string) $envValues[$key];
    }

    return $default;
}

function getMicrosoftAuthConfig(): array
{
    // Lazy-load the legacy PHP config (if present) so its MS_* constants are available.
    static $legacyLoaded = false;
    if (!$legacyLoaded) {
        $legacyLoaded = true;
        $legacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "microsoft_sso.php";
        if (is_file($legacy)) {
            require_once $legacy;
        }
    }

    $legacyTenant   = defined("MS_TENANT_ID")            ? (string) MS_TENANT_ID            : "organizations";
    $legacyClient   = defined("MS_CLIENT_ID")            ? (string) MS_CLIENT_ID            : "b74d2891-7ec3-4967-af45-0036556f8978";
    $legacySecret   = defined("MS_CLIENT_SECRET")        ? (string) MS_CLIENT_SECRET        : "cx18Q~CrZWihcVopBV1bbu7q_CdUr.FmwXqPgaou";
    $legacyRedirect = defined("MS_REDIRECT_URI")         ? (string) MS_REDIRECT_URI         : "https://app.fleet-pantrucks.com/ms-callback";
    $legacyAllowed  = defined("MS_AUTO_PROVISION_TENANT")? (string) MS_AUTO_PROVISION_TENANT: "95b1c458-41cb-489b-9c8f-b1d5764c60fd";

    $tenant       = trim((string) getEnvValue("MICROSOFT_TENANT_ID",        $legacyTenant !== "" ? $legacyTenant : "organizations"));
    $clientId     = trim((string) getEnvValue("MICROSOFT_CLIENT_ID",        $legacyClient));
    $clientSecret = trim((string) getEnvValue("MICROSOFT_CLIENT_SECRET",    $legacySecret));
    $configuredRedirectUri = trim((string) getEnvValue("MICROSOFT_REDIRECT_URI", $legacyRedirect));
    $allowedTenantId = trim((string) getEnvValue("MICROSOFT_ALLOWED_TENANT_ID", $legacyAllowed));

    // Auto-build a redirect URI from the current host if none was configured.
    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $basePath = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php"), "/\\");
    $defaultRedirectUri = $scheme . "://" . $host . ($basePath === "" ? "" : $basePath) . "/ms-callback";

    // Strip placeholder values from the legacy example file.
    if ($clientId === "YOUR_APP_CLIENT_ID")         $clientId = "";
    if ($clientSecret === "YOUR_APP_CLIENT_SECRET") $clientSecret = "";
    if ($tenant === "YOUR_TENANT_GUID_OR_common")   $tenant = "organizations";

    return [
        "tenant"            => $tenant,
        "client_id"         => $clientId,
        "client_secret"     => $clientSecret,
        "redirect_uri"      => $configuredRedirectUri !== "" ? $configuredRedirectUri : $defaultRedirectUri,
        "allowed_tenant_id" => $allowedTenantId,
        "scope"             => "openid profile email",
    ];
}

function microsoftAuthConfigured(): bool
{
    $config = getMicrosoftAuthConfig();
    return $config["client_id"] !== "" && $config["client_secret"] !== "";
}

function buildMicrosoftAuthorizeUrl(): string
{
    $config = getMicrosoftAuthConfig();
    $state = bin2hex(random_bytes(16));
    $_SESSION["microsoft_oauth_state"] = $state;

    $query = http_build_query([
        "client_id"     => $config["client_id"],
        "response_type" => "code",
        "redirect_uri"  => $config["redirect_uri"],
        "response_mode" => "query",
        "scope"         => $config["scope"],
        "state"         => $state,
        "prompt"        => "select_account",
    ]);

    return "https://login.microsoftonline.com/" .
        rawurlencode($config["tenant"]) .
        "/oauth2/v2.0/authorize?" .
        $query;
}

function exchangeMicrosoftCodeForToken(string $code): array
{
    $config = getMicrosoftAuthConfig();
    $tokenUrl =
        "https://login.microsoftonline.com/" .
        rawurlencode($config["tenant"]) .
        "/oauth2/v2.0/token";

    $postFields = http_build_query([
        "client_id"     => $config["client_id"],
        "client_secret" => $config["client_secret"],
        "grant_type"    => "authorization_code",
        "code"          => $code,
        "redirect_uri"  => $config["redirect_uri"],
        "scope"         => $config["scope"],
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response   = curl_exec($ch);
    $curlError  = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("Microsoft token request failed: " . $curlError);
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        throw new RuntimeException("Invalid Microsoft token response");
    }

    if ($statusCode >= 400 || isset($payload["error"])) {
        $message = $payload["error_description"] ?? $payload["error"] ?? "Microsoft sign-in failed";
        throw new RuntimeException($message);
    }

    return $payload;
}

function decodeMicrosoftJwtPayload(string $jwt): array
{
    $parts = explode(".", $jwt);
    if (count($parts) < 2) {
        throw new RuntimeException("Invalid Microsoft ID token");
    }

    $payload = $parts[1];
    $payload .= str_repeat("=", (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode(strtr($payload, "-_", "+/"), true);

    if ($decoded === false) {
        throw new RuntimeException("Unable to decode Microsoft ID token");
    }

    $claims = json_decode($decoded, true);
    if (!is_array($claims)) {
        throw new RuntimeException("Invalid Microsoft ID token claims");
    }

    return $claims;
}

function getMicrosoftUserClaims(string $code): array
{
    $tokenData = exchangeMicrosoftCodeForToken($code);
    if (empty($tokenData["id_token"])) {
        throw new RuntimeException("Microsoft did not return an ID token");
    }

    $claims = decodeMicrosoftJwtPayload($tokenData["id_token"]);
    $email = strtolower(trim(
        (string) ($claims["preferred_username"] ?? $claims["email"] ?? "")
    ));

    if ($email === "") {
        throw new RuntimeException("Microsoft account email was not provided");
    }

    $config = getMicrosoftAuthConfig();
    if (
        $config["allowed_tenant_id"] !== "" &&
        (($claims["tid"] ?? "") !== $config["allowed_tenant_id"])
    ) {
        throw new RuntimeException("This Microsoft tenant is not allowed for this application");
    }

    return [
        "email"     => $email,
        "tenant_id" => (string) ($claims["tid"] ?? ""),
        "name"      => (string) ($claims["name"] ?? $email),
    ];
}
