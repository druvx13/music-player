# database.py — SQLite helpers for the Gradio music player
import sqlite3
from pathlib import Path

DB_PATH = Path(__file__).parent / "music_player.db"


def init_db() -> None:
    """Create the songs table if it doesn't exist."""
    with sqlite3.connect(DB_PATH) as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS songs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                title       TEXT    NOT NULL,
                artist      TEXT    NOT NULL DEFAULT 'Unknown Artist',
                file        TEXT    NOT NULL,
                cover       TEXT,
                lyrics      TEXT,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """)
        conn.commit()


def fetch_songs() -> list[dict]:
    """Return all songs ordered by upload date (newest first)."""
    with sqlite3.connect(DB_PATH) as conn:
        conn.row_factory = sqlite3.Row
        rows = conn.execute(
            "SELECT id, title, artist, file, cover, lyrics "
            "FROM songs ORDER BY uploaded_at DESC"
        ).fetchall()
    return [dict(r) for r in rows]


def insert_song(
    title: str,
    artist: str,
    file_path: str,
    cover_path: str | None,
    lyrics: str,
) -> dict:
    """Insert a new song row and return the full inserted record."""
    with sqlite3.connect(DB_PATH) as conn:
        cur = conn.execute(
            "INSERT INTO songs (title, artist, file, cover, lyrics) "
            "VALUES (?, ?, ?, ?, ?)",
            (title, artist, file_path, cover_path, lyrics),
        )
        new_id = cur.lastrowid
        conn.commit()
        row = conn.execute(
            "SELECT id, title, artist, file, cover, lyrics "
            "FROM songs WHERE id = ?",
            (new_id,),
        ).fetchone()
    return dict(row) if row else {}
