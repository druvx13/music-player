"""
app.py — Neon Wave Music Player (Gradio / Hugging Face Spaces edition)

A full-featured music player built with Gradio that mirrors the PHP version.
Run locally:
    python app.py
Deploy on HF Spaces:
    Push this folder to a Gradio Space — no additional config required.

Compatible with Gradio 4.44+ and Gradio 6.x.
"""

import os
import secrets
import shutil
from pathlib import Path

import gradio as gr

from database import fetch_songs, init_db, insert_song

# ── Paths ──────────────────────────────────────────────────────────────────────
BASE_DIR   = Path(__file__).parent
UPLOAD_DIR = BASE_DIR / "uploads"
UPLOAD_DIR.mkdir(exist_ok=True)

# Initialise database on startup
init_db()

# ── Gradio theme (neon-wave dark) ──────────────────────────────────────────────
# In Gradio 6.x, theme and css are passed to demo.launch() instead of gr.Blocks().
_theme = gr.themes.Base(
    primary_hue=gr.themes.colors.sky,
    secondary_hue=gr.themes.colors.purple,
    neutral_hue=gr.themes.colors.zinc,
    font=gr.themes.GoogleFont("Space Mono"),
    font_mono=gr.themes.GoogleFont("Space Mono"),
).set(
    body_background_fill="linear-gradient(135deg, hsl(220,13%,18%), hsl(220,13%,25%))",
    body_text_color="#ffffff",
    block_background_fill="rgba(255,255,255,0.08)",
    block_border_color="rgba(255,255,255,0.10)",
    block_border_width="1px",
    block_radius="1rem",
    block_shadow="0 8px 32px rgba(0,0,0,0.25), 0 0 20px rgba(64,160,212,0.12)",
    block_title_text_color="rgba(255,255,255,0.90)",
    block_label_text_color="rgba(255,255,255,0.70)",
    input_background_fill="rgba(255,255,255,0.10)",
    input_border_color="rgba(255,255,255,0.20)",
    input_placeholder_color="rgba(255,255,255,0.30)",
    input_shadow="none",
    button_primary_background_fill="linear-gradient(90deg, hsl(201,63%,54%), hsl(261,77%,57%))",
    button_primary_background_fill_hover="linear-gradient(90deg, hsl(201,63%,60%), hsl(261,77%,63%))",
    button_primary_text_color="#ffffff",
    button_primary_shadow="0 4px 15px rgba(64,160,212,0.35)",
    button_secondary_background_fill="rgba(255,255,255,0.10)",
    button_secondary_background_fill_hover="rgba(255,255,255,0.18)",
    button_secondary_border_color="rgba(255,255,255,0.20)",
    button_secondary_text_color="#ffffff",
    checkbox_background_color="rgba(255,255,255,0.10)",
    table_even_background_fill="rgba(255,255,255,0.04)",
    table_odd_background_fill="rgba(255,255,255,0.02)",
    table_row_focus="rgba(64,160,212,0.15)",
)

