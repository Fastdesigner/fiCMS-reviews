# Upcoming Changes

This note captures planned work for `fiCMS-reviews` so another agent can continue without re-discovering the intended direction.

## Current State

- Plugin id and repository name are `fiCMS-reviews`.
- Addons installs the repository to `system/plugins/fiCMS-reviews`.
- Reviews are file-backed in `data/reviews.json`.
- Provider integrations and sync metadata are file-backed in `data/integrations.json`.
- Review data ownership lives in `src/Reviews.php`.
- Admin integration lives in `settings/pages/reviews.php`.
- Frontend widget lives in `widgets/reviews/widget.php`.
- Default widget frame lives in `widgets/reviews/frame.html`.
- Default widget blocks live in `widgets/reviews/blocks/*.html`.
- Design override behavior supports `designs/<design>/widgets/review/` first, then `designs/<design>/widgets/reviews/`. `DESIGNSYSTEM` is not used for review widget layout overrides.

## V1 Cleanup Implemented

- `src/Reviews.php` owns data file path resolution, loading, default structures, V1 normalization, saving, id creation, filtering, multilingual value resolution, and sorting.
- The plugin uses a local `require_once` loading strategy for `src/Reviews.php`. The fiCMS plugin autoloader cannot directly map the hyphenated plugin id `fiCMS-reviews` to a PHP namespace without either a different namespace convention or a core autoloading change.
- `settings/pages/reviews.php` builds and handles the admin UI, but delegates review loading, saving, deletion, filtering, and sorting to the class.
- `widgets/reviews/widget.php` asks the class for filtered render rows and aggregate summary data.
- No database table was introduced for V1.
- Existing simple string data remains readable for `author`, `source`, `text`, and `lid`.

## Widget Rendering

The default widget frame contains:
- `frame` as the wrapper.
- `###summary###` for summary output.
- `###items###` for all non-summary layouts.

The default widget blocks contain:
- `widgets/reviews/blocks/list.html` as the normal review-card partial.
- `widgets/reviews/blocks/slider.html` as the slider/card-strip partial.
- `widgets/reviews/blocks/summary.html` as the aggregate-summary partial.

The selected review layout determines which item block file is used. If a selected review layout has no matching block file, rendering falls back to `list`.
Summary display is controlled separately with `reviews_summary_mode`: `none`, `global`, or `provider`.
Review item display is controlled separately with `reviews_show_items`; list/slider-specific options depend on it.
Block partials must not contain `[repeat=...]`; `widgets/reviews/widget.php` owns repetition.
For design frames, PHP fills both the generic `###items###` placeholder and the selected concrete placeholder such as `###list###`, `###slider###`, or `###compact###`.

Important parser rule:
- Never pass `$reviews['structure']['list']`, `slider`, or `summary` directly into `parser__replace()`.
- `parser__replace()` takes the string by reference.
- Always copy the template into a line variable before replacement.

## Widget Options

Current widget options:
- `widgetnum` for amount.
- `widgetvalue` for minimum rating.
- `reviews_summary_mode` for summary display.
- `reviews_show_items` for review item display.
- `reviews_layout` as a datalist generated from `widgets/reviews/blocks/*.html` and matching `DESIGNPATH` additions, excluding `summary`.
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
- The Google dependency plugin provides `oauth/provider/google.json` for the shared `oauth` plugin and exposes `\google\BusinessProfile`.

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

## Provider Integration Flow Implemented

