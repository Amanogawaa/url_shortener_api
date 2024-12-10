<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';


$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost:4200',
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "./modules/url_shortener.php";
require_once "./config/connection.php";
require_once "./modules/payload.php";
require_once "./modules/getter.php";


try {
    $db = new Connection();
    $pdo = $db->connect();
    $payload = new GlobalMethods();
    $get = new Getter($pdo);


    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    $post = new URLShortener($pdo);

    // Check if 'request' parameter is set in the request
    if (isset($_REQUEST['request'])) {
        // Split the request into an array based on '/'
        $request = explode('/', $_REQUEST['request']);
    } else {
        // If 'request' parameter is not set, return a 404 response
        echo "Not Found";
        http_response_code(404);
        exit(); // Add exit to stop further execution
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'OPTIONS':
            http_response_code(200);
            exit;

        case 'GET':
            switch ($request[0]) {
                case 'urls':
                    if (count($request) > 1) {
                        $result = $get->getURLS($request[1]);
                        echo json_encode($result);
                    } else {
                        $result = $get->getURLS();
                        echo json_encode($result);
                    }
                    break;

                default:
                    break;
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (isset($data['encrypted'])) {
                try {
                    $decryptedData = $payload->decryptPayload($data['encrypted']);

                    if (isset($decryptedData['url'])) {
                        $result = $post->shortenAndStore($decryptedData['url']);
                        echo json_encode($result);
                    } else {
                        echo json_encode([
                            "status" => [
                                "remarks" => "failed",
                                "message" => "URL not provided in encrypted data"
                            ],
                            "payload" => null,
                            "prepared_by" => "Etrella Yue",
                            "timestamp" => date_create()
                        ]);
                        http_response_code(400);
                    }
                } catch (Exception $e) {
                    echo json_encode([
                        "status" => [
                            "remarks" => "failed",
                            "message" => "Decryption failed"
                        ],
                        "payload" => null,
                        "prepared_by" => "Etrella Yue",
                        "timestamp" => date_create()
                    ]);
                    http_response_code(400);
                }
            } else {
                echo json_encode([
                    "status" => [
                        "remarks" => "failed",
                        "message" => "Encrypted data not provided"
                    ],
                    "payload" => null,
                    "prepared_by" => "Etrella Yue",
                    "timestamp" => date_create()
                ]);
                http_response_code(400);
            }
            break;
    }
} catch (\Throwable $th) {
    echo json_encode(["error" => $th->getMessage(), "code" => 500]);
    http_response_code(500);
    exit;
}
