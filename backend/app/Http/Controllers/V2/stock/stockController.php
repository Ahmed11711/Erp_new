<?php

namespace App\Http\Controllers\V2\stock;

use Illuminate\Http\JsonResponse;
use App\Http\Resources\V2\stock\stockResource;
use App\Http\Requests\V2\stock\stockStoreRequest;
use App\Http\Requests\V2\stock\stockUpdateRequest;
use App\Repositories\stock\stockRepositoryInterface;
use App\Http\Controllers\BaseController\BaseController;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class stockController extends BaseController
{
    use ApiResponseTrait;
    public function __construct(stockRepositoryInterface $repository)
    {
        parent::__construct();

        $this->initService(
            repository: $repository,
            collectionName: 'stock',
        );
        $this->relations= ['asset'];
        $this->storeRequestClass = stockStoreRequest::class;
        $this->updateRequestClass = stockUpdateRequest::class;
        $this->resourceClass = stockResource::class;
    }

}
