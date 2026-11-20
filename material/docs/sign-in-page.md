# Sign-in page

**Status:** Available. Plugin: `theme_material` (`layout/login.php`, `templates/core/login_panel.mustache`).

## What it does

The sign-in page uses Boost's split layout. The coloured side shows the site's short name with a mark, a large
heading, a sentence or two of text and a row of short labels, over slowly morphing shapes in the palette colours.
The heading, text and labels rise in one after another. The form side is restyled in Material and, when passkey
sign-in is enabled, shows a large **Sign in with a passkey** button above the username field (see
[Passkey sign-in](passkey-sign-in.md)).

If **Instructions** are set in Moodle's authentication settings (`auth_instructions`), they replace the heading and
text on the coloured side.

## Settings

Site administration > Appearance > Themes > Material, tab **Sign-in page**:

| Setting | Name | Default |
| --- | --- | --- |
| Sign-in heading | `theme_material | loginheading` | "Learning, in full colour." |
| Sign-in text | `theme_material | logintext` | "Sign in with your fingerprint, face or security key, or with your username and password." |
| Sign-in labels | `theme_material | loginchips` | "Passkeys, Dark mode, Material 3" (comma separated; empty for none) |
| Sign-in image | `theme_material | loginimage` | none. JPG, PNG or WebP, one file. Replaces the shapes. |

All text is passed through `format_string`, so multi-language filters work.

## How to verify

Sign out and open `/login/index.php`: the coloured panel shows the configured heading, text and labels. Upload an
image and reload: the image replaces the shapes.

## Related articles

- [Passkey sign-in](passkey-sign-in.md)
- [Theme look and colours](theme-look-and-colours.md)
