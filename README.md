> **⚠️ Found a bug or issue?**  
> If you encounter or find any error, **do not hesitate to report it in the [Issues](../../issues) section**.  
> This helps me identify and fix problems more effectively. Thank you!

---

# Music Player Web App

A simple, dynamic PHP-based music player web application that allows users to browse, play, and manage a list of music tracks. It features a responsive UI, fetches song data from a MySQL database, and allows users to upload new songs with cover art and lyrics.

## Features

- **Dynamic Playlist:** Automatically lists all songs from the database.
- **Audio Player:** HTML5-based music player with play, pause, volume, shuffle, repeat, and progress functionality.
- **Song Upload:** Users can upload MP3 files, cover images (JPG, PNG, GIF), and add title, artist, and lyrics.
- **Database Integration:** Fetches and stores song data (title, file path, cover path, artist, lyrics) in a MySQL database.
- **Responsive UI:** Modern interface built with Tailwind CSS for a seamless listening experience across devices.
- **Visualizations:** Includes subtle background visual effects and a waveform display.

## Demo

![Music Player Screenshot](gtavc-matrix-dk-eu-org-1024xFULLdesktop-dacc32.png)  

## Technologies Used

- **Frontend:** HTML, CSS (Tailwind CSS, Font Awesome), JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Audio:** HTML5 `<audio>` tag

## Getting Started

Follow these steps to set up the project on your local machine:

### Prerequisites

- PHP 7.x or above (with `mysqli` extension)
- MySQL
- Web server (e.g., Apache, Nginx, XAMPP, LAMP, or WAMP)

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/druvx13/music-player.git
    cd music-player
    ```

2.  **Import the Database:**
    -   Open phpMyAdmin or any MySQL client.
    -   Create a new database (e.g., `music_player_db`).
    -   Import the provided `database.sql` file into this database. This will create the `songs` table.

3.  **Update Database Configuration:**
    -   Open `php/database.php`.
    -   Modify the MySQL credentials to match your environment:
        ```php
        // php/database.php
        // Database configuration
        $host = "your_host"; // e.g., "localhost"
        $db = "your_db_name"; // e.g., "music_player_db"
        $user = "your_username";
        $pass = "your_password";
        ```

4.  **Set Permissions:**
    -   Ensure your web server has write permissions for the `uploads/` directory. This is where uploaded song files and cover art will be stored.
        ```bash
        chmod -R 775 uploads/
        # You might also need to set the correct owner, e.g., www-data for Apache
        # chown -R www-data:www-data uploads/
        ```

5.  **Run the App:**
    -   Place the project directory in your web server's document root (e.g., `htdocs/` for XAMPP, `www/` for WAMP, `/var/www/html/` for Apache on Linux).
    -   Open your browser and navigate to the project, for example:
        ```
        http://localhost/music-player/
        ```
        (The exact URL will depend on your web server configuration and where you placed the project).

6.  **Upload Music:**
    -   Use the "Upload" button within the web interface to add your MP3 files. You can also add a title, artist, cover image, and lyrics for each song.
    -   Uploaded songs and their cover images will be stored in the `uploads/` directory, and their metadata will be saved to the database.

## Project Structure

```
music-player/
├── css/                # CSS files
│   └── style.css
├── js/                 # JavaScript files
│   └── script.js
├── php/                # PHP files
│   ├── api.php         # Handles API requests (get playlist, upload song)
│   └── database.php    # Database connection logic
├── uploads/            # Folder for uploaded audio and cover files (needs write permissions)
├── index.php           # Main application file (HTML structure and UI)
├── database.sql        # MySQL dump file for database schema
├── README.md           # This file
├── LICENSE             # Project License
└── gtavc-matrix-dk-eu-org-1024xFULLdesktop-dacc32.png # Demo screenshot
```

## Troubleshooting

-   **Songs not loading/playing:**
    -   Ensure your web server has read permissions for the `uploads/` directory and the files within it.
    -   Verify that the paths in the `songs` table in your database correctly point to the files in the `uploads/` directory (e.g., `uploads/song_name.mp3`).
    -   Check the browser's developer console (usually F12, then look at "Console" and "Network" tabs) for any errors.
-   **Uploads failing:**
    -   Ensure your web server has write permissions for the `uploads/` directory.
    -   Check your PHP configuration for `upload_max_filesize` and `post_max_size` in your `php.ini` file if you are trying to upload large files.
    -   Look for error messages in the browser console or PHP error logs on your server.
-   **Database connection issues:**
    -   Double-check the database credentials in `php/database.php`.
    -   Ensure your MySQL server is running and accessible from your web server.
-   **Page looks unstyled or features don't work:**
    -   Verify that `css/style.css` and `js/script.js` are correctly linked in `index.php` and are loading in the browser (check the Network tab in developer tools).
    -   Ensure external CDN links for Tailwind CSS and Font Awesome are accessible.

## Contributing

Contributions are welcome! If you have ideas for improvements or find any bugs, please follow these steps:

1.  Fork the repository.
2.  Create a new branch (`git checkout -b feature/your-feature-name`).
3.  Make your changes and commit them (`git commit -m 'Add some feature'`).
4.  Push to the branch (`git push origin feature/your-feature-name`).
5.  Open a Pull Request.

## License

This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](./LICENSE) file for more details.

---
