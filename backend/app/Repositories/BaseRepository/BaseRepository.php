<?php

namespace App\Repositories\BaseRepository;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Repositories\BaseRepository\BaseRepositoryInterface;

class BaseRepository implements BaseRepositoryInterface
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function query()
    {
        return $this->model->newQuery();
    }
    public function all()
    {
        return $this->model->all();
    }

    public function allRelations(array $relations)
    {
        return $this->model->with($relations)->get();
    }

    public function paginate()
    {
        return $this->model->paginate();
    }

    public function find($id)
    {
        return $this->model->find($id);
    }
    public function findWithRelations(int $id, array $relations = [])
{
    return $this->model->with($relations)->find($id);
}
    public function findBYKey($key, $value)
    {
        return $this->model->where($key, $value)->first();
    }

    public function AllBYKey($key, $value)
    {
        return $this->model->where($key, $value)->get();
    }


    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $record = $this->find($id);
        $record->update($data);
        return $record;
    }

    public function delete($id)
    {
        $record = $this->find($id);
        return $record->delete();
    }
    public function deleteMultiple(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }
        return $this->model->whereIn('id', $ids)->delete();
    }
    public function deleteWithAttachments(array $ids): int
    {
        $records = $this->model->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            foreach ($record->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment->file)) {
                    Storage::disk('public')->delete($attachment->file);
                }
                $attachment->delete();
            }
            $record->delete();
        }

        return count($records);
    }
}
