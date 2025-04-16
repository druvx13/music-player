> **⚠️ Found a bug or issue?**  
> If you encounter or find any error, **do not hesitate to report it in the [Issues](../../issues) section**.  
> This helps me identify and fix problems more effectively. Thank you!

---

# Music Player Web App

A simple, dynamic PHP-based music player web application that allows users to browse, play, and manage a list of music tracks stored in a MySQL database.

## Features

- **Music Listing:** Automatically lists all songs from the database.
- **Audio Player:** HTML5-based music player with play, pause, and progress functionality.
- **Database Integration:** Fetches song data (title, file path) directly from a MySQL database.
- **Responsive UI:** Minimal, functional interface for seamless listening.

## Demo

![Music Player Screenshot](gtavc-matrix-dk-eu-org-1024xFULLdesktop-dacc32.png)  


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
   - Upload your audio favourite audios through the upload button shown in index.php which will popup an form field to be filled in and you can customise that however you want and the uploaded songs & their covers will be stored in `/uploads` directory & such other things such as title, artist, lyrics etc will be stored in MySQL database.
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

## **License**

This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](./LICENSE) file for more details.


---
