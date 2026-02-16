# Admin Menu Disabler

Disable admin sections in Castopod with a page-by-page role matrix.

## What it does

- Hides selected sections from the admin sidebar
- Blocks direct access to disabled sections with HTTP `403`
- Uses a matrix (`Page` x `Denied roles`) in plugin settings

## How to use

1. Activate the plugin in `Admin > Plugins`.
2. Open plugin settings.
3. In the matrix, click each `Denied roles` cell to open the roles list.
4. Select one or more roles.
5. Save.

## Notes

- The settings page of this plugin stays accessible so you can re-enable sections.
- Role examples: `superadmin`, `manager`, `podcaster`, `podcast#1-admin`.
- Block everyone for a page: pick `Everyone` (or use token `everyone` / `*` in existing values).
