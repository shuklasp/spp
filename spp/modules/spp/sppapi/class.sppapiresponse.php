<?php
namespace SPPMod\SPPAPI;

class SPPApiResponse
{

    public static function success($data = null, string $message = 'Success', int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    public static function error(string $message = 'Error', int $code = 400, $errors = null)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ]);
        exit;
    }

    public static function paginate(array $items, int $total, int $perPage, int $currentPage)
    {
        return self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => ceil($total / max(1, $perPage))
            ]
        ]);
    }
}
