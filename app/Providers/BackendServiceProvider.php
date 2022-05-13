<?php

namespace App\Providers;

use App\Interfaces\BaseInterface;
use App\Interfaces\TaskInterface;
use App\Interfaces\UserInterface;
use App\Models\Task;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class BackendServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            BaseInterface::class,
            BaseRepository::class
        );

        $this->app->bind(
            UserInterface::class,
            function () {
                return new UserRepository(new User);
            }
        );

        $this->app->bind(TaskInterface::class,
            function () {
                return new TaskRepository(new Task());
            });


    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
