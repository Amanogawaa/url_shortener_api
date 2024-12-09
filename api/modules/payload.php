<?php

require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../vendor/autoload.php';

class GlobalMethods
{
    private $encryption_key;

    public function __construct()
    {
        try {
            $this->encryption_key = Environment::getInstance()->get('ENCRYPTION_KEY');
        } catch (Exception $e) {
            error_log("Error in constructor: " . $e->getMessage());
            throw $e;
        }
    }
    // public function sendPayload($data, $remarks, $message, $code)
    // {
    //     $status = array("remarks" => $remarks, "message" => $message);
    //     http_response_code($code);
    //     return array(
    //         "status" => $status,
    //         "payload" => $data,
    //         "prepared_by" => "Etrella Yue",
    //         "timestamp" => date_create()
    //     );
    // }

    // SEND PAYLOAD 
    protected function sendPayload($payload, $remarks, $message, $code)
    {
        $status = array(
            "remarks" => $remarks,
            "message" => $message
        );

        // ONLY ENCRYPT SUCCESSFUL RESPONSES
        $finalPayload = ($code === 200 && $payload !== null)
            ? $this->encryptPayload($payload)
            : $payload;

        $responseData = [
            "status" => $status,
            "payload" => $finalPayload,
            "prepared_by" => "Etrella Yue",
            "timestamp" => date_create()
        ];

        http_response_code($code);
        return $responseData;
    }

    protected function encryptPayload($data)
    {
        try {
            // CONVERT PAYLOAD TO JSON
            $jsonData = json_encode($data);

            // NOTE TO SELF: REVISE THIS
            $key = hex2bin($this->encryption_key);

            // Generate random IV
            $iv = openssl_random_pseudo_bytes(16);

            // ENCRYPT
            $encrypted = openssl_encrypt(
                $jsonData,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            // Combine IV and encrypted data
            $combined = $iv . $encrypted;

            // ENCODE RESULT TO BASE64
            $base64 = base64_encode($combined);

            return $base64;
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            throw new Exception("Encryption failed");
        }
    }

    public function decryptPayload($encryptedData)
    {
        try {
            // Decode base64
            $combined = base64_decode($encryptedData);

            // Extract IV (first 16 bytes)
            $iv = substr($combined, 0, 16);

            // Extract encrypted data (remaining bytes)
            $encrypted = substr($combined, 16);

            // Convert hex key to binary
            $key = hex2bin($this->encryption_key);

            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            // Parse JSON
            return json_decode($decrypted, true);
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            throw new Exception("Decryption failed");
        }
    }
}
