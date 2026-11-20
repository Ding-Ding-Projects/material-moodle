# Motion and reduced motion

**Status:** Available. Plugin: `theme_material`.

## What it does

The theme animates with Material's emphasized easing: content rises in when a page opens, menus and dialogs scale
in, buttons show a touch ripple where they are pressed, and the sign-in page has slowly morphing shapes in the
palette colours. Celebrations (see [Celebrations](celebrations.md)) are also motion.

The motion level is written to the `<html>` element as `data-md-motion` by a hook before the first paint
(`classes/hook_listener.php`), so CSS follows it without waiting for JavaScript.

| Level | What moves |
| --- | --- |
| `expressive` (default) | Everything: page rise, ripples, menu and dialog motion, sign-in shapes, confetti and rings. |
| `calm` | Short fades only. Sign-in shapes and ripples stop, page rise is shortened to 0.2 s, celebrations are skipped. |
| `off` | No animations or transitions at all. |

## Reduced motion

When the device asks for reduced motion (`prefers-reduced-motion: reduce`):

- CSS cuts every animation and transition to 0.01 ms and a single iteration.
- JavaScript does not start page transitions, ripples or celebrations, whatever the site setting says.

So people who ask their device for less motion always get the calm or still version. Nothing that carries meaning
depends on motion: success and error messages remain ordinary Moodle alerts with text.

## Settings

Site administration > Appearance > Themes > Material, tab **Look and motion**:

| Setting | Name | Default |
| --- | --- | --- |
| Motion | `theme_material | motion` | `expressive` |
| Page transitions | `theme_material | pagetransitions` | on |
| Touch ripples | `theme_material | ripples` | on (only used when motion is `expressive`) |

## Limits

- Motion is a site-wide setting. There is no per-person motion preference yet; people rely on their device's
  reduced-motion setting.
- Motion cannot be scheduled.

## How to verify

1. With motion `expressive`, open a course page: the header and main region rise in, and pressing a button shows a
   ripple.
2. Turn on reduced motion in the operating system and reload: nothing animates.
3. Set motion to `off`: `<html data-md-motion="off">` is present and nothing animates even without the device
   setting.

## Related articles

- [Theme look and colours](theme-look-and-colours.md)
- [Celebrations](celebrations.md)
