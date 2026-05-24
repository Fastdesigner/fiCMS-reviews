# Upcoming Changes

This note captures the next planned work for `fiCMS-reviews` so another agent can continue without re-discovering the intended direction.

## Current State

- Plugin id and repository name are `fiCMS-reviews`.
- Addons installs the repository to `system/plugins/fiCMS-reviews`.
- Reviews are currently file-backed in `data/reviews.json`.
- Admin integration lives in `settings/info/reviews.php`.
- Frontend widget lives in `widgets/reviews/widget.php`.
- Default widget frame lives in `widgets/reviews/frame.html`.
- Widget options currently include:
  - `widgetnum` for amount.
  - `widgetvalue` for minimum rating.
  - `show_rating`.
  - `show_source`.
  - `show_date`.

## V1 Work Still Planned

### 1. Move Data Logic Into A Class

Create `src/Reviews.php` as the data owner. Because the plugin directory is `fiCMS-reviews`, do not use `fiCMS-reviews` as a PHP namespace; hyphens are not valid namespace characters. Before implementing, check the fiCMS plugin autoloader constraints and choose the smallest compatible loading strategy.

Acceptable V1 approaches:
- add a plugin-local class and `require_once` it from `settings/info/reviews.php` and `widgets/reviews/widget.php`
- or adjust the class namespace/path only if fiCMS already has an established plugin namespace convention for hyphenated repository ids

Do not change fiCMS core autoloading just for this plugin in V1.

The class should own:
- data file path resolution
- loading `data/reviews.json`
- default structure
- normalizing V1 data
- saving
- creating ids
- filtering published reviews
- resolving multilingual values
- sorting

Settings and widget files should become orchestration only:
- `settings/info/reviews.php` builds and handles the admin UI.
- `widgets/reviews/widget.php` asks the class for filtered render rows.

Do not introduce a database table in V1 unless explicitly requested.

### 2. Keep And Expand Template-Based Rendering

Keep `widgets/reviews/frame.html` as the plugin default.

Support multiple repeat/template regions:
- `list` for normal review cards.
- `slider` for slider/card-strip output.
- `summary` for aggregate summary output.

Suggested frame structure:
- Keep `frame` as the wrapper.
- Keep `[repeat=list]` as the default item template.
- Add optional `[repeat=slider]` and `[repeat=summary]` sections that render when the selected layout needs them.

Design override behavior must continue to support:
- `designs/<design>/widgets/review/frame.html`
- `designs/<design>/widgets/reviews/frame.html`

The singular `review` override should remain first because the user explicitly requested that path.

Important parser rule:
- Never pass `$reviews['structure']['list']`, `slider`, or `summary` directly into `parser__replace()`.
- `parser__replace()` takes the string by reference.
- Always copy the template into a line variable before replacement.

Example:

```php
$reviews['line'] = $reviews['structure']['list'];
$reviews['items'][] = parser__replace($reviews['line'],$reviews['item']);
```

### 3. Add Widget Layout Options

Add a layout option for the widget, likely `reviews_layout` or similar.

Initial layouts:
- `list`
- `slider`
- `summary`

Keep option naming consistent with existing fiCMS widget option patterns. Before adding a new name, inspect current widget option handling and avoid duplicating a standard option if one already exists.

The selected layout should determine which repeat section is used. If a selected layout has no matching repeat section in the active frame, fall back to `list`.

### 4. Add Admin And Widget Filters

Filters make sense already in V1.

Admin filters:
- search text
- language
- published/draft
- featured
- rating

Widget filters/options:
- amount
- minimum rating
- featured only
- current language / all matching
- show rating
- show source
- show date
- sort mode

Sort modes:
- featured first, newest first
- newest first
- highest rating first

Keep filtering in the data class, not duplicated between admin and widget.

### 5. Preserve Backward Compatibility

Existing simple string data must remain readable:
- `author` as string
- `source` as string
- `text` as string
- `lid` as string

The normalization layer may expose these as arrays internally, but should not destroy existing data unexpectedly.

## V2 Boundary

External source integrations belong to V2, not the immediate V1 cleanup.

Potential V2 connectors:
- Google reviews
- Trusted Shops
- ProvenExpert
- CSV/JSON import as a first manual bridge

V2 should add source ownership and synchronization rules before adding provider-specific logic:
- source type
- external id
- sync timestamp
- duplicate detection
- read-only imported reviews vs locally editable reviews
- provider-specific legal/SEO constraints

Do not add external source assumptions to V1 storage or rendering.

## Verification Checklist

Before handoff:
- Run `php -l` on changed PHP files.
- Validate all JSON localization files.
- Run `git diff --check`.
- Smoke-test at least two reviews so repeat templates are not mutated by `parser__replace()`.
- Smoke-test a design override at `designs/<design>/widgets/review/frame.html`.
