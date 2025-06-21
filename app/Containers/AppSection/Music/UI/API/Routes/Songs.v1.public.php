<?php

/**
 * @apiDefine SongSuccessSingleResponse
 * @apiSuccessExample {json} Success-Response:
 * HTTP/1.1 200 OK
 * {
 *   "data": {
 *     "object": "Song",
 *     "id": "hashed_id",
 *     "title": "Song Title",
 *     "artist": "Artist Name",
 *     "lyrics": "Song lyrics...",
 *     "file_url": "http://localhost/storage/songs/filename.mp3",
 *     "cover_url": "http://localhost/storage/covers/covername.jpg",
 *     "created_at": "2023-10-27T12:00:00.000000Z",
 *     "updated_at": "2023-10-27T12:00:00.000000Z"
 *   },
 *   "meta": {
 *     "include": [],
 *     "custom": []
 *   }
 * }
 */

/**
 * @apiDefine SongSuccessMultipleResponse
 * @apiSuccessExample {json} Success-Response:
 * HTTP/1.1 200 OK
 * {
 *   "data": [
 *     {
 *       "object": "Song",
 *       "id": "hashed_id_1",
 *       "title": "Song Title 1",
 *       // ... other song fields
 *     },
 *     {
 *       "object": "Song",
 *       "id": "hashed_id_2",
 *       "title": "Song Title 2",
 *       // ... other song fields
 *     }
 *   ],
 *   "meta": {
 *     "include": [],
 *     "custom": [],
 *     "pagination": {
 *       "total": 2,
 *       "count": 2,
 *       "per_page": 15,
 *       "current_page": 1,
 *       "total_pages": 1,
 *       "links": {}
 *     }
 *   }
 * }
 */

use App\Containers\AppSection\Music\UI\API\Controllers\Controller as SongController;
use Illuminate\Support\Facades\Route;

// Route to get all songs
Route::get('songs', [SongController::class, 'getAllSongs'])
    ->name('api_music_get_all_songs');
    // ->middleware(['auth:api']); // Add if authentication is needed

// Route to upload a new song
Route::post('songs', [SongController::class, 'uploadSong'])
    ->name('api_music_upload_song');
    // ->middleware(['auth:api']); // Add if authentication is needed