- Admin has an `Integrationen` tab next to the shared review overview.
- Integrations are shown as provider list entries, following the `fiCMS-booking` model. Do not add one tab per provider.
- Provider classes live in `src/Providers/*Provider.php` and extend `FiCMSReviewsProvider`.
- `src/Reviews.php` discovers provider classes dynamically and builds provider definitions from `Provider::definition()`.
- Provider-specific requirements such as OAuth, connect action, location choices, form fields, secret status fields, and config error keys are returned through `Provider::requirements()`.
- `src/Reviews.php` must not branch on provider ids for saving, status, connecting, or syncing. It delegates to the provider methods `saveIntegration()`, `status()`, `connect()`, and `sync()`.
- `Neue Integration` first stores label and provider. Provider-specific setup happens on the saved integration entry.
- Google exposes an additional connect/reconnect action. `assets/js/settings/reviews.js` starts OAuth in a popup window, shows a popup-action hint, and polls the saved integration status every ten seconds. After the OAuth account is connected, the settings script reloads the integration form so the admin can select the Business Profile source and save.
- Integration setup saves run through the plain `settings__load()` path, but PHP must return the refreshed integration form without `result.result === true`. A true form result closes the form panel before the next required setup step is visible.
- The integration status dropdown is rendered after provider fields and provider location selection, so setup fields stay first in the form.
- If Google source loading fails with an OAuth error, the integration form marks the connected state as requiring reconnect and exposes a reconnect action even though the local OAuth account file still exists.
- Existing provider-specific connection actions live on that integration entry.
- Provider logos are resolved from `assets/img/providers/<provider>.svg|png|webp`.
- The main view does not expose raw Google account/location fields.
- OAuth starts through the existing `/oauth.php?action=authorize&provider=google&account=<account-ref>` flow, which delegates to `\oauth\OAuth::authorize(...)`.
- The selected OAuth account ref, resolved Business Profile account/location, last sync, last counts, and last error are stored on the integration entry in `data/integrations.json`.
- If Google exposes exactly one account/location, the integration form shows it preselected after OAuth. If multiple locations exist, the same form shows all location choices after OAuth is connected.
- OAuth connection and sync readiness are separate states: a connected Google OAuth account is not sync-ready until a Business Profile location is selected or uniquely resolved.
- The popup connection hint must only be visible while connecting, and sync actions should only be offered once the integration is ready.
- A missing Google location is configuration state, not a provider sync failure; do not persist `google_location_missing` as the last sync error.
- `cron/reviews.php` delegates daily sync for all active integrations to `src/Reviews.php`.
- The admin can trigger a manual sync per integration; this deletes/bypasses the integration timer through `forceSyncIntegration()`.
- Imported Google reviews are normalized in `src/Reviews.php` with `provider`, `source_type`, `external_id`, `external_updated`, `imported`, and `read_only`.
- Provider + external review id is the primary duplicate key. A provider-local fallback by author/rating/date/text is only used when Google does not return an external id.
- Imported Google content is read-only. The shared admin overview allows local language visibility, published state, and featured state.
- Widget rendering still consumes normalized review rows from `Reviews`; provider-specific API calls do not belong in `widgets/reviews/widget.php`.
- Widget output is merged by default and can be filtered with `reviews_provider`.

## Tripadvisor Terra Provider

- The provider key is `tripadvisor`.
- The admin stores the Terra API key on the integration entry in `data/integrations.json`; the key must never be committed to the repository.
- The integration needs a Tripadvisor Location ID. The optional display name is only used as the normalized review source label.
- Sync calls `GET https://terra.tripadvisor.com/api/locations/{id}/reviews` with `X-API-KEY`, `sort_by=MOST_RECENT`, `page=1`, and `size=3`.
- Sync runs once per supported installed language using Terra's `language` query parameter. Terra returns localized `title` and `text` arrays with language metadata; unsupported installed languages are skipped.
- Imported reviews are accumulated by provider + external review id, because the endpoint may only expose the newest reviews for the location.
- Terra's public Caching Policy says storing/copying content is not permitted unless the Tripadvisor contract explicitly allows it. The daily collection behavior assumes the active contract allows server-side review storage.

## Temporary Provider Refresh Cleanup

- Google review language sync introduced a one-time cleanup cutoff at `2026-06-20 10:00 Europe/Berlin`.
- Imported provider reviews older than that cutoff are removed once so the cron can rebuild Google reviews with per-language `Accept-Language` text.
- The OAuth account and integration configuration must not be removed by this cleanup.
- After affected installations have rebuilt their Google review data, remove this temporary cutoff/version code from `src/Reviews.php` and the provider sync path as dead code.

