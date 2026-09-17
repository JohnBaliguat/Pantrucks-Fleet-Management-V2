# Pantrucks Driver Android App

This is a native Android shell for the existing driver web app in this project.

## What it does

- Opens the driver dashboard inside a full-screen Android app
- Keeps login/session cookies inside the app
- Supports geolocation prompts for the driver's live tracking flow
- Supports file upload and camera capture for POD, pickup, jack-up, and breakdown screens
- Supports pull-to-refresh and standard Android back navigation
- Sends downloads to Android's download manager

## Before you build

The default base URL is already set to `https://app.fleet-pantrucks.com/driver-dashboard` in [gradle.properties](C:/xampp/htdocs/Fleet%20Management%20New/android-driver-app/gradle.properties).

Examples:

- Android emulator with XAMPP on the same PC:
  `DRIVER_APP_BASE_URL=http://10.0.2.2/Fleet%20Management%20New/driver-dashboard`
- Physical phone on the same Wi-Fi:
  `DRIVER_APP_BASE_URL=http://192.168.x.x/Fleet%20Management%20New/driver-dashboard`
- Hosted server:
  `DRIVER_APP_BASE_URL=https://app.fleet-pantrucks.com/driver-dashboard`

## Open in Android Studio

Open the folder:

`C:\xampp\htdocs\Fleet Management New\android-driver-app`

Then let Gradle sync and run the `app` target.

## Notes

- The app is a WebView wrapper, not a native rewrite of the driver module.
- If you want Play Store style verified full-screen web packaging later, the existing `.well-known/assetlinks.json` can be reused for a Trusted Web Activity path, but that requires a stable HTTPS domain.
