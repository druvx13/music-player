<?php

namespace App\Containers\AppSection\Music\UI\API\Controllers;

// Apiato's base controller for API
// The exact path might vary slightly based on Apiato version / setup
// For older versions it might be: App\Ship\Parents\Controllers\ApiController
// For newer, it might be directly from Apiato\Core or similar.
// Assuming a newer structure or a generic base that would be available.
use Apiato\Core\Http\Controllers\ApiController;
use App\Containers\AppSection\Music\Actions\GetAllSongsAction;
use App\Containers\AppSection\Music\Actions\UploadSongAction;
use App\Containers\AppSection\Music\UI\API\Requests\GetAllSongsRequest;
use App\Containers\AppSection\Music\UI\API\Requests\UploadSongRequest;
use App\Containers\AppSection\Music\UI\API\Transformers\SongTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log; // For logging

class Controller extends ApiController
{
    /**
     * Get all songs.
     */
    public function getAllSongs(GetAllSongsRequest $request): JsonResponse
    {
        // In a real Apiato app, this would typically call an Action
        // $songs = app(GetAllSongsAction::class)->run($request);
        // For simulation, let's assume the action directly uses the model for now or is simplified

        // Simplified placeholder for where an action would be called
        // $songs = \App\Containers\AppSection\Music\Models\Song::orderBy('created_at', 'desc')->get();

        // More correct Apiato way: Use an Action
        $songs = app(GetAllSongsAction::class)->run();


        // Transform the response
        // The transformer should handle pagination if implemented
        return $this->transformedCollection($songs, SongTransformer::class);
    }

    /**
     * Upload a new song.
     */
    public function uploadSong(UploadSongRequest $request): JsonResponse
    {
        try {
            // The UploadSongRequest will handle validation.
            // Data from the request will be passed to an Action.
            $song = app(UploadSongAction::class)->run($request);

            // Transform the response for the newly created song
            return $this->transformedItem($song, SongTransformer::class, [], 201); // 201 Created
        } catch (\Exception $e) {
            // Log the exception for debugging
            Log::error('Song Upload Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Return a generic error response
            return $this->json([
                'message' => 'Failed to upload song. Please try again later.',
                'error_details' => $e->getMessage() // Only include if APP_DEBUG is true or for dev
            ], 500);
        }
    }
}
