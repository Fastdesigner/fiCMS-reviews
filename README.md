# fiCMS Reviews

Third-party fiCMS plugin for admin-managed customer reviews.

## Installation

Install the repository through fiCMS Addons. The repository name and plugin id are `fiCMS-reviews`, so Addons installs it to `system/plugins/fiCMS-reviews`.

## Admin

The plugin adds the settings screen `Pages > Reviews`. Reviews are stored inside the plugin directory in `data/reviews.json`, with file-backed data access owned by `src/Reviews.php`.

Fields:
- languages
- author per selected language
- source per selected language
- rating
- text per selected language
- date
- published
- featured
- external provider URL when the provider response exposes one

The integrations tab manages review providers as list entries following the `fiCMS-booking` model. `Neue Integration` first asks for label and provider; `Verbinden` saves the entry and starts OAuth in a popup window. The plugin settings script polls the saved integration every ten seconds while the popup is open. After successful OAuth, the integration form reloads, the admin chooses the Business Profile source, and saves. Existing entries expose connect, sync, status, and delete actions. Provider logos are resolved from `assets/img/providers/<provider>.svg|png|webp`. The selected OAuth account, resolved Business Profile source, sync state, and errors are stored in `data/integrations.json`. The plugin cron syncs active integrations daily. Imported provider reviews stay read-only for provider-owned content, but the admin can locally change language visibility, published state, and featured state in the shared review overview.
Provider review links are stored as `external_url` only when the provider returns a real URL for the individual review. The Google Business Profile review resource currently used by the plugin does not expose a public single-review browser URL, so the Google importer does not synthesize one from the API resource id.

## Widget

Use the widget tag in a fiCMS widget block:

```html
[widget=reviews]6[/widget]
```

The optional inner value is the maximum number of reviews. Without a value the widget renders six published reviews for the current language.

Widget block options are supported through `widgetnum` for the limit and `reviews_min_rating` for the minimum rating. Existing blocks that still store the old `widgetvalue` minimum rating continue to render through a legacy fallback.

When a review uses `all` languages, the widget may render it in every frontend language and picks the matching translated text when available.

Widget display options:
- `reviews_summary_mode` (`none`, `global`, `provider`)
- `reviews_show_items`
- `reviews_layout` (`list`, `slider`, plus design-provided item layouts)
- `reviews_sort` (`featured`, `date`, `rating`) with `reviews_sort_dir`
- `reviews_featured`
- `reviews_language` as installed-language multipicker
- `reviews_provider` as source multipicker, defaulting to all sources
- `show_rating`
- `show_source`
- `show_date`

## Markup

The default widget frame is in `widgets/reviews/frame.html`. Repeated item markup lives in `widgets/reviews/blocks/*.html`; block partials must not contain `[repeat=...]`.
Review item placeholders include `author`, `author_initials`, `source`, `text`, `rating_value`, `rating_label`, `rating_stars`, `date`, `datetime`, `external_url`, and `has_external_url`. Summary placeholders include `summary_rating_value` for CSS-driven `[data-rating] > [data-rateme]` visuals and `summary_rating_stars` for legacy text-star templates.

Designs can override it with:
- `designs/<design>/widgets/review/frame.html`
- `designs/<design>/widgets/review/blocks/<layout>.html`
- `designs/<design>/widgets/reviews/frame.html`
- `designs/<design>/widgets/reviews/blocks/<layout>.html`

The singular `review` path is checked first for design overrides. The plugin default remains `widgets/reviews/frame.html`.
