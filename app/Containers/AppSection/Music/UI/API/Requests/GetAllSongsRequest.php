<?php

namespace App\Containers\AppSection\Music\UI\API\Requests;

// Apiato's base request class
// Path might vary: App\Ship\Parents\Requests\Request as ShipRequest;
use Apiato\Core\Http\Requests\Request as CoreRequest;


class GetAllSongsRequest extends CoreRequest
{
    /**
     * Define which Roles and/or Permissions has access to this request.
     */
    protected array $access = [
        'permissions' => '', // e.g., 'list-songs'
        'roles' => '', // e.g., 'admin|user'
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
     */
    public function rules(): array
    {
        return [
            // No specific validation rules for getting all songs in this simple case
            // Could add rules for pagination parameters like 'page', 'limit' if implemented
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For now, allow all authenticated users or public access
        // In a real app, you'd check permissions: return $this->check([
        //    'hasAccess',
        // ]);
        return true;
    }
}
