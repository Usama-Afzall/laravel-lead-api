<?php

/*
|--------------------------------------------------------------------------
| Lead API routes (append to routes/api.php)
|--------------------------------------------------------------------------
|
| Laravel 11 ships without routes/api.php. Run `php artisan install:api`
| first; it creates the file and serves it under the /api prefix.
| Merge the `use` lines below with the ones already in the file.
|
*/

use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

// POST /api/leads
Route::post('/leads', LeadController::class)
    ->middleware('throttle:leads')
    ->name('leads.store');

/*
|--------------------------------------------------------------------------
| Named rate limiter (place in AppServiceProvider::boot())
|--------------------------------------------------------------------------
|
| Define it in a service provider rather than in this file so that it still
| exists when routes are cached with `php artisan route:cache`.
|
|   use Illuminate\Cache\RateLimiting\Limit;
|   use Illuminate\Http\Request;
|   use Illuminate\Support\Facades\RateLimiter;
|
|   public function boot(): void
|   {
|       // 5 submissions per minute per client IP. Tune to your traffic.
|       RateLimiter::for('leads', function (Request $request) {
|           return Limit::perMinute(5)->by($request->ip());
|       });
|   }
|
| Optional, in bootstrap/app.php: always render API errors (including the
| 429 from the limiter) as JSON, even when the client omits the Accept header.
|
|   ->withExceptions(function (Exceptions $exceptions) {
|       $exceptions->shouldRenderJsonWhen(
|           fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
|       );
|   })
*/
