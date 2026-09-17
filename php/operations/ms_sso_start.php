<?php
session_start();

require_once __DIR__ . "/../helpers/microsoft_auth.php";

if (!microsoftAuthConfigured()) {
    header(
        "Location: login?error=" .
            urlencode(
                "Microsoft sign-in is not configured yet. Ask the administrator to set the Microsoft app credentials."
            )
    );
    exit();
}

header("Location: " . buildMicrosoftAuthorizeUrl());
exit();
