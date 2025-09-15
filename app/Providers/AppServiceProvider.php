<?php

namespace App\Providers;

use App\Models\GoalWeek;
use App\Observers\GoalWeekObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Repositories\GoalRepository;
use App\Repositories\WeekRepository;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // $this->app->singleton(GoalRepository::class);
        //     $this->app->singleton(WeekRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Route::prefix('api')
            ->group(base_path('routes/api.php'));
        // GoalWeek::observe(GoalWeekObserver::class);
    }
}
