myArt+ 

A community site for artists inspired by old web culture !  — upload and browse artwork, hang out in the forum, run or join groups, host events, and trade commissions with other members. Built as a personal hobby project.

Features

- **Artwork gallery** — upload art into 19 categories across 4 groups (Digital, Traditional, Characters, Photo & Other), with search, category filters, and sort by newest / oldest / most liked
- **Forum** — multiple boards, threads with replies, pinning and locking (admin), search
- **Groups** — create or join tag-based groups (Art, Anime, Retro, Gaming, Music), group chat, join requests, group banners
- **Events** — post events with a flyer and location, RSVP, per-event discussion/comments
- **Exchange** — a trade board for offering/seeking art, craft, tattoo work, writing, or music
- **Social** — friend requests, direct messages with file attachments, profile comments, blocking/reporting, favorites
- **Profiles** — custom bio, profile picture, social links, and commission status/pricing
- **Admin panel** — user, category, and forum board management
- **Bilingual** — full English/Turkish translation, switchable at any time
- **Light & dark themes**, switchable at any time

Tech Stack

- PHP (procedural, `mysqli`)
- MySQL
- jQuery + vanilla JS (AJAX-driven forum, chat, and groups panels)
- Bootstrap 4 (image modal) + a fully custom Y2K-style CSS theme (`style.css`) built on CSS custom properties for the light/dark themes
- No frameworks, no build step — plain PHP files served directly

Getting Started

1. Install [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP + MySQL stack)
2. Clone this repo into your `htdocs` folder
3. Create a MySQL database named `myart` and set up the tables the app expects (users, artworks, forum_boards, forum_threads, forum_posts, groups, events, messages, friendships, notifications, exchanges, etc.) — a couple of tables (`event_rsvps`, `event_comments`) are created automatically on first use, but most are not yet captured in a schema file in this repo
4. Check `config.php` for the DB connection settings (defaults to XAMPP's standard `root` / no password / `localhost`)
5. Visit `setup_admin.php` once to create an admin account (`admin@hotmail.com` / `1502` by default — change this if you plan to expose the site publicly)
6. Browse to `index.php` in your browser

Project Structure

Each page is a self-contained PHP file (no router/framework) that handles its own session, language, theme, and page logic, then renders its own HTML. Shared pieces live in a handful of includes:

- `config.php` — database connection
- `navbar.php` / `chat_widget.php` — shared UI included across pages
- `lang/en.php`, `lang/tr.php` — all UI strings, keyed and swapped based on the selected language
- `categories_config.php` — the single source of truth for artwork categories, shared by Browse, Categories, and profile pages
- `*_api.php` — AJAX endpoints (forum, chat, groups, artwork likes, discovery)

License

Personal project — no license file yet, all rights reserved by default.


























