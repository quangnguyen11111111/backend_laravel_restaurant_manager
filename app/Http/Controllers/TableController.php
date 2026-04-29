<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Requests\CreateTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    public function __construct(
        private readonly TableService $tableService
    ) {}

    /**
     * GET /tables
     */
    public function index(): JsonResponse
    {
        $result = $this->tableService->index();

        return response()->json($result);
    }

    /**
     * GET /tables/{number}
     */
    public function show(int $number): JsonResponse
    {
        try {
            $result = $this->tableService->show($number);

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * POST /tables
     */
    public function store(CreateTableRequest $request): JsonResponse
    {
        try {
            $result = $this->tableService->store($request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * PUT /tables/{number}
     */
    public function update(UpdateTableRequest $request, int $number): JsonResponse
    {
        try {
            $result = $this->tableService->update($number, $request->validated());

            return response()->json($result);
        } catch (ServiceException $exception) {
            return $this->jsonErrorResponse($exception);
        }
    }

    /**
     * DELETE /tables/{number}
     */
    public function destroy(int $number): JsonResponse
    {
        try {
            $result = $this->tableService->destroy($number);

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
