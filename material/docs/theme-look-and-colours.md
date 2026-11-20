# Theme look and colours

**Status:** Available. Plugin: `theme_material` (`public/theme/material`).

## What it does

Material is a child theme of Boost that restyles every Moodle page in Material 3: tonal colour roles, rounded
shapes, Material type, elevated surfaces, filled and tonal buttons, outlined fields, Material menus, dialogs,
alerts, tabs, tables and code blocks.

One **seed colour** generates the whole palette for light and dark mode. `classes/palette.php` turns the seed into
the Material 3 role table (primary, secondary, tertiary, error, success, the surface container ladder, outline,
inverse roles and scrim) using tones in HSL: primary at tone 40 in light mode and tone 80 in dark mode, containers
at 90 and 30, and so on. The roles are written as CSS custom properties `--md-sys-color-<role>` (plus an `-rgb`
variant) for `:root`/`[data-bs-theme=light]` and `[data-bs-theme=dark]`, and Bootstrap's own variables
(`--bs-body-bg`, `--bs-primary`, link colours, subtle backgrounds and so on) are mapped onto those roles so core
Moodle components follow the palette. `scss/material.scss` only reads the custom properties, so changing the seed
restyles every page without editing SCSS.

The generator approximates Material tones in HSL so it stays dependency free. Colours are close to, but not
identical with, the reference Material Color Utilities output.

## Fonts

All fonts are bundled in `public/theme/material/fonts` and served through Moodle's font handler, so no font is
loaded from another website:

| Setting value | Font | Weights |
| --- | --- | --- |
| `robotoflex` (default) | Roboto Flex | variable 100 to 1000 |
| `inter` | Inter | variable 100 to 900 |
| `atkinson` | Atkinson Hyperlegible Next | 400 and 700 |
| `system` | The device's own interface font | n/a |

Roboto Mono (variable 100 to 700) is always loaded for code. Only the Latin subsets are bundled.

## Light and dark mode

Material does not add its own light and dark switch. Turn on **Colour modes** in the Boost settings
(`theme_boost | enablecolourmodes`); people then choose light, dark or automatic from the user menu, and the Material
palette follows that choice. On a fresh Docker install the first-run configuration turns colour modes on for you.

## Settings

Site administration > Appearance > Themes > Material, tab **Look and motion**:

| Setting | Name | Default | Effect |
| --- | --- | --- | --- |
| Seed colour | `theme_material | seedcolor` | `#8B5000` | Generates every colour role. Invalid values fall back to the default. |
| Font | `theme_material | font` | `robotoflex` | Font stack for all text. |
| Corner roundness | `theme_material | radius` | `24` px | Large surfaces use the value, buttons and fields half of it, small elements a third (minimum 4 px). Choices 0 to 40 px; values are clamped to 0 to 48. |
| Density | `theme_material | density` | `comfortable` | `compact` tightens spacing by 4 px. |

Tab **Advanced** keeps Boost's raw SCSS hooks: `theme_material | scsspre` (variables before compilation) and
`theme_material | scss` (appended at the end).

Changing the seed colour, font, roundness, density or raw SCSS purges the theme cache automatically.

## When something goes wrong

- **Colours did not change.** The theme CSS is cached. Saving the setting purges it; if you changed the value with
  the CLI, run `php admin/cli/purge_caches.php`.
- **A seed such as `#abc` was saved.** Three-digit colours are expanded; anything that is not a hex colour uses
  `#8B5000`.
- **Low contrast on a very light or very dark seed.** The generator clamps primary saturation to 0.38 to 0.9 but
  does not check contrast. Pick a seed with mid lightness.

## Security and privacy

The theme stores no personal data (`classes/privacy/provider.php`). Raw SCSS is administrator-only, as in Boost.

## How to verify

1. Set a seed colour, save, and open any course page: buttons, links and the top bar take the new colour.
2. Turn on Colour modes in Boost, switch to dark from the user menu: surfaces turn dark and primary becomes tone 80.
3. Open the browser developer tools and check that `--md-sys-color-primary` is defined on `:root`.

## Related articles

- [Motion and reduced motion](motion-and-reduced-motion.md)
- [Sign-in page](sign-in-page.md)
- [Administration settings reference](administration-settings.md)
