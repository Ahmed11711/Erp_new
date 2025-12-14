<?php

namespace App\Repositories\BaseRepository;

interface BaseRepositoryInterface {

    public function all();

    public function paginate();
    public function query();

    public function find($id);
    public function findBYKey($key,$value);
    public function AllBYKey($key,$value);
    public function findWithRelations(int $id, array $relations = []);


    public function allRelations(array $data);
    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);
    public function deleteMultiple(array $id);
    public function deleteWithAttachments(array $id);
}
