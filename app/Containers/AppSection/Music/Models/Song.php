<?php

namespace App\Containers\AppSection\Music\Models;

use Apiato\Core\Framework\Models\Model as CoreModel; // Adjust if Apiato has a different base model path
use Illuminate\Database\Eloquent\Factories\HasFactory; // Optional: if using factories

class Song extends CoreModel
{
    // If you're using factories with Laravel 8+
    // use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'artist',
        'file_path',
        'cover_path',
        'lyrics',
        // 'created_at' and 'updated_at' are handled by Eloquent timestamps
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Potentially hide fields if needed, e.g., 'file_path' if you always serve through a signed URL
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // 'created_at' => 'datetime', // Handled by Eloquent
        // 'updated_at' => 'datetime', // Handled by Eloquent
    ];

    // Add any relationships here if needed in the future
    // Example:
    // public function user()
    // {
    //    return $this->belongsTo(User::class);
    // }
}
