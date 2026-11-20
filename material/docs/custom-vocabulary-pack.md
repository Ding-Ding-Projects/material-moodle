# Custom vocabulary pack

**Status:** Available. Plugin: `local_materialtools`, page `/local/materialtools/vocabulary.php`
(Site administration > Users > Material tools > Custom vocabulary).

## What it does

A vocabulary pack is a JSON file that replaces interface words with your own words, for the signed-in people you
choose. For example, a pack can show "Hand-in" wherever the interface says "Submission". Signed-out pages always
keep the original wording.

The pack is applied in the browser after the page loads: words in visible text and in `placeholder`, `title`,
`aria-label` and `alt` attributes are replaced, and so is the window title. Text inside `script`, `style`,
`textarea`, `input`, `select`, `option`, `code`, `pre`, `kbd`, `samp`, `noscript`, editable content and anything
marked `data-vocab-skip` is never changed. Content added to the page later is rewritten too.

Matching is case-insensitive and whole-word (a key never matches inside a longer word). Longer keys win over
shorter ones. When the original word starts with a capital letter and the replacement does not, the replacement's
first letter is capitalised.

## File format

```json
{
  "schemaVersion": 1,
  "name": "Our school words",
  "entries": {
    "submission": "hand-in",
    "assignment": "task"
  }
}
```

| Rule | Limit |
| --- | --- |
| Encoding | UTF-8 (a byte-order mark is ignored) |
| File size | at most 256 KB (262,144 bytes) |
| Root | an object with `schemaVersion` and `entries` |
| `schemaVersion` | exactly `1` |
| `entries` | an object; at least one entry |
| Keys | 1 to 160 characters (Unicode code points), not only spaces |
| Values | strings of 1 to 1,000 characters |
| `name` | optional string; up to 80 characters are kept and shown as the headline when the pack is applied |
| Nesting | at most 8 levels deep anywhere in the file |
| Duplicate keys | rejected anywhere in the file |

The file is read by a strict JSON reader (`classes/strict_json.php`) because PHP's own decoder silently keeps the
last of two duplicate keys. Any rule that fails rejects the whole file with a specific message, and the previous
pack stays in place. The stored copy contains only `schemaVersion` and `entries`.

## Uploading

- On the Custom vocabulary page: **Choose file**, or drop a `.json` file on the drop zone.
- Anywhere on the site: people with `local/materialtools:managevocabulary` can drop a `.json` file onto any page.
  A full-window headline confirms the result ("Personal wording applied" or the pack's `name`) with a celebration,
  or shows why the file was not applied. Drops onto Moodle's own file pickers and upload areas are left to Moodle.
- **Clear and restore original wording** removes the pack.

## Who sees it

Only signed-in people, never guests, and only these audiences (`local_materialtools | vocabaudience`):

| Audience | Who |
| --- | --- |
| Nobody | No one; the pack stays stored but unused. |
| Everyone signed in | Every signed-in person. |
| Selected students and cohorts (default) | People chosen individually (on this page, or with the checkbox on Add a student), plus members of the chosen cohorts (`local_materialtools | vocabcohorts`). |

People are chosen with the user preference `local_materialtools_vocab`. The pack is served by
`/local/materialtools/vocab.php`, which requires sign-in and returns an empty pack to anyone not in the audience. The
URL carries a hash of the pack, so browsers cache it for a day and fetch a new copy as soon as the pack changes.

## Permissions

`local/materialtools:managevocabulary` (managers by default; configuration risk).

## When something goes wrong

| Message | Cause |
| --- | --- |
| The file has no terms. | Empty file or empty `entries`. |
| The file is larger than 256 KB. | Size limit. |
| The file is not UTF-8 text. | Encoding. |
| The file is not valid JSON. | Syntax error. |
| The file repeats the same key. | Duplicate key. |
| The file is nested more than 8 levels deep. | Depth limit. |
| The file must be an object with "schemaVersion" and "entries". | Wrong shape. |
| Only schemaVersion 1 is supported. | Version. |
| A key is empty or longer than 160 characters. | Key limit. |
| A value is not text, is empty, or is longer than 1,000 characters. | Value limit. |

If the pack cannot be loaded in the browser, the original wording simply stays.

## Security and privacy

- Replacement happens only in the browser of an eligible signed-in person; the server's HTML is unchanged, so
  search engines, signed-out pages and people outside the audience never receive the pack.
- Values are inserted as text, never as HTML.
- The privacy provider declares and exports the `local_materialtools_vocab` preference.

## How to verify

1. Upload the example above, set the audience to Everyone signed in, open an assignment: "Submission" reads
   "Hand-in".
2. Sign out: the sign-in page and front page show the original wording.
3. Upload a file with the same key twice: "The file repeats the same key." and the old pack still applies.

## Related articles

- [Site wording](site-wording.md)
- [Per-assignment type labels](assignment-type-labels.md)
- [Add a student and Discord credentials](add-a-student.md)