# ── Extra CSS (neon accents, cover art, etc.) ──────────────────────────────────
_CSS = """
/* Remove default white Gradio container background */
.gradio-container { background: transparent !important; }
footer { display: none !important; }

/* Header */
#app-header { text-align: center; padding: 1.5rem 0 0.5rem; }
#app-header h1 {
    font-size: 2rem !important;
    font-weight: 700 !important;
    background: linear-gradient(135deg, hsl(201,63%,65%), hsl(261,77%,70%));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: none !important;
}
#app-header p { color: rgba(255,255,255,0.55) !important; font-size: 0.85rem; }

/* Now-playing title */
#now-playing-title textarea,
#now-playing-title input {
    font-size: 1.3rem !important;
    font-weight: 700 !important;
    color: #ffffff !important;
    text-shadow: 0 0 10px rgba(64,160,212,0.6);
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
}
#now-playing-artist textarea,
#now-playing-artist input {
    color: rgba(255,255,255,0.65) !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
}

/* Cover image */
#cover-art img {
    border-radius: 0.75rem !important;
    box-shadow: 0 0 25px rgba(64,160,212,0.40) !important;
    object-fit: cover !important;
}
#cover-art .image-container { background: rgba(44,62,80,0.9) !important; border-radius: 0.75rem; }

/* Playlist table */
#playlist-table table  { font-size: 0.85rem !important; }
#playlist-table thead  { background: rgba(64,160,212,0.18) !important; }
#playlist-table th     { color: rgba(255,255,255,0.80) !important; }
#playlist-table td     { color: rgba(255,255,255,0.90) !important; cursor: pointer; }
#playlist-table tbody tr:hover { background: rgba(255,255,255,0.09) !important; }

/* Lyrics */
#lyrics-display textarea {
    line-height: 1.8 !important;
    text-align: center !important;
    color: rgba(255,255,255,0.80) !important;
    background: rgba(255,255,255,0.04) !important;
    font-size: 0.95rem !important;
}

/* Upload panel */
#upload-panel {
    border: 1px dashed rgba(255,255,255,0.20) !important;
    border-radius: 1rem !important;
    padding: 0.5rem !important;
}

/* Status messages */
#upload-status textarea { font-size: 0.85rem !important; }

/* Footer */
.footer-credit {
    text-align: center;
    color: rgba(255,255,255,0.35);
    font-size: 0.78rem;
    padding: 1rem 0;
}
.footer-credit span { color: #f87171; }
"""

# ── Handlers ───────────────────────────────────────────────────────────────────

def load_playlist() -> tuple[list[dict], list[list[str]]]:
    """Fetch songs from DB; return (state, dataframe rows)."""
    songs = fetch_songs()
    table = [[s["title"], s["artist"]] for s in songs]
    return songs, table


def on_song_select(
    songs: list[dict], evt: gr.SelectData
) -> tuple:
    """Called when the user clicks a row in the playlist table."""
    row = evt.index[0]
    if not songs or row >= len(songs):
        return None, "Select a song", "—", "No lyrics available.", None

    song   = songs[row]
    audio  = song["file"]  if os.path.isfile(song["file"])                        else None
    cover  = song["cover"] if song.get("cover") and os.path.isfile(song["cover"]) else None
    lyrics = song.get("lyrics") or "No lyrics available for this song."

    return audio, song["title"], song["artist"], lyrics, cover


def upload_song(
    song_file,
    cover_file,
    title:  str,
    artist: str,
    lyrics: str,
) -> tuple:
    """Handle song upload: validate, save files, insert into DB, refresh list."""
    if song_file is None:
        return "❌ Please select an MP3 file.", gr.update(), gr.update()

    src_path = Path(song_file.name)
    if src_path.suffix.lower() != ".mp3":
        return "❌ Only MP3 files are allowed.", gr.update(), gr.update()

    # ── Save audio ──────────────────────────────────────────────────────────
    dest_name = secrets.token_hex(16) + ".mp3"
    dest_path = UPLOAD_DIR / dest_name
    shutil.copy2(src_path, dest_path)

    # ── Save optional cover ─────────────────────────────────────────────────
    cover_path: str | None = None
    if cover_file is not None:
        cover_src = Path(cover_file.name)
        if cover_src.suffix.lower() in {".jpg", ".jpeg", ".png", ".gif", ".webp"}:
            cover_name = secrets.token_hex(16) + cover_src.suffix.lower()
            cover_dest = UPLOAD_DIR / cover_name
            shutil.copy2(cover_src, cover_dest)
            cover_path = str(cover_dest)

    clean_title  = title.strip()  or src_path.stem
    clean_artist = artist.strip() or "Unknown Artist"
    clean_lyrics = lyrics.strip()

    insert_song(clean_title, clean_artist, str(dest_path), cover_path, clean_lyrics)

    songs = fetch_songs()
    table = [[s["title"], s["artist"]] for s in songs]
    return f"✅ '{clean_title}' added to the playlist!", songs, table


