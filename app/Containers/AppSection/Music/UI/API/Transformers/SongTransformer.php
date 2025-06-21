<?php

namespace App\Containers\AppSection\Music\UI\API\Transformers;

use App\Containers\AppSection\Music\Models\Song;
// Apiato's base transformer class
// Path might vary: App\Ship\Parents\Transformers\Transformer as ShipTransformer;
use Apiato\Core\Transformers\Transformer as CoreTransformer;
use League\Fractal\Resource\Collection; // If you have collections to include
use Illuminate\Support\Facades\Storage; // To generate URLs for files

class SongTransformer extends CoreTransformer
{
    /**
     * List of resources to automatically include
     */
    protected array $defaultIncludes = [
        // e.g., 'user' if there's a user relationship
    ];

    /**
     * List of resources possible to include
     */
    protected array $availableIncludes = [
        // e.g., 'comments'
    ];

    /**
     * A Fractal transformer.
     *
     * @param Song $song The song model.
     * @return array The transformed data.
     */
    public function transform(Song $song): array
    {
        $response = [
            'object' => $song->getResourceKey(), // Should be 'Song'
            'id' => $song->getHashedKey(), // Or $song->id if not using Hashed IDs

            'title' => $song->title,
            'artist' => $song->artist,
            'lyrics' => $song->lyrics,

            // Generate full URLs for file paths if they are stored relatively
            // Assumes files are stored in 'public' disk and accessible via 'storage/' symlink
            'file_url' => $song->file_path ? Storage::disk('public')->url($song->file_path) : null,
            'cover_url' => $song->cover_path ? Storage::disk('public')->url($song->cover_path) : null,

            'created_at' => $song->created_at,
            'updated_at' => $song->updated_at,

            // Add realId for debugging or specific use cases if needed
            // 'real_id' => $song->id,
        ];

        // Apply response Etag (not essential for this simulation but good practice)
        // $response = $this->ifAdmin([
        //     'real_id' => $song->id,
        //     // 'deleted_at' => $song->deleted_at,
        // ], $response);

        return $response;
    }

    // Example of including a related resource:
    // public function includeUser(Song $song): Item
    // {
    //     return $this->item($song->user, new UserTransformer());
    // }
}
