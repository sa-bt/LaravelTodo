<?php


namespace App\Repositories;

use App\Http\Requests\LoginRequest;
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
class UserRepository extends BaseRepository implements UserInterface
{
    public function all($columns = array('*'), $orderBy = 'id', $sortBy = 'DESC')
    {
        return $this->model->with('tasks')->orderBy($orderBy, $sortBy)->get($columns);
    }
}
