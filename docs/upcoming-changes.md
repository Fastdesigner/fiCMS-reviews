# Upcoming Changes

This note captures planned work for `fiCMS-reviews` so another agent can continue without re-discovering the intended direction.

## Current State

- Plugin id and repository name are `fiCMS-reviews`.
- Addons installs the repository to `system/plugins/fiCMS-reviews`.
- Reviews are file-backed in `data/reviews.json`.
- Google connection and sync metadata are file-backed in `data/integrations.json`.
- Review data ownership lives in `src/Reviews.php`.
- Admin integration lives in `settings/info/reviews.php`.
- Frontend widget lives in `widgets/reviews/widget.php`.
- Default widget frame lives in `widgets/reviews/frame.html`.
- Design override behavior supports `designs/<design>/widgets/review/frame.html` first, then `designs/<design>/widgets/reviews/frame.html`.

## V1 Cleanup Implemented

- `src/Reviews.php` owns data file path resolution, loading, default structures, V1 normalization, saving, id creation, filtering, multilingual value resolution, and sorting.
- The plugin uses a local `require_once` loading strategy for `src/Reviews.php`. The fiCMS plugin autoloader cannot directly map the hyphenated plugin id `fiCMS-reviews` to a PHP namespace without either a different namespace convention or a core autoloading change.
- `settings/info/reviews.php` builds and handles the admin UI, but delegates review loading, saving, deletion, filtering, and sorting to the class.
- `widgets/reviews/widget.php` asks the class for filtered render rows and aggregate summary data.
- No database table was introduced for V1.
- Existing simple string data remains readable for `author`, `source`, `text`, and `lid`.

## Widget Rendering

The default widget frame contains:
- `frame` as the wrapper.
- `[repeat=list]` for normal review cards.
- `[repeat=slider]` for slider/card-strip output.
- `[repeat=summary]` for aggregate summary output.

The selected layout determines which repeat section is used. If a selected layout has no matching repeat section in the active frame, rendering falls back to `list`.

Important parser rule:
- Never pass `$reviews['structure']['list']`, `slider`, or `summary` directly into `parser__replace()`.
- `parser__replace()` takes the string by reference.
- Always copy the template into a line variable before replacement.

## Widget Options

Current widget options:
- `widgetnum` for amount.
- `widgetvalue` for minimum rating.
- `reviews_layout` as a datalist generated from the active frame repeat regions.
- `reviews_sort` as a toggle with `featured`, `date`, `rating` plus `reviews_sort_dir`.
- `reviews_featured` for featured-only output.
- `reviews_language` as a multipicker using the `installed-languages` datalist.
- `reviews_provider` as a source multipicker with default `all`.
- `show_rating`.
- `show_source`.
- `show_date`.

## Admin Filters

Current admin filters:
- search text
- language
- published/draft
- featured
- rating
- provider/source
- sort by newest, featured first, or highest rating

Filtering is kept in the data class, not duplicated between admin and widget.

## V2 Boundary

External source integrations belong to V2.

The dependency base is already prepared:
- `plugin.json` declares `google` as required dependency from `Fastdesigner/google`.
- The fiCMS Git updater can install manifest dependencies before continuing the parent plugin update.
- The Google dependency plugin provides `provider/google.json` for `system/plugins/oauth` and exposes `\google\BusinessProfile`.

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

## Google Reviews Flow Implemented

- Admin has a `Google` tab next to the shared review overview.
- OAuth starts through the existing `/oauth.php?action=authorize&provider=google&account=<account-ref>` flow, which delegates to `\oauth\OAuth::authorize(...)`.
- The selected OAuth account ref, Google Business Profile account/location, display title, last sync, last counts, and last error are stored in `data/integrations.json`.
- `cron/reviews.php` delegates daily Google sync to `src/Reviews.php`.
- The admin can trigger a manual sync; this deletes/bypasses the timer through `forceGoogleSync()`.
- Imported Google reviews are normalized in `src/Reviews.php` with `provider`, `source_type`, `external_id`, `external_updated`, `imported`, and `read_only`.
- Provider + external review id is the primary duplicate key. A provider-local fallback by author/rating/date/text is only used when Google does not return an external id.
- Imported Google content is read-only. The shared admin overview allows local language visibility, published state, and featured state.
- Widget rendering still consumes normalized review rows from `Reviews`; provider-specific API calls do not belong in `widgets/reviews/widget.php`.
- Widget output is merged by default and can be filtered with `reviews_provider`.

## Verification Checklist

Before handoff:
- Run `php -l` on changed PHP files.
- Validate all JSON localization files.
- Run `git diff --check`.
- Smoke-test at least two reviews so repeat templates are not mutated by `parser__replace()`.
- Smoke-test a design override at `designs/<design>/widgets/review/frame.html`.
