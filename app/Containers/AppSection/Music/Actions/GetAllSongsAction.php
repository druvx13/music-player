<?php

namespace App\Containers\AppSection\Music\Actions;

// Apiato's base action class
// Path might vary: App\Ship\Parents\Actions\Action as ShipAction;
use Apiato\Core\Actions\Action as CoreAction;
use App\Containers\AppSection\Music\Models\Song;
use Illuminate\Support\Collection; // Or LengthAwarePaginator if paginating

class GetAllSongsAction extends CoreAction
{
    public function run(): Collection // Adjust return type if paginating
    {
        // In a more complex scenario, you might use a Task to fetch data
        // e.g., return app(GetAllSongsTask::class)->run();

        // For this simulation, directly query using the model
        return Song::orderBy('created_at', 'desc')->get();

        // If pagination is desired:
        // return Song::orderBy('created_at', 'desc')->paginate();
    }
}
