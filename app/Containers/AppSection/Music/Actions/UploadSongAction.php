<?php

namespace App\Containers\AppSection\Music\Actions;

// Apiato's base action class
use Apiato\Core\Actions\Action as CoreAction;
use App\Containers\AppSection\Music\Models\Song;
use App\Containers\AppSection\Music\UI\API\Requests\UploadSongRequest; // For type hinting
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str; // For generating unique filenames

class UploadSongAction extends CoreAction
{
    public function run(UploadSongRequest $request): Song
    {
        $data = $request->validated();

        $songFilePath = null;
        $coverImagePath = null;

        // Handle song file upload
        if ($request->hasFile('song_file')) {
            $songFile = $request->file('song_file');
            // Generate a unique name for the file
            $songFileName = Str::uuid() . '.' . $songFile->getClientOriginalExtension();
            // Store it in 'storage/app/public/songs'
            $songFilePath = $songFile->storeAs('songs', $songFileName, 'public');
        } else {
            // This should ideally be caught by validation, but as a safeguard:
            throw new \Exception("Song file is missing.");
        }

        // Handle cover image upload (optional)
        if ($request->hasFile('cover_image')) {
            $coverFile = $request->file('cover_image');
            $coverFileName = Str::uuid() . '.' . $coverFile->getClientOriginalExtension();
            // Store it in 'storage/app/public/covers'
            $coverImagePath = $coverFile->storeAs('covers', $coverFileName, 'public');
        }

        // Create the song record in the database
        // In a full Apiato app, this might be done via a Task, e.g., app(CreateSongTask::class)->run(...)
        $song = Song::create([
            'title' => $data['title'],
            'artist' => $data['artist'] ?? null, // Handle optional artist
            'lyrics' => $data['lyrics'] ?? null, // Handle optional lyrics
            'file_path' => $songFilePath,
            'cover_path' => $coverImagePath,
        ]);

        return $song;
    }
}
