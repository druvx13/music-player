> **⚠️ Found a bug or issue?**  
> If you encounter any error, **report it in the [Issues](../../issues) section**.  
> This helps me identify and fix problems faster. Thank you!

---

# Neon Wave Music Player

A PHP-based music player web application that lets you browse, upload, and play
music tracks stored in a MySQL / MariaDB database — all from the browser.

## Features

- **Music listing** — all songs fetched from the database, newest first.
- **HTML5 audio player** — play / pause, previous / next, seek (mouse & touch),
  volume control, repeat, shuffle.
- **Upload** — add songs with title, artist, lyrics, and optional cover art
  directly from the browser.
- **Waveform visualizer** — real-time frequency bars via the Web Audio API.
- **Lyrics viewer** — modal overlay with scrollable lyrics.
- **Media Session API** — integrates with OS media controls / lock-screen.
- **Responsive UI** — works on desktop, tablet, and mobile.

## Demo

![Music Player Screenshot](gtavc-matrix-dk-eu-org-1024xFULLdesktop-dacc32.png)

## Technologies

| Layer      | Tech                                         |
|------------|----------------------------------------------|
| Frontend   | HTML5, CSS3, JavaScript (ES2020+)            |
| Styles     | Tailwind CSS (Play CDN), custom CSS          |
| Icons      | Font Awesome 6.7.2                           |
| Font       | Space Mono (Google Fonts)                    |
| Backend    | PHP 8.1+                                     |
| Database   | MySQL 8.0+ / MariaDB 10.5+ (InnoDB, utf8mb4) |
| Web server | Apache 2.4+ (with `.htaccess`)               |

## Getting Started

### Prerequisites

- PHP **8.1** or newer
- MySQL **8.0+** or MariaDB **10.5+**
- Apache **2.4+** with `mod_rewrite`, `mod_headers`, `mod_deflate`, `mod_expires` enabled
- (Optional) XAMPP / LAMP / WAMP for local development

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/druvx13/music-player.git
   cd music-player
   ```

2. **Import the database schema:**
   ```bash
   mysql -u <user> -p <database_name> < database.sql
   ```
   Or via phpMyAdmin: create a new database and import `database.sql`.

3. **Configure the database connection:**

   Open `config/database.php` and update the constants:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **Verify directory permissions:**

   The web-server process must be able to write to the `uploads/` folder:
   ```bash
   chmod 755 uploads/
   ```

5. **Enable Apache modules** (if not already active):
   ```bash
   sudo a2enmod rewrite headers deflate expires
   sudo systemctl restart apache2
   ```

6. **Open in your browser:**
   ```
   http://localhost/music-player/
   ```

## Project Structure

```
music-player/
├── .htaccess               # Root Apache config (security headers, caching, compression)
├── index.php               # Main HTML entry point
├── database.sql            # MySQL / MariaDB schema
├── README.md
├── LICENSE
│
├── config/
│   ├── .htaccess           # Deny all web access to this directory
│   └── database.php        # Database credentials & connection helper
│
├── api/
│   ├── playlist.php        # GET  → returns JSON array of songs
│   └── upload.php          # POST → handles song + cover upload, inserts DB row
│
├── assets/
│   ├── css/
│   │   └── style.css       # Application styles (imports Google Fonts)
│   └── js/
│       └── player.js       # Audio player logic, playlist UI, upload form
│
└── uploads/
    ├── .htaccess           # Disables PHP execution — prevents uploaded-file attacks
    └── (audio & cover files stored here)
```

## Security Notes

- **PHP execution disabled** in `uploads/` via `.htaccess` — a maliciously
  renamed script cannot run even if it is uploaded.
- **`config/` is blocked** from web access via its own `.htaccess`.
- Upload API validates **MIME type** (not just file extension) using `finfo`.
- Uploaded filenames are replaced with **random hex strings** to prevent
  path-traversal and enumeration.
- All database queries use **prepared statements** (MySQLi).
- Security headers (`X-Frame-Options`, `X-Content-Type-Options`,
  `Content-Security-Policy`, etc.) are sent via the root `.htaccess`.

## License

This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](./LICENSE) file for details.

---
