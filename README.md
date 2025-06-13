> **⚠️ Found a bug or issue?**  
> If you encounter or find any error, **do not hesitate to report it in the [Issues](../../issues) section**.  
> This helps me identify and fix problems more effectively. Thank you!

---

# Music Player Web App

A simple, dynamic PHP-based music player web application that allows users to browse, play, and manage a list of music tracks. It features a modern interface and is built with security and accessibility in mind.

## Features

- **Dynamic Music Listing:** Automatically lists all songs from the database, displayed in a user-friendly playlist.
- **Interactive Audio Player:** HTML5-based music player with play, pause, next, previous, shuffle, and repeat functionalities. Includes a visual progress bar and volume control.
- **Song Uploads:** Users can upload new songs (MP3 format) with optional cover art, title, artist, and lyrics through an intuitive modal form.
- **Secure File Uploads:** Implements MIME type validation on the backend to ensure only valid audio and image files are uploaded.
- **Database Integration:** Uses PDO for secure and efficient interaction with a MySQL database. The database schema is optimized with `InnoDB` and `utf8mb4` for performance and full Unicode support.
- **Responsive UI:** Clean, modern interface built with Tailwind CSS, designed for a seamless listening experience across devices.
- **Accessibility Enhancements:** Improved semantic HTML structure and ARIA attributes for better usability with assistive technologies.
- **Visualizations:** Includes subtle background visualizer effects and a waveform display for the currently playing song.

## Demo

![Music Player Screenshot](gtavc-matrix-dk-eu-org-1024xFULLdesktop-dacc32.png)  


## Technologies Used

- **Frontend:** HTML5, CSS3 (Tailwind CSS, custom styles), JavaScript (ES6+)
- **Backend:** PHP 8.0+ (utilizing PDO for database interaction)
- **Database:** MySQL (schema uses InnoDB engine and `utf8mb4` charset for full Unicode support)
- **Audio:** HTML5 `<audio>` element with extensive JavaScript controls.
- **Accessibility:** ARIA attributes and semantic HTML for improved screen reader support.

## Getting Started

Follow these steps to set up the project on your local machine:

### Prerequisites

- PHP 8.0 or above (with PDO_MySQL extension enabled)
- MySQL
- Web server (e.g., XAMPP, Apache, Nginx)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/druvx13/music-player.git
   cd music-player
   ```

2. **Import the Database:**
   - Open your MySQL management tool (e.g., phpMyAdmin).
   - Create a new database (e.g., `music_db_neon`).
   - Import the `database.sql` file into this database.
   - **Note:** The database schema in `database.sql` uses the `InnoDB` engine for tables and `utf8mb4` character set for comprehensive Unicode support, ensuring data integrity and performance.

3. **Update Database Configuration:**
   - Open `index.php` in the root directory.
   - Modify the MySQL credentials if necessary:
     ```php
     // Database configuration
     $host = "localhost";
     $db = "db_name";
     $user = "user_name";
     $pass = "user_pass";
     ```

4. **Add Your Music Files:**
   - Upload your MP3 audio files and optional cover images (JPEG, PNG, GIF) using the "Upload" button within the application.
   - Song metadata (title, artist, lyrics) can also be added during the upload process.
   - Uploaded files are stored in the `uploads/` directory, and their metadata is saved to the MySQL database.

5. **Run the App:**
   - Ensure your web server is running and configured to serve PHP files from the project directory.
   - Open your browser and navigate to the `index.php` file (e.g., `http://localhost/music-player/index.php` or your configured virtual host).

## Project Structure

```
music-player/
├── uploads/              # Directory for storing uploaded song files and cover art
├── index.php             # Main application file (PHP backend, HTML, CSS, JavaScript)
├── database.sql          # MySQL database schema file
├── README.md             # This file
├── LICENSE               # Project license information (GPL-3.0)
```

## **License**

This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](./LICENSE) file for more details.


---
