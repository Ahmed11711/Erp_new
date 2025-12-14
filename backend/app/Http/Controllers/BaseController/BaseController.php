<?php

namespace App\Http\Controllers\BaseController;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;

abstract class BaseController extends Controller
{
    use ApiResponseTrait;

    protected $repository;
    protected string $storeRequestClass;
    protected string $updateRequestClass;
    protected string $resourceClass;
    protected ?string $collectionName = null;
    protected array $fileFields = [];
    protected string $uploadDisk = 'public';
    protected array $relations = [];

    public function __construct() {}

    protected function initService($repository, string $collectionName, array $fileFields = [], string $uploadDisk = 'public'): void
    {
        $this->repository = $repository;
        $this->collectionName = $collectionName;
        $this->fileFields = $fileFields;
        $this->uploadDisk = $uploadDisk;
    }

    public function index(Request $request): JsonResponse
    {
        $relations = $this->relations ?? [];

        $data = !empty($relations)
            ? $this->repository->allRelations($relations)
            : $this->repository->all();

        if (class_exists($this->resourceClass)) {
            $data = $this->resourceClass::collection($data);
        }

        return $this->successResponse($data, "{$this->collectionName} list retrieved successfully");
    }

    public function show(int $id): JsonResponse
    {
        $relations = $this->relations ?? [];

        $record = !empty($relations)
            ? $this->repository->findWithRelations($id, $relations)
            : $this->repository->find($id);

        if (!$record) {
            return $this->errorResponse("Record not found", 404);
        }

        return $this->successResponse(new $this->resourceClass($record), 'Record retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();

        try {
            DB::beginTransaction();

            $validated = $this->handleFileUploads($request, $validated);
            $record = $this->repository->create($validated);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error creating {$this->collectionName}: " . $e->getMessage());
            return $this->errorResponse("Failed to create {$this->collectionName}", 500);
        }

        return $this->successResponse(new $this->resourceClass($record), 'Record created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = app($this->updateRequestClass)->validated();

        $record = $this->repository->find($id);
        if (!$record) {
            return $this->errorResponse("Record not found", 404);
        }

        try {
            DB::beginTransaction();

            $validated = $this->handleFileUploads($request, $validated, $record);
            $record = $this->repository->update($id, $validated);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error updating {$this->collectionName}: " . $e->getMessage());
            return $this->errorResponse("Failed to update record", 500);
        }

        return $this->successResponse(new $this->resourceClass($record), 'Record updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $deletedCount = $this->repository->delete($id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error deleting {$this->collectionName}: " . $e->getMessage());
            return $this->errorResponse("Failed to delete record(s)", 500);
        }

        return $this->successResponse(null, "$deletedCount record(s) deleted successfully");
    }

    protected function handleFileUploads(Request $request, array $validated, $existingRecord = null): array
    {
        if (empty($this->fileFields)) return $validated;

        foreach ($this->fileFields as $field) {
            if ($request->hasFile($field)) {
                try {
                    $file = $request->file($field);
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs("uploads/{$this->collectionName}", $filename, $this->uploadDisk);

                    if ($existingRecord && !empty($existingRecord->$field)) {
                        Storage::disk($this->uploadDisk)->delete('uploads/' . $this->collectionName . '/' . basename($existingRecord->$field));
                    }

                    $validated[$field] = Storage::disk($this->uploadDisk)->url($path);
                } catch (\Throwable $e) {
                    Log::error("File upload failed for field [{$field}] in {$this->collectionName}: " . $e->getMessage());
                }
            }
        }

        return $validated;
    }
}
