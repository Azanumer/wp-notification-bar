# WP Notification Bar

A dismissible announcement bar at the top of your site — sales, notices, maintenance warnings, holiday hours. One option, no JavaScript libraries, no database table.

## Features

- **Fixed or static** — stays pinned while scrolling, or scrolls away with the page.
- **Dismissible** — visitors can close it; the dismissal is remembered in a cookie for a configurable number of days.
- **Scheduling** — optional start and end dates, so a sale banner turns itself off.
- **Targeting** — show on every page, the home page only, single posts only, or pages only; and to everyone, logged-in users only, or logged-out visitors only.
- **Link support** — optional link URL + link text appended to the message.
- **Colours** — background and text colour pickers; the bar inherits your font.

## Filters

```php
// Change the message in code
add_filter( 'wnb_bar_text', function( $text ) {
    return 'Flash sale ends tonight!';
} );

// Decide in code whether the bar shows at all
add_filter( 'wnb_show_bar', function( $show, $settings ) {
    return $show && ! is_page( 'checkout' );
}, 10, 2 );
```

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Go to **Settings → Notification Bar**, write your message and tick **Enable bar**.

## Notes

- The bar renders on `wp_body_open` (with a `wp_footer` fallback for themes that lack it).
- Dismissal uses a plain `SameSite=Lax` cookie named `wnb_dismissed`; no personal data is stored.
- Uninstalling deletes the single `wnb_settings` option.

## License

MIT — see [LICENSE](LICENSE).
