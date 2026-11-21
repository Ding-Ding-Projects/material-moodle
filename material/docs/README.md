# Material Moodle documentation

One article per feature. Each article covers what the feature does, its settings, what can go wrong, security
and privacy, and how to verify it. Features still being built are marked **In progress** and describe the intended
behaviour together with what exists today.

## Look and feel

| Article | Status |
| --- | --- |
| [Theme look and colours](theme-look-and-colours.md) | Available |
| [Motion and reduced motion](motion-and-reduced-motion.md) | Available |
| [Sign-in page](sign-in-page.md) | Available |
| [Celebrations](celebrations.md) | Available |

## Finding and doing things

| Article | Status |
| --- | --- |
| [Command palette](command-palette.md) | Available |
| [Regex builder on search boxes](regex-builder.md) | Available in the palette; in progress on page search boxes |
| [Right-click menu](right-click-menu.md) | In progress |
| [Copy buttons](copy-buttons.md) | Available |

## Signing in

| Article | Status |
| --- | --- |
| [Passkey sign-in](passkey-sign-in.md) | Available |
| [Passkey manager](passkey-manager.md) | Available |
| [First sign-in flow](first-sign-in.md) | Available |

## People and wording

| Article | Status |
| --- | --- |
| [Add a student and Discord credentials](add-a-student.md) | Available |
| [Custom vocabulary pack](custom-vocabulary-pack.md) | Available |
| [Site wording](site-wording.md) | Available |
| [Per-assignment type labels](assignment-type-labels.md) | In progress |

## Hosting

| Article | Status |
| --- | --- |
| [Docker stack](docker-stack.md) | Available |
| [Cloudflare tunnels](cloudflare-tunnels.md) | Available; guided sign-in in progress |
| [Desktop deployer](desktop-deployer.md) | In progress |
| [Server image (appliance)](appliance.md) | Available once the image build finishes |

## Reference

| Article | Status |
| --- | --- |
| [Administration settings reference](administration-settings.md) | Available |

## APIs

The plugins expose three small HTTP endpoints, all inside Moodle's session: `auth/passkey/ajax.php` (passkey
ceremonies, described in [Passkey sign-in](passkey-sign-in.md)), `local/materialtools/vocab.php` (the vocabulary
pack for an eligible person) and `local/materialtools/vocabulary.php?action=uploadjson` (pack upload for managers),
both described in [Custom vocabulary pack](custom-vocabulary-pack.md). There is no API collection file yet.

See also [`../ROADMAP.md`](../ROADMAP.md), [`../HANDOFF.md`](../HANDOFF.md) and
[`../COMPLETENESS.md`](../COMPLETENESS.md).
