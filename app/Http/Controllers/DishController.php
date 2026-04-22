<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Requests\CreateDishRequest;
use App\Http\Requests\UpdateDishRequest;
use App\Services\DishService;
use Illuminate\Http\JsonResponse;

class DishController extends Controller
{
    public function __construct(
        private readonly DishService $dishService
    ) {}

    /**
     * GET /dishes
     */
    public function index(): JsonResponse
    {
        $result = $this->dishService->index();

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
            $result = $this->dishService->store($request->validated());

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
            $result = $this->dishService->update($id, $request->validated());

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
