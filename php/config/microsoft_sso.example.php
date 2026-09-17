<?php
// Microsoft (Entra ID) work-account SSO config.
//
// You have two ways to configure SSO. Pick ONE:
//
// =====================================================================
// Option A (preferred) — `.env` file at the project root.
// =====================================================================
// Drop the following lines into a file named `.env` next to index.php:
//
//   MICROSOFT_TENANT_ID=YOUR_TENANT_GUID
//   MICROSOFT_CLIENT_ID=YOUR_APP_CLIENT_ID
//   MICROSOFT_CLIENT_SECRET=YOUR_APP_CLIENT_SECRET
//   MICROSOFT_REDIRECT_URI=https://your.host/Fleet%20Management%20New/ms-callback
//   MICROSOFT_ALLOWED_TENANT_ID=YOUR_TENANT_GUID    # optional — locks SSO to one tenant
//
// `.env` is NOT committed to git (it contains a client secret).
//
// =====================================================================
// Option B (legacy) — copy this file to `microsoft_sso.php` and edit.
// =====================================================================
// Kept for backwards compatibility with the older config style.
//
// Setup steps (one-time, in Azure Portal):
//   1. Azure Portal → Microsoft Entra ID → App registrations → New
//      registration.
//        Name:         Pantrucks Fleet
//        Supported:    Accounts in this organisational directory only
//                      (single tenant)  OR  Multi-tenant — pick what
//                      matches your company policy.
//        Redirect URI: Web — http://localhost/Fleet%20Management%20New/ms-callback
//                      (for production replace with your https URL)
//   2. Copy the "Application (client) ID" → MS_CLIENT_ID below.
//   3. Copy the "Directory (tenant) ID"    → MS_TENANT_ID below.
//      Use 'organizations' for any work/school account, or 'common' to
//      also allow personal Microsoft accounts.
//   4. Certificates & secrets → New client secret → copy the *value*
//      (not the ID) → MS_CLIENT_SECRET below.
//   5. API permissions → ensure Microsoft Graph "User.Read" is granted
//      (it's added by default).

if (!defined('MS_TENANT_ID'))     define('MS_TENANT_ID',     'YOUR_TENANT_GUID_OR_common');
if (!defined('MS_CLIENT_ID'))     define('MS_CLIENT_ID',     'YOUR_APP_CLIENT_ID');
if (!defined('MS_CLIENT_SECRET')) define('MS_CLIENT_SECRET', 'YOUR_APP_CLIENT_SECRET');

// Redirect URI must match exactly what's registered in Azure.
if (!defined('MS_REDIRECT_URI')) {
    define('MS_REDIRECT_URI', 'http://localhost/Fleet%20Management%20New/ms-callback');
}

// Optional tenant allowlist — only users whose Microsoft tenant matches
// this GUID can sign in. Leave empty to skip the tenant check.
if (!defined('MS_AUTO_PROVISION_TENANT')) define('MS_AUTO_PROVISION_TENANT', '');
