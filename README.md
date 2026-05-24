# fiCMS Reviews

Third-party fiCMS plugin for admin-managed customer reviews.

## Installation

Install the repository through fiCMS Addons. The repository name and plugin id are `fiCMS-reviews`, so Addons installs it to `system/plugins/fiCMS-reviews`.

## Admin

The plugin adds the settings screen `Info > Reviews`. Reviews are stored inside the plugin directory in `data/reviews.json`, with file-backed data access owned by `src/Reviews.php`.

Fields:
- languages
- author per selected language
- source per selected language
- rating
- text per selected language
- date
- published
- featured

The Google tab connects a Google OAuth account, stores the selected Business Profile account/location in `data/integrations.json`, and can trigger a manual sync. The plugin cron also syncs active Google locations daily. Imported Google reviews stay read-only for provider-owned content, but the admin can locally change language visibility, published state, and featured state in the shared review overview.

## Widget

Use the widget tag in a fiCMS widget block:

```html
[widget=reviews]6[/widget]
```

The optional inner value is the maximum number of reviews. Without a value the widget renders six published reviews for the current language.

Widget block options are supported through `widgetnum` for the limit and `widgetvalue` for the minimum rating.

When a review uses `all` languages, the widget may render it in every frontend language and picks the matching translated text when available.

Widget display options:
- `reviews_layout` (`list`, `slider`, `summary`)
- `reviews_sort` (`featured`, `date`, `rating`) with `reviews_sort_dir`
- `reviews_featured`
- `reviews_language` as installed-language multipicker
- `reviews_provider` as source multipicker, defaulting to all sources
- `show_rating`
- `show_source`
- `show_date`

## Markup

The default widget markup is in `widgets/reviews/frame.html`. It contains repeat regions for `list`, `slider`, and `summary`.

Designs can override it with:
- `designs/<design>/widgets/review/frame.html`
- `designs/<design>/widgets/reviews/frame.html`

The singular `review` path is checked first for design overrides. The plugin default remains `widgets/reviews/frame.html`.
