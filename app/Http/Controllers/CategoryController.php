<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\GetCategoryListRequest;
use App\Http\Requests\GetCategoryTreeRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * GET /admin/categories - Lấy danh sách category dạng phẳng (admin)
     */
    public function indexForAdmin(GetCategoryListRequest $request): JsonResponse
    {
        $result = $this->categoryService->indexForAdmin($request->validated());

        return response()->json($result);
    }

    /**
     * GET /categories - Lấy danh sách category dạng cây (user)
     */
    public function indexForUser(GetCategoryTreeRequest $request): JsonResponse
    {
        $listTree = $request->boolean('listtree', true);
        $result = $this->categoryService->indexForUser($listTree);

        return response()->json($result);
    }

    /**
     * GET /categories/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->categoryService->show($id);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * POST /admin/categories
     */
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        try {
            $result = $this->categoryService->store($request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * PUT /admin/categories/{id}
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->categoryService->update($id, $request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * DELETE /admin/categories/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->categoryService->destroy($id);

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
