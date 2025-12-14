<?php

namespace App\Repositories\TreeAccount;

use App\Repositories\BaseRepository\BaseRepositoryInterface;

interface TreeAccountRepositoryInterface extends BaseRepositoryInterface
{
        public function getAccounts($request);
}
