<?php

require_once 'payload.php';

class URLShortener extends GlobalMethods
{
    private $conn;
    private $yourlsEndpoint;
    private $yourlsApiKey;

    public function __construct(\PDO $pdo)
    {
        parent::__construct();
        $this->conn = $pdo;
        $this->yourlsEndpoint = 'http://localhost/yourls/yourls-api.php';
        $this->yourlsApiKey =  Environment::getInstance()->get('YOURLS_API_KEY');
    }

    public function shortenAndStore($url)
    {
        try {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->sendPayload(null, "failed", "Invalid URL provided", 400);
            }

            $existingShortUrl = $this->getShortUrlByLongUrl($url);
            if ($existingShortUrl) {
                return $this->sendPayload(
                    ["short_url" => $existingShortUrl],
                    "success",
                    "Retrieved existing shortened URL",
                    200
                );
            }

            $shortUrl = $this->shortenWithYOURLS($url);
            if ($shortUrl) {
                return $this->sendPayload(
                    ["short_url" => $shortUrl],
                    "success",
                    "Successfully shortened URL",
                    200
                );
            }

            return $this->sendPayload(null, "failed", "Failed to shorten URL", 500);
        } catch (\Throwable $th) {
            return $this->sendPayload(null, "failed", $th->getMessage()(), 500);
        }
    }

    private function shortenWithYOURLS($url)
    {

        $customKeyword = $this->generateRandomKeyword();

        $apiUrl = $this->yourlsEndpoint
            . '?signature=' . $this->yourlsApiKey
            . '&action=shorturl'
            . '&format=json'
            . '&url=' . urlencode($url)
            . '&keyword=' . $customKeyword;

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception("CURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['shorturl'])) {
            throw new \Exception("Error shortening URL with YOURLS: " . ($data['message'] ?? 'Unknown error'));
        }

        return $data['shorturl'];
    }

    private function generateRandomKeyword($length = 6)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    public function getShortUrlByLongUrl($keyword)
    {
        $query = "SELECT url FROM yourls_url WHERE keyword = :keyword";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':keyword', $keyword);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getOriginalUrl($url)
    {
        $query = "SELECT url FROM yourls_url WHERE url = :url";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':url', $url);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
