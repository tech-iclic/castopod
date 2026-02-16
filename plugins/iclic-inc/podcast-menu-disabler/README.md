# Podcast Menu Disabler

Disable podcast sidebar menus and subpages in Castopod with a role matrix.

## What it does

- Hides full podcast menu sections from the sidebar.
- Hides specific subpages inside a section when only a subset must be denied.
- Blocks direct access to disabled menus/subpages with HTTP `403`.
- Uses a matrix (`Page` x `Denied roles`) in plugin settings.

## How to use

1. Activate the plugin in `Admin > Plugins`.
2. Open plugin settings.
3. In the matrix, choose denied roles for full menus and/or specific subpages.
4. Save.
