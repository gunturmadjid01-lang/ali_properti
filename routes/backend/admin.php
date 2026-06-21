<?

use App\Http\Controllers\Admin\UnitRumahController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->group(function () {

    Route::get('unit-rumah', [UnitRumahController::class, 'index'])->name('admin.unit-rumah.index');
});

?>