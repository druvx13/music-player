# Neon Wave Music Player (Apiato Edition)

A dynamic PHP-based music player web application, rebuilt with the Apiato framework for a scalable and maintainable API-centric backend. Users can browse, play, and manage a list of music tracks.

## Features

- **Music Listing:** Fetches and displays all songs from the database via a robust API.
- **Audio Player:** Modern HTML5-based music player with play, pause, next, previous, shuffle, repeat, and progress functionality. (Frontend from original project)
- **Dynamic Waveform Display:** Visual feedback during playback. (Frontend)
- **Volume Control:** Adjust playback volume. (Frontend)
- **Apiato Backend:** Leverages Apiato for API development, including request validation, data transformation, and clear business logic encapsulation.
- **Song Uploads:** Upload new MP3 tracks with optional cover art, artist details, and lyrics via a user-friendly interface, processed by the Apiato backend.
- **Lyrics Display:** View lyrics for the current song if available. (Frontend)
- **Responsive UI:** Minimal, functional, and aesthetically pleasing interface. (Frontend, styled with Tailwind CSS via CDN)

## Technology Stack

- **Backend:** Apiato (built on Laravel 10)
    - PHP 8.1+
    - RESTful API
- **Frontend:**
    - HTML5
    - CSS3 (Tailwind CSS via CDN, custom styles in `public/assets/css/style.css`)
    - JavaScript (ES6+, in `public/assets/js/main.js`)
- **Database:** MySQL (or other Laravel-supported DB like PostgreSQL, SQLite)
- **Audio:** HTML5 `<audio>` tag
- **Icons:** Font Awesome (via CDN)
- **PHP Package Management:** Composer

## Prerequisites

- PHP 8.1 or higher (check Apiato/Laravel 10 requirements for specific extensions: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML, JSON, BCMath).
- Composer installed globally or locally.
- A supported database server (e.g., MySQL, PostgreSQL).
- Web server (e.g., Apache, Nginx) with URL rewriting enabled.
- Node.js and npm/yarn (optional, if frontend assets were to be compiled, but current setup uses CDNs and direct CSS/JS).

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/your-repo-name.git neon-wave-music-player
    cd neon-wave-music-player
    ```

2.  **Install PHP Dependencies:**
    ```bash
    composer install --prefer-dist --no-dev # For production, or without --no-dev for development
    ```

3.  **Configure Environment:**
    *   Copy the example environment file:
        ```bash
        cp .env.example .env
        ```
    *   Generate the application key:
        ```bash
        php artisan key:generate
        ```
    *   Open the `.env` file and configure your application details, especially:
        *   `APP_NAME`, `APP_URL`
        *   `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
        *   `API_PREFIX` (default `api`), `API_VERSION` (default `v1`) - ensure these match frontend expectations.

4.  **Run Database Migrations:**
    This will create the necessary tables in your database (e.g., `songs` table).
    ```bash
    php artisan migrate
    ```
    *Optional: Seed the database if seeders are created:*
    ```bash
    # php artisan db:seed
    ```

5.  **Create Storage Symlink:**
    This makes files stored in `storage/app/public` accessible from the web.
    ```bash
    php artisan storage:link
    ```

6.  **Set Permissions:**
    Ensure the `storage/` and `bootstrap/cache/` directories are writable by the web server.
    ```bash
    sudo chmod -R 775 storage bootstrap/cache
    # Adjust ownership if necessary, e.g., sudo chown -R www-data:www-data storage bootstrap/cache
    ```

7.  **Configure Your Web Server:**
    *   Set the web server's **document root** (or "web root") to the `public/` directory inside your project.
    *   Ensure URL rewriting is enabled (e.g., `mod_rewrite` for Apache). The `public/.htaccess` file handles routing for Apache.
    *   For Nginx, a configuration similar to Laravel's standard Nginx config should be used.

8.  **Access the Application:**
    *   Open your browser and navigate to the `APP_URL` you configured in your `.env` file. This should load the main music player interface.
    *   The API will be accessible under `APP_URL`/`API_PREFIX`/`API_VERSION` (e.g., `http://localhost/api/v1/`).

## API Endpoints

The application exposes the following API endpoints (default prefix `/api`, version `v1`):

### Songs

*   **`GET /v1/songs`**
    *   **Description:** Retrieves a list of all available songs.
    *   **Response:** A JSON array of song objects (see `SongTransformer` for structure), possibly paginated.
*   **`POST /v1/songs`**
    *   **Description:** Uploads a new song.
    *   **Request Type:** `multipart/form-data`
    *   **Form Fields:**
        *   `title` (string, required): Song title.
        *   `artist` (string, nullable): Artist name.
        *   `lyrics` (string, nullable): Song lyrics.
        *   `song_file` (file, required): The MP3 audio file (max 20MB).
        *   `cover_image` (file, nullable): Cover image (JPG, PNG, GIF - max 5MB).
    *   **Success Response (201 Created):** JSON object of the newly created song.
    *   **Error Responses:** Standard Apiato error responses (e.g., 422 for validation errors, 500 for server errors).

*Note: Apiato can generate comprehensive API documentation using `php artisan apiato:generate:apidoc`. Check the `public/docs` directory after running this command.*

## Frontend Integration

The frontend is built with HTML, CSS (Tailwind via CDN), and JavaScript (`public/assets/js/main.js`).
-   The main HTML is served from `resources/views/index.blade.php` via a web route.
-   `public/assets/js/main.js` needs its `API_ENDPOINT_BASE` variable configured to point to the correct Apiato API base URL (e.g., `/api/v1` or your fully qualified `APP_URL`/`API_PREFIX`/`API_VERSION`).

## Key Artisan Commands (Development)

-   `php artisan serve`: Start the PHP development server.
-   `php artisan migrate`: Run database migrations.
-   `php artisan migrate:fresh --seed`: Drop all tables, re-run migrations, and run seeders.
-   `php artisan key:generate`: Generate a new application key.
-   `php artisan storage:link`: Create the public storage symlink.
-   `php artisan list apiato`: List all Apiato specific commands for code generation, etc.
-   `php artisan route:list`: List all registered routes.
-   `php artisan tinker`: Interact with your application.

## Troubleshooting

-   **File Upload Issues:** Check permissions on `storage/app/public`, PHP's `upload_max_filesize` and `post_max_size` in `php.ini`. Ensure `php artisan storage:link` was run.
-   **404 Errors on API or Web Routes:** Verify web server configuration, `.htaccess` (for Apache), and run `php artisan route:list` to check registered routes.
-   **Database Connection Errors:** Double-check `.env` database credentials. Ensure your database server is running and accessible.
-   **"Class not found" or similar errors after `composer install`:** Try running `composer dump-autoload`.

---
*This project structure is a simulation of an Apiato application. Not all Apiato features or complexities are fully implemented in this simulated environment.*
