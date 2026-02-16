# Public Site Privacy

Control public visibility of your Castopod homepage and `@podcastHandle` pages.

## What it does

- Optionally redirects `/` (homepage) to the admin gateway.
- Optionally blocks all public `@handle` routes and redirects them to admin.
- Keeps admin/auth/install routes accessible.

## How to use

1. Activate the plugin in `Admin > Plugins`.
2. Open plugin settings.
3. Enable one or both options:
   - `Redirect homepage (/) to admin`
   - `Block public @podcast pages`
4. Save.

## Notes

- Redirect target is your admin gateway (`/cp-admin` by default).
- If a visitor is not logged in, Castopod will then show the login flow.
