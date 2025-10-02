# ACF Events Manager

A lightweight WordPress plugin that renders event dates/times from Advanced Custom Fields (ACF) using the shortcode `[event_date]`. Useful when you manage events with ACF instead of a full events plugin.

## What it does

- Provides a shortcode `[event_date]` that outputs a friendly date (and time) range based on ACF fields on the current post.
- Handles both single and recurring events.
- Supports all-day events by omitting the time portion automatically.

## Requirements

- WordPress 5.6+
- PHP 7.4+
- Advanced Custom Fields (free or Pro)
  

## Installation

1. Download or clone this repository into your WordPress `wp-content/plugins` directory as `acf-events-manager`.
2. In your WordPress admin, go to Plugins and activate “ACF Events Manager”.
3. Ensure your posts (or custom post types) have the ACF fields defined below.

If ACF is not active, the plugin will not activate and will display a message asking you to enable ACF first.

## ACF Fields Expected

On the event post, the shortcode expects the following ACF fields. Field keys and return formats can vary; names are what matter here.

- `all_day_event` (Checkbox, True/False, or Select)
	- Interprets truthy values like “Yes”, “True”, “1”, or an array containing those labels.
	- If true, times are omitted.

- For single events:
	- `event_start_date` (Text/DateTime string)
		- Example formats supported: `F j, Y g:i a` or `F j, Y` (e.g., “July 12, 2024 6:00 pm” or “July 12, 2024”).
	- `event_end_date` (optional, Text/DateTime string)

- For recurring events:
	- `is_recurring_event` (Select/Text) — set to “Yes” to treat as recurring
	- `recurring_event` (Group) with:
		- `first_date` (Date or DateTime)
		- `last_date` (Date or DateTime)

Notes:
- Date strings are parsed with `DateTime::createFromFormat` using the formats above; Date/DateTime fields that return `DateTime` objects are also supported.

## Usage

Place the shortcode where you want the event date to appear, e.g., inside post content or template files.

- In post content:
	- `[event_date]`

- In a PHP template:
	- `echo do_shortcode('[event_date]');`

### Output patterns

Depending on the field values, output looks like:

- Single all-day event, one day: `July 12, 2024`
- Single all-day event, multi-day: `July 12, 2024 - July 14, 2024`
- Single event, same start/end date different times: `July 12, 2024 from 6:00-8:00 pm`
- Single event, different days with times: `July 12, 2024 6:00 pm to July 13, 2024 9:00 am`
- Recurring event (dates only):
	- Same day: `July 12, 2024`
	- Same month: `July 12-14, 2024`
	- Same year: `July 12 - August 2, 2024`
	- Different years: `December 30, 2024 - January 2, 2025`

## Developer notes

- The core logic lives in `acf-events-manager.php` and registers the `[event_date]` shortcode.
- Helper `is_all_day_event($post_id)` normalizes various ACF checkbox/select/boolean returns.
- `format_date_range_dates_only($start, $end)` handles range display for date-only outputs.

### GitHub hosting and automatic updates

This plugin can self-update from GitHub using the Plugin Update Checker (PUC) library if it’s present.

1) Host on GitHub
- Create a repository named `acf-events-manager` under your GitHub account (e.g., `daniellwaters/acf-events-manager`).
- Push this plugin code to that repo (see commands below).

2) Add Plugin Update Checker (optional but recommended)
- Download the library from https://github.com/YahnisElsts/plugin-update-checker (Download ZIP),
- Place it in `plugin-update-checker/` inside this plugin directory.
- The plugin will automatically load `plugin-update-checker/plugin-update-checker.php` if present.

3) Tag releases for updates
- Create GitHub Releases with semantic version tags (e.g., `v1.0.1`).
- When a site with this plugin checks for updates, it will see newer tags and offer an update in the WP admin Plugins screen.

#### Example: Initialize Git and push to GitHub

From the plugin root directory:

```bash
git init
git add .
git commit -m "Initial plugin version"
git branch -M main
git remote add origin git@github.com:YOUR_GITHUB_USERNAME/acf-events-manager.git
git push -u origin main
```

To cut a new release version:

```bash
# Update the Version header in acf-events-manager.php
git commit -am "chore: bump version to 1.0.1"
git tag v1.0.1
git push --tags
```

Notes:
- If using GitHub release assets (zip), ensure the release has an uploaded asset; the updater is configured to prefer release assets if available.
- The updater tracks the `main` branch by default when not using tags, but tags/releases are the most reliable signal for versioning.

## Troubleshooting

- If nothing displays, ensure `event_start_date` is present and in a supported format.
- If you see “Invalid date”, one of the dates could not be parsed. Check your ACF return format and saved values.
- Confirm ACF is active and the fields are assigned to the relevant post type and screen.

## License

GPLv2 or later