## Google OAuth Bridge Findings

- `fiCMS-reviews` must not ship or expect Google client credentials.
- The `google` plugin must not contain `oauth/clients/google.json`, not even as an empty template. Client credentials are operator/runtime configuration, not provider code.
- The `google` plugin owns provider metadata at `oauth/provider/google.json`, including `redirect_fixed`.
- The central Google OAuth app uses exactly this authorized redirect URI:
  `https://fastdesign.de/oauth.php?action=callback`
- The real central client file belongs only on the Fastdesign CMS installation:
  `system/plugins/oauth/clients/google.json`
- Customer installations do not need `client_id` or `client_secret`.
- Customer installations start `/oauth.php?action=authorize&provider=google&account=<account-ref>` from their own domain.
- Because Google uses the fixed Fastdesign redirect, core OAuth delegates through the bridge:
  customer popup -> Fastdesign OAuth bridge -> Google -> Fastdesign callback -> customer `bridge_pull`.
- Fastdesign exchanges the one-time Google authorization code and writes a short-lived handoff under `system/plugins/oauth/bridge`.
- The customer installation pulls that handoff server-side, stores the OAuth account locally under `system/plugins/oauth/accounts/google/<account-ref>.json`, and then the popup notifies the admin opener.
- Daily review sync and manual sync run from the customer installation. Provider API calls do not belong in admin rendering or widget rendering.
- Refresh also starts from the customer installation. If the customer has no local client credentials, core OAuth calls Fastdesign `bridge_refresh`; Fastdesign uses the central client credentials to refresh and returns the refreshed account data without keeping it as plugin state.
- If the callback reaches `https://fastdesign.de/oauth.php?action=callback` and then says `OAuth: Autorisierung fehlgeschlagen`, Google login already succeeded and the failure is the token exchange on Fastdesign. Reusing that callback URL will not work because Google authorization codes are one-time use.
- Core now surfaces token exchange errors through `OAuth::last_error()`, so the next failed attempt should show the actual Google error such as `invalid_client`, `invalid_grant`, `redirect_uri_mismatch`, or a PKCE-related message.

## Runtime Path Findings

- `src/Reviews.php` must normalize installed plugin storage to `PLUGINPATH.'/fiCMS-reviews'`.
- Passing an absolute path such as `/var/www/.../system/plugins/fiCMS-reviews/data/reviews.json` into `helper__files_write()` can create a wrong relative `var/www/...` directory tree under the CMS root.
- Runtime review data remains in `system/plugins/fiCMS-reviews/data/reviews.json`.
- Runtime integration data remains in `system/plugins/fiCMS-reviews/data/integrations.json`.
- Provider logo URLs must be public URLs based on `PAGEPATH.'/'.PLUGINPATH.'/fiCMS-reviews/assets/...`, not filesystem paths.
- `.update.ignore` marker files are no longer part of the OAuth/plugin data model.

## Current Test Path

- Test on the customer installation, for example `https://neu.fell-ab.de`, not by reusing an old callback URL.
- Open admin content settings, then `Bewertungen`, then `Integrationen`.
- Open an existing Google integration or create a new one.
- Trigger connect from the integration entry; OAuth should open in a separate popup.
- After successful consent, the popup should return to the customer installation, store the local OAuth account, notify the opener, and close.
- After connection, resolve/select the Google Business Profile location if needed, then run manual sync.

## Verification Checklist

Before handoff:
- Run `php -l` on changed PHP files.
- Validate all JSON localization files.
- Run `git diff --check`.
- Smoke-test at least two reviews so repeat templates are not mutated by `parser__replace()`.
- Smoke-test a design override at `designs/<design>/widgets/review/frame.html`.
- For Google OAuth, start a fresh flow from the reviews integration UI; never retest with an old Google callback URL.
