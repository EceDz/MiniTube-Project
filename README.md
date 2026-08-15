# MiniTube

A YouTube-style video platform built with PHP and MySQL. It includes user accounts, channels, video watching with likes and threaded comments, subscriptions, search, a personalized subscription feed, and a live SQL query console for exploring the database directly from the browser.

## Features

- **Accounts** — sign up / sign in (`login.html` + `login.php`), with server-side validation (username format, password length, unique username/email)
- **Feed** — personalized homepage (`feed.html` + `feed.php`) showing recent uploads from subscribed channels, with popularity badges (New / Trending / Popular) based on view count
- **Watch page** — video playback, like/unlike, subscribe/unsubscribe, and threaded (nested) comments (`watch.php`)
- **Channels** — channel pages with owner info, subscriber actions, and a list of the channel's videos (`channel.html` + `channel.php`)
- **Search** — search across videos and channels with All / Videos / Channels filters (`search.html` + `search.php`)
- **SQL console** — a built-in query tool (`sql.html` + `sql.php`) that runs `SELECT` / `INSERT` / `UPDATE` / `DELETE` statements typed by the user against the live database and renders the results, with friendly error messages for common MySQL errors
- **Synthetic data generator** — `generate_data.php` builds a large, realistic seed dataset from the included `.txt` word lists (names, bios, video titles, categories, etc.)

## Documentation

- [`ER Diagram.pdf`](ER%20Diagram.pdf) — entity-relationship diagram for the six-table schema (users, channels, videos, subscriptions, comments, likes) and how they relate.
- [`ActionFlow.pdf`](ActionFlow.pdf) — flowchart of the full application flow, from initializing the database through login, navigation, watching/searching/commenting, and the SQL console.

## Database Schema

Six tables, created and seeded by `install.php`:

| Table | Purpose |
|---|---|
| `USERS` | Accounts: credentials, profile info, bio |
| `CHANNELS` | One channel per owning user, with category and description |
| `VIDEOS` | Belongs to a channel; title, description, URL, duration, view/like counts |
| `SUBSCRIPTIONS` | Many-to-many: users subscribed to channels |
| `COMMENTS` | Comments on videos, with `parent_comment_id` for threaded replies |
| `LIKES` | Which users liked which videos |

All foreign keys cascade on delete (e.g. deleting a user removes their channel, videos, comments, likes, and subscriptions).

## Requirements

- PHP with the `mysqli` extension
- MySQL / MariaDB
- A local web server capable of running PHP (e.g. PHP's built-in server, Apache, or XAMPP/MAMP)

## Setup

1. Make sure MySQL is running locally, and that a `root` user exists with password `mysql` — this is the hardcoded connection used throughout the project (`localhost` / `root` / `mysql`). Update the credentials in each `.php` file if your local setup differs.
2. (Optional) Regenerate the seed data:
   ```bash
   php generate_data.php
   ```
   This reads the `.txt` files in the project folder and writes a fresh `seed.sql`. A `seed.sql` is already included, so this step is only needed if you want different/regenerated sample data.
3. Initialize the database — this drops and recreates the `ece_duzgec` database, creates all six tables, and loads `seed.sql`:
   ```bash
   php install.php
   ```
   Or visit `install.php` in the browser via your local web server.
4. Serve the project and open `index.html` as the entry point:
   ```bash
   php -S localhost:8000
   ```
   Then visit `http://localhost:8000/index.html`.

## Seed Data

The generated dataset includes:
- 100 users, 50 channels, 200 videos
- 600 subscriptions, 250 comments, 100 likes (approximate — see `generate_data.php` for exact record counts per table)

Generated user passwords follow a predictable pattern (uppercase letters of the first name + reversed 2-digit index + `!`) so you can log in as any seeded user — see `generate_data.php` for the exact scheme, or just sign up a fresh account via `login.html`.

## Project Structure

```
.
├── ActionFlow.pdf       # Application flowchart (see Documentation)
├── ER Diagram.pdf       # Database entity-relationship diagram (see Documentation)
├── LICENSE
├── README.md
└── MiniTube/
    ├── index.html          # Landing/entry page
    ├── login.html / .php   # Sign in & sign up
    ├── feed.html / .php    # Subscription feed / homepage
    ├── watch.php            # Video watch page (likes, comments, subscribe)
    ├── channel.html / .php # Channel pages
    ├── search.html / .php  # Search
    ├── sql.html / .php     # In-browser SQL query console
    ├── install.php          # Creates & seeds the database
    ├── generate_data.php    # Builds seed.sql from the .txt data files
    ├── seed.sql              # Pre-generated seed data
    ├── *.txt                # Source word lists used by generate_data.php
    └── *.jpg / *.avif / *.png # Static background images used by the pages
```

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
