<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Requests\CreateDishRequest;
use App\Http\Requests\DeleteUploadedDishImageRequest;
use App\Http\Requests\GetDishListRequest;
use App\Http\Requests\UploadDishImageRequest;
use App\Http\Requests\UpdateDishRequest;
use App\Models\Account;
use App\Services\DishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class DishController extends Controller
{
    public function __construct(
        private readonly DishService $dishService
    ) {}

    /**
     * GET /dishes
     */
    public function index(GetDishListRequest $request): JsonResponse
    {
        $result = $this->dishService->index($request->validated());

        return response()->json($result);
    }

    /**
     * GET /dishes/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->dishService->show($id);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * POST /dishes
     */
    public function store(CreateDishRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();

            if (!$actor instanceof Account) {
                throw new ServiceException('Không thể xác định người dùng hiện tại.', 401);
            }

            $result = $this->dishService->store($request->validated(), $actor);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * PUT /dishes/{id}
     */
    public function update(UpdateDishRequest $request, int $id): JsonResponse
    {
        try {
            $actor = $request->user();

            if (!$actor instanceof Account) {
                throw new ServiceException('Không thể xác định người dùng hiện tại.', 401);
            }

            $result = $this->dishService->update($id, $request->validated(), $actor);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * DELETE /dishes/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->dishService->destroy($id);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * POST /dishes/image
     * Upload ảnh món ăn tạm thời
     */
    public function uploadImage(UploadDishImageRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();

            if (!$actor instanceof Account) {
                throw new ServiceException('Không thể xác định người dùng hiện tại.', 401);
            }

            $image = $request->file('image');

            if (!$image instanceof UploadedFile) {
                throw new ServiceException('Hình ảnh món ăn không hợp lệ', 422, [
                    ['field' => 'image', 'message' => 'Hình ảnh món ăn không hợp lệ'],
                ]);
            }

            $result = $this->dishService->uploadImage($actor, $image);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * DELETE /dishes/image
     * Xóa ảnh món ăn tạm thời
     */
    public function deleteUploadedImage(DeleteUploadedDishImageRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();

            if (!$actor instanceof Account) {
                throw new ServiceException('Không thể xác định người dùng hiện tại.', 401);
            }

            $result = $this->dishService->deleteUploadedImage(
                $actor,
                $request->validated('imageS3Key')
            );

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    private function jsonErrorResponse(ServiceException $exception): JsonResponse
    {
        $payload = [
            'message' => $exception->getMessage(),
            'statusCode' => $exception->getStatusCode(),
        ];

        if ($exception->getErrors() !== []) {
            $payload['errors'] = $exception->getErrors();
        }

        return response()->json($payload, $exception->getStatusCode());
    }
}
