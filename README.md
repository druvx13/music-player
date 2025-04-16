---

# Music Player Web App

A simple, dynamic PHP-based music player web application that allows users to browse, play, and manage a list of music tracks stored in a MySQL database.

## Features

- **Music Listing:** Automatically lists all songs from the database.
- **Audio Player:** HTML5-based music player with play, pause, and progress functionality.
- **Database Integration:** Fetches song data (title, file path) directly from a MySQL database.
- **Responsive UI:** Minimal, functional interface for seamless listening.

## Demo

![Music Player Screenshot](demo-screenshot.png)  
*(Optional: Add a screenshot or a GIF preview of the player UI)*

## Technologies Used

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Audio:** HTML5 `<audio>` tag

## Getting Started

Follow these steps to set up the project on your local machine:

### Prerequisites

- PHP 7.x or above
- MySQL
- Web server (e.g., XAMPP, LAMP, or WAMP)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/druvx13/music-player.git
   cd music-player
   ```

2. **Import the Database:**
   - Open phpMyAdmin.
   - Create a new database, e.g., `musicdb`.
   - Import the provided `database.sql` file into this database.

3. **Update Database Configuration:**
   - Open `index.php`.
   - Modify the MySQL credentials if necessary:
     ```php
     // Database configuration
     $host = "localhost";
     $db = "db_name";
     $user = "user_name";
     $pass = "user_pass";
     ```

4. **Add Your Music Files:**
   - Upload your `.mp3` or audio files to the `/uploads` directory.
   - Make sure the file names match the entries in your database.

5. **Run the App:**
   - Open your browser and navigate to:
     ```
     http://localhost/music-player/index.php
     ```

## Project Structure

```
music-player/
├── uploads/              # Folder for audio files
├── index.php          # Main PHP application file
├── database.sql        # MySQL dump file
├── README.md           
├── LICENCE         
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.


---
