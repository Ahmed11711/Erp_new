<?php

namespace App\Repositories\stock;

use App\Repositories\stock\stockRepositoryInterface;
use App\Repositories\BaseRepository\BaseRepository;
use App\Models\stock;

class stockRepository extends BaseRepository implements stockRepositoryInterface
{
    public function __construct(stock $model)
    {
        parent::__construct($model);
    }
}
