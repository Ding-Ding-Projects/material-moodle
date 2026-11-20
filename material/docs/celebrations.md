# Celebrations

**Status:** Available. Plugin: `theme_material`, module `theme_material/celebrate`.

## What it does

The theme watches the page for Moodle's success and error messages, including ones added later:

- **Success** (`.alert-success`, `.toast.bg-success`, or anything marked `data-md-celebrate="success"`): the message
  bounces and confetti in the palette colours bursts from it. At the **Dramatic** level 64 pieces fly and two rings
  expand; at **Normal**, 28 pieces and no rings.
- **Error** (`.alert-danger`, `.alert-error`, `.toast.bg-danger`, or `data-md-celebrate="error"`): the message shakes.
  At **Dramatic** the window also flashes red briefly.

Each message is celebrated once (`data-md-celebrated` marks it). Other plugins use the same module: passkey
sign-in and registration celebrate success and shake on failure, and dropping a vocabulary pack shows a full-window
headline with a burst.

## Settings

| Setting | Name | Default |
| --- | --- | --- |
| Celebrations | `theme_material | celebrations` | on |
| Celebration level | `theme_material | celebrationlevel` | `dramatic` (`subtle`, `normal`, `dramatic`) |

**Subtle** keeps the error shake and skips confetti. Celebrations never run when motion is `calm` or `off`, or when
the device asks for reduced motion.

## Accessibility

The effects are decoration only (`aria-hidden`); the message itself is the ordinary Moodle alert with its text and
role. The red flash is short and only at the Dramatic level.

## How to verify

Save any administration setting: the green "Changes saved" message bounces with confetti. Submit a form with a
missing required field to see the error shake.

## Related articles

- [Motion and reduced motion](motion-and-reduced-motion.md)
- [Custom vocabulary pack](custom-vocabulary-pack.md)
