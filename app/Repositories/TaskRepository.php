<?php


namespace App\Repositories;

use App\Http\Requests\LoginRequest;
use App\Interfaces\TaskInterface;
use App\Interfaces\UserInterface;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class UserRepository
 *
 * @package \App\Repositories
 */
class TaskRepository extends BaseRepository implements TaskInterface
{
    public function all($columns = array('*'), $orderBy = 'id', $sortBy = 'DESC')
    {
        return $this->model->with('user')->orderBy($orderBy, $sortBy)->get($columns);
    }

    public function getAllTrashed($columns = array('*'), $orderBy = 'id')
    {
        return $this->model->with('user')->onlyTrashed()->orderBy($orderBy)->get($columns);
    }
}
