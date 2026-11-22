# Administration settings reference

Open **Site administration > Server > Configuration settings directory** to search the names documented
in the installed version of `config-dist.php`. Each matching core control opens its native settings page.
Filter by configuration method to see editable controls, host overrides and names without a matching core
field. The directory reads the distributed example and registered setting names, never the live configuration file.
It covers named properties; nested keys, compound expressions and service configuration files are outside its count.

### Change backup, enrolment and queue behaviour

Open **Site administration > Server > Backup, enrolment and task settings**. The ZIP option affects new
course backups. The restore setting blocks creation of new accounts during a course restore. The
enrolment interval controls when an ongoing user session refreshes enrolments. The two queue thresholds
control Moodle's existing ad hoc queue status check. Each control writes Moodle's original setting.
Values explicitly forced in the installation configuration retain Moodle's normal override semantics.

### Change the PHP limits of a hosted installation

In the desktop deployer, open **Server > PHP runtime limits**. Choose the memory per process, upload limit,
request body size, request timeout, maximum input variables or opcode cache settings. **Keep server setting**
preserves an existing deployment value. The option label shows the default used on new installations.
Select **Deploy** to recreate the web and scheduled-task containers with the selected limits.
For an existing instance, enter its SSH connection details and select **Read running server**.
The deployer discovers the active Compose directory and displays the nine configured values.
Choose **Apply choices and restart web and worker** to update only the selected limits. It checks
for unrelated pending Compose changes, keeps the running images and database, waits for both services,
and reads the configured limits back. A normal restart or verification failure restores the previous
configuration. This route requires Python 3, SSH and a Compose version supporting dry-run and health waits.
If the connection or host stops during a change, the stack directory may contain the owner-only
`.material-php-limits.previous` file. Treat it as confidential. Compare it with the current configuration
on the host, restore the intended settings, and verify the web and worker containers before retiring it.
The deployer leaves that file in place if recovery needs attention.
The request body limit must accommodate the upload and its form overhead. A course or site upload
limit can further restrict files. Review **Site administration > Security > Site security settings** in Moodle.
The first container version with these controls must be installed for the choices to take effect.

### Set reading preferences for one account

In Moodle, open **Preferences > Reading and motion preferences**. Choose a text size, line spacing
or lower animation intensity and save. The Material theme applies the choice to that account on other
devices. Select **Site default** in each field to reset. The theme respects the site's motion ceiling
and the device's reduced-motion preference.

Every setting the Material additions add, with its stored name (`plugin | name`), default and where it lives.
Settings marked with an asterisk purge the theme cache when saved.

## Material theme (`theme_material`)

Site administration > Appearance > Themes > Material.

| Tab | Setting | Name | Default | Article |
| --- | --- | --- | --- | --- |
| Look and motion | Seed colour * | `seedcolor` | `#8B5000` | [Theme look and colours](theme-look-and-colours.md) |
| Look and motion | Font * | `font` | `robotoflex` (`inter`, `atkinson`, `system`) | [Theme look and colours](theme-look-and-colours.md) |
| Look and motion | Corner roundness * | `radius` | 24 (0, 4, 8, 12, 16, 20, 24, 28, 32, 40 px) | [Theme look and colours](theme-look-and-colours.md) |
| Look and motion | Density * | `density` | `comfortable` (`compact`) | [Theme look and colours](theme-look-and-colours.md) |
| Look and motion | Motion | `motion` | `expressive` (`calm`, `off`) | [Motion and reduced motion](motion-and-reduced-motion.md) |
| Look and motion | Page transitions | `pagetransitions` | on | [Motion and reduced motion](motion-and-reduced-motion.md) |
| Look and motion | Touch ripples | `ripples` | on | [Motion and reduced motion](motion-and-reduced-motion.md) |
| Sign-in page | Sign-in heading | `loginheading` | "Learning, in full colour." | [Sign-in page](sign-in-page.md) |
| Sign-in page | Sign-in text | `logintext` | see article | [Sign-in page](sign-in-page.md) |
| Sign-in page | Sign-in labels | `loginchips` | "Passkeys, Dark mode, Material 3" | [Sign-in page](sign-in-page.md) |
| Sign-in page | Sign-in image * | `loginimage` | none | [Sign-in page](sign-in-page.md) |
| Features | Command palette | `commandpalette` | on | [Command palette](command-palette.md) |
| Features | Regex builder | `regexbuilder` | on | [Regex builder](regex-builder.md) |
| Features | Copy buttons on code | `copybuttons` | on | [Copy buttons](copy-buttons.md) |
| Features | Celebrations | `celebrations` | on | [Celebrations](celebrations.md) |
| Features | Celebration level | `celebrationlevel` | `dramatic` (`subtle`, `normal`) | [Celebrations](celebrations.md) |
| Advanced | Raw initial SCSS * | `scsspre` | empty | [Theme look and colours](theme-look-and-colours.md) |
| Advanced | Raw SCSS * | `scss` | empty | [Theme look and colours](theme-look-and-colours.md) |

