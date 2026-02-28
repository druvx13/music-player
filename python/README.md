---
title: Neon Wave Music Player
emoji: 🎵
colorFrom: blue
colorTo: purple
sdk: gradio
sdk_version: "4.44.0"
app_file: app.py
pinned: false
license: gpl-3.0
short_description: Neon-themed music player with playlist management & upload
---

> **⚠️ Found a bug or issue?**  
> Report it in the [Issues](../../issues) section — thank you!

---

# 🎵 Neon Wave Music Player — Python / Gradio Edition

A full-featured music player built with **Python + Gradio**, designed to run on
[Hugging Face Spaces](https://huggingface.co/spaces) while mirroring the look
and functionality of the original PHP version.

## Features

- **Playlist** — songs are listed in a table, click any row to play it instantly.
- **HTML5 audio player** — powered by the browser's native `<audio>` element;
  Gradio handles streaming.
- **Upload** — add songs with title, artist, optional lyrics, and cover art
  from the browser (MP3 + JPG/PNG/GIF/WEBP accepted).
- **Lyrics viewer** — collapsible accordion panel with the full lyrics of the
  currently selected song.
- **Album art** — cover image displayed alongside the player controls.
- **Neon Wave theme** — dark glass-morphism UI matching the PHP version.

## Tech stack

| Layer    | Tech                                      |
|----------|-------------------------------------------|
| UI       | [Gradio](https://gradio.app/) 4.x         |
| Font     | Space Mono (Google Fonts)                 |
| Backend  | Python 3.10+                              |
| Database | SQLite (via stdlib `sqlite3`)             |
| Storage  | Local `uploads/` directory                |

## Running locally

```bash
# 1. Install dependencies
pip install -r requirements.txt

# 2. Start the app
python app.py
```

Open `http://127.0.0.1:7860` in your browser.

## Deploying on Hugging Face Spaces

1. Create a new Space at <https://huggingface.co/new-space>.
2. Choose **Gradio** as the SDK.
3. Upload (or push via `git`) the contents of this `python/` folder as the
   **root** of the Space repository.
4. Hugging Face will install `requirements.txt` and launch `app.py` automatically.

> **Note on persistence:** Hugging Face Spaces do not have persistent storage by
> default. Uploaded songs will be lost when the Space restarts. To retain
> uploads across restarts, enable **Persistent Storage** in your Space settings
> — the `uploads/` directory and `music_player.db` will then survive restarts.

## Project structure

```
python/                  ← root of the HF Space repo
├── app.py               # Gradio application (entry point)
├── database.py          # SQLite helpers (init, fetch, insert)
├── requirements.txt     # Python dependencies
├── README.md            # This file (also HF Space config)
└── uploads/             # Uploaded audio & cover files (runtime)
    └── .gitkeep
```

## License

GNU General Public License v3.0 — see [LICENSE](../LICENSE).
