# fiCMS Reviews

Third-party fiCMS plugin for admin-managed customer reviews.

## Installation

Install the repository through fiCMS Addons. The repository name and plugin id are `fiCMS-reviews`, so Addons installs it to `system/plugins/fiCMS-reviews`.

## Admin

The plugin adds the settings screen `Info > Reviews`. Reviews are stored inside the plugin directory in `data/reviews.json`.

Fields:
- author
- source
- rating
- text
- language
- date
- published
- featured

## Widget

Use the widget tag in a fiCMS widget block:

```html
[widget=reviews]6[/widget]
```

The optional inner value is the maximum number of reviews. Without a value the widget renders six published reviews for the current language plus language-independent reviews.

Widget block options are supported through `widgetnum` for the limit and `widgetvalue` for the minimum rating.