# ── UI ─────────────────────────────────────────────────────────────────────────
with gr.Blocks(title="Neon Wave Music Player") as demo:

    # ── Header ──────────────────────────────────────────────────────────────
    gr.HTML("""
        <div id="app-header">
            <h1>🎵 Neon Wave Music Player</h1>
            <p>Browse &middot; Upload &middot; Play</p>
        </div>
    """)

    # Shared state: full list of song dicts (not exposed in the UI)
    songs_state = gr.State([])

    # ── Main row: Player (left) + Playlist (right) ───────────────────────
    with gr.Row(equal_height=False):

        # Left column — album art + player controls
        with gr.Column(scale=2, min_width=280):
            cover_art = gr.Image(
                value=None,
                label="Album Art",
                height=240,
                elem_id="cover-art",
                interactive=False,
                show_label=False,
            )
            song_title_disp = gr.Textbox(
                value="Select a song",
                label="Now Playing",
                interactive=False,
                elem_id="now-playing-title",
            )
            artist_disp = gr.Textbox(
                value="—",
                label="Artist",
                interactive=False,
                elem_id="now-playing-artist",
            )
            audio_player = gr.Audio(
                value=None,
                label="Player",
                type="filepath",
                interactive=False,
                autoplay=True,
                elem_id="audio-player",
            )

        # Right column — playlist + action buttons
        with gr.Column(scale=3):
            gr.Markdown("### 🎶 Playlist")
            playlist_df = gr.Dataframe(
                headers=["Title", "Artist"],
                column_count=2,
                interactive=False,
                label="",
                wrap=True,
                elem_id="playlist-table",
            )
            with gr.Row():
                refresh_btn     = gr.Button("🔄 Refresh",     variant="secondary", scale=1)
                upload_open_btn = gr.Button("⬆️ Upload Song", variant="primary",   scale=2)

    # ── Lyrics accordion ──────────────────────────────────────────────────
    with gr.Accordion("📜 Lyrics", open=False):
        lyrics_disp = gr.Textbox(
            value="",
            label="",
            lines=7,
            interactive=False,
            elem_id="lyrics-display",
            placeholder="Select a song to view lyrics…",
        )

    # ── Upload panel (hidden until button clicked) ────────────────────────
    with gr.Group(visible=False, elem_id="upload-panel") as upload_panel:
        gr.Markdown("### ⬆️ Upload New Song")
        with gr.Row():
            with gr.Column():
                up_title  = gr.Textbox(label="Song Title *",      placeholder="Enter song title")
                up_artist = gr.Textbox(label="Artist *",          placeholder="Enter artist name")
                up_lyrics = gr.Textbox(label="Lyrics (optional)", lines=4,
                                       placeholder="Enter song lyrics…")
            with gr.Column():
                up_song  = gr.File(
                    label="Song File (MP3) *",
                    file_types=[".mp3"],
                    file_count="single",
                )
                up_cover = gr.File(
                    label="Cover Art (optional)",
                    file_types=[".jpg", ".jpeg", ".png", ".gif", ".webp"],
                    file_count="single",
                )
        with gr.Row():
            up_submit = gr.Button("Upload", variant="primary",   scale=3)
            up_cancel = gr.Button("Cancel", variant="secondary", scale=1)
        up_status = gr.Textbox(
            label="", interactive=False, show_label=False,
            elem_id="upload-status", placeholder="",
        )

    # ── Footer ────────────────────────────────────────────────────────────
    gr.HTML('<p class="footer-credit">Made with <span>❤️</span> by DK &nbsp;&middot;&nbsp; Neon Wave Music Player</p>')

    # ── Event wiring ──────────────────────────────────────────────────────
    demo.load(load_playlist, outputs=[songs_state, playlist_df])

    playlist_df.select(
        on_song_select,
        inputs=[songs_state],
        outputs=[audio_player, song_title_disp, artist_disp, lyrics_disp, cover_art],
    )

    refresh_btn.click(load_playlist, outputs=[songs_state, playlist_df])

    upload_open_btn.click(lambda: gr.update(visible=True),  outputs=[upload_panel])
    up_cancel.click(      lambda: gr.update(visible=False), outputs=[upload_panel])

    up_submit.click(
        upload_song,
        inputs=[up_song, up_cover, up_title, up_artist, up_lyrics],
        outputs=[up_status, songs_state, playlist_df],
    )

# ── Launch ─────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    demo.launch(
        theme=_theme,
        css=_CSS,
        allowed_paths=[str(UPLOAD_DIR)],
    )
