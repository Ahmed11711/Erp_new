<?php

namespace App\Repositories\stock;

use App\Repositories\stock\stockRepositoryInterface;
use App\Repositories\BaseRepository\BaseRepository;
use App\Models\Stock;

class stockRepository extends BaseRepository implements stockRepositoryInterface
{
    public function __construct(Stock $model)
    {
        parent::__construct($model);
    }
}
