<?php

namespace App\Containers\AppSection\Music\UI\API\Requests;

// Apiato's base request class
use Apiato\Core\Http\Requests\Request as CoreRequest;
use Illuminate\Validation\Rule; // For more complex rules if needed

class UploadSongRequest extends CoreRequest
{
    /**
     * Define which Roles and/or Permissions has access to this request.
     */
    protected array $access = [
        'permissions' => '', // e.g., 'upload-songs'
        'roles' => '',       // e.g., 'admin|contributor'
    ];

    /**
     * Id's that needs decoding before applying a validation rule.
     */
    protected array $decode = [
        // 'id',
    ];

    /**
     * Defining the URL parameters (`/stores/999/items`) allows applying
     * validation rules on them and allows accessing them like request data.
     */
    protected array $urlParameters = [
        // 'id',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'lyrics' => 'nullable|string',
            'song_file' => 'required|file|mimes:mp3|max:20480', // Max 20MB, adjust as needed
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB, adjust
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For now, allow all authenticated users or based on a general permission
        // return $this->check([
        //    'hasAccess', // Or specific permission like 'upload-songs'
        // ]);
        return true;
    }

    /**
     * Custom messages for validation.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'song_file.required' => 'The song file is required.',
            'song_file.mimes' => 'The song file must be an MP3.',
            'song_file.max' => 'The song file may not be greater than 20MB.',
            'cover_image.image' => 'The cover must be an image file.',
            'cover_image.mimes' => 'The cover image must be a JPEG, PNG, JPG, or GIF.',
            'cover_image.max' => 'The cover image may not be greater than 5MB.',
        ];
    }
}