Light and dark mode use Boost's `theme_boost | enablecolourmodes`.

## Passkeys (`auth_passkey`)

Site administration > Plugins > Authentication > Passkeys. Enable the plugin under Manage authentication.

| Setting | Name | Default | Article |
| --- | --- | --- | --- |
| Name shown by the device | `rpname` | empty (site name) | [Passkey sign-in](passkey-sign-in.md) |
| User verification | `userverification` | `required` (`preferred`) | [Passkey sign-in](passkey-sign-in.md) |
| Prompt timeout | `timeout` | 120 (60, 120, 300 seconds) | [Passkey sign-in](passkey-sign-in.md) |
| Offer a passkey to new accounts | `offeronfirstlogin` | on | [First sign-in flow](first-sign-in.md) |

## Material tools (`local_materialtools`)

Site administration > Users > Material tools.

| Page | Setting | Name | Default | Article |
| --- | --- | --- | --- | --- |
| Student account settings | Credentials message | `discordtemplate` | see article | [Add a student](add-a-student.md) |
| Student account settings | Temporary password style | `passwordstyle` | `words` (`random`) | [Add a student](add-a-student.md) |
| Student account settings | Random password length | `passwordlength` | 14 | [Add a student](add-a-student.md) |
| Student account settings | Role when enrolling | `studentrole` | the `student` role | [Add a student](add-a-student.md) |
| Custom vocabulary | Who sees it | `vocabaudience` | `selected` (`nobody`, `everyone`) | [Custom vocabulary pack](custom-vocabulary-pack.md) |
| Custom vocabulary | Cohorts that see it | `vocabcohorts` | none | [Custom vocabulary pack](custom-vocabulary-pack.md) |
| (in progress) | Quick post buttons | `quickposts` | `Dropbox\|assign` | [Per-assignment type labels](assignment-type-labels.md) |

The Custom vocabulary page also keeps `vocabhash`, `vocabcount` and `vocabtime` as internal state. Site wording
writes role names and a language override file rather than settings; see [Site wording](site-wording.md).

## Pages and capabilities

| Page | Path | Needs |
| --- | --- | --- |
| Add a student | `/local/materialtools/newstudent.php` | `local/materialtools:createstudents` |
| Custom vocabulary | `/local/materialtools/vocabulary.php` | `local/materialtools:managevocabulary` |
| Site wording | `/local/materialtools/wording.php` | `moodle/site:config` |
| Passkeys (own) | `/auth/passkey/manage.php` | any signed-in, non-guest account |
| Passkey sign-in | `/auth/passkey/login.php` | the plugin enabled |
| Passkey step | `/auth/passkey/welcome.php` | signed in |

## Command line

`php public/local/materialtools/cli/configure.php --first-run` applies the Material defaults (theme, colour modes,
seed colour from `MATERIAL_SEED_COLOR`, passkeys unless `MATERIAL_ENABLE_PASSKEYS=0`, vocabulary audience) and
purges caches. Without `--first-run` it does nothing. The Docker entrypoint runs it once after installation.
