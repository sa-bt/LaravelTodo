<?php


namespace App\Interfaces;

use App\Http\Requests\LoginRequest;
use App\Models\User;

/**
 * Interface UserInterface
 * @package App\Interfaces
 */
interface TaskInterface extends BaseInterface
{

    public function getAllTrashed($columns = array('*'), $orderBy = 'id');
}
