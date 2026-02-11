<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController
{
    /**
     * Api response format
     *
     * @param string $message
     * @param mixed $payload = null
     * @param int $responseCode
     *
     * @return JsonResponse
     */
    protected function response(
        ?string $message = null,
        mixed $payload = [],
        int $responseCode = Response::HTTP_OK
    ): JsonResponse {
        $response = [
            "message" => json_decode($message) ?? $message,
        ];
        $data = is_object($payload)
            ? $payload->response()->getData(true)
            : ["data" => $payload];

        $response = array_merge($response, $data);
        if (
            $payload === null
            || (is_array($payload) && $payload === [])
        ) {
            unset($response["data"]);
        }

        if ($response["message"] == null) {
            unset($response["message"]);
        }

        return response()->json($response, $responseCode);
    }

    public function successResponse(
        ?string $message = null,
        mixed $payload = [],
        int $responseCode = Response::HTTP_OK
    ): JsonResponse {
        return $this->response(
            message: $message,
            payload: $payload,
            responseCode: $responseCode
        );
    }

    public function errorResponse(
        ?string $message = null,
        int $responseCode = Response::HTTP_INTERNAL_SERVER_ERROR
    ): JsonResponse {
        return $this->response(
            message: $message,
            responseCode: $responseCode
        );
    }
}
