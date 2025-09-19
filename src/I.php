<?php

namespace codechap\i;

class I
{
    /**
     * Facebook Page ID (needed to get Instagram account ID)
     */
    private string $facebookPageId = '';

    /**
     * Instagram Business Account ID
     */
    private string $igAccountId = '';

    /**
     * Facebook Page Access Token (with Instagram permissions)
     */
    private string $accessToken = '';

    /**
     * Facebook Graph API Version
     */
    private string $apiVersion = 'v18.0';

    /**
     * Facebook Graph API Base URL
     */
    private const API_BASE_URL = 'https://graph.facebook.com/';

    /**
     * Set configuration values
     */
    public function set(string $key, string $value): self
    {
        switch ($key) {
            case 'facebookPageId':
                $this->facebookPageId = $value;
                break;
            case 'igAccountId':
                $this->igAccountId = $value;
                break;
            case 'accessToken':
                $this->accessToken = $value;
                break;
            case 'apiVersion':
                $this->apiVersion = $value;
                break;
            default:
                throw new \InvalidArgumentException("Unknown configuration key: {$key}");
        }

        return $this;
    }

    /**
     * Post to Instagram Business account
     *
     * @param Msg|array $content Single Msg object or array of Msg objects for carousel posts
     * @return array Response from Instagram API
     */
    public function post($content): array
    {
        if ((!$this->facebookPageId && !$this->igAccountId) || !$this->accessToken) {
            throw new \RuntimeException('Facebook Page ID or Instagram Account ID and Access Token are required');
        }

        // Get Instagram Account ID if not provided
        if (!$this->igAccountId) {
            $this->igAccountId = $this->getIgAccountId();
        }

        // Handle single message
        if ($content instanceof Msg) {
            return $this->postSingle($content);
        }

        // Handle array of messages (carousel post)
        if (is_array($content)) {
            return $this->postMultiple($content);
        }

        throw new \InvalidArgumentException('Content must be a Msg object or array of Msg objects');
    }

    /**
     * Post a single message with optional photo
     */
    private function postSingle(Msg $msg): array
    {
        $caption = $msg->get('content');
        $image = $msg->get('image');

        // Check if image is a URL or a local file
        $isUrl = $image && (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0);
        $hasLocalFile = $image && !$isUrl && file_exists($image);

        if ($isUrl || $hasLocalFile) {
            // Create media object for photo
            $mediaId = $this->createMedia($image, $caption);
            return $this->publishMedia($mediaId);
        } else {
            // Text-only post - Instagram requires media, so we can't post text-only
            throw new \RuntimeException('Instagram requires media for all posts. Please provide an image.');
        }
    }

    /**
     * Post multiple photos as carousel with a caption
     */
    private function postMultiple(array $messages): array
    {
        $childrenMediaIds = [];
        $carouselCaption = '';

        // Create media objects for each image
        foreach ($messages as $msg) {
            if (!($msg instanceof Msg)) {
                throw new \InvalidArgumentException('All array items must be Msg objects');
            }

            $image = $msg->get('image');
            // Check if image is a URL or a local file
            $isUrl = $image && (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0);
            $hasLocalFile = $image && !$isUrl && file_exists($image);

            if (!$image || (!$isUrl && !$hasLocalFile)) {
                throw new \RuntimeException('All messages must have valid images for carousel posts');
            }

            // Use the first non-empty caption as the carousel caption
            $content = $msg->get('content');
            if ($content && !$carouselCaption) {
                $carouselCaption = $content;
            }

            // Create media object without publishing
            $mediaId = $this->createMedia($image, '');
            $childrenMediaIds[] = $mediaId;
        }

        if (empty($childrenMediaIds)) {
            throw new \RuntimeException('No valid images found for carousel post');
        }

        // Create carousel container
        $endpoint = self::API_BASE_URL . $this->apiVersion . '/' . $this->igAccountId . '/media';

        $data = [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childrenMediaIds),
            'access_token' => $this->accessToken
        ];

        if ($carouselCaption) {
            $data['caption'] = $carouselCaption;
        }

        $carouselResponse = $this->makeRequest($endpoint, $data);

        if (!isset($carouselResponse['id'])) {
            throw new \RuntimeException('Failed to create carousel container');
        }

        // Publish the carousel
        return $this->publishMedia($carouselResponse['id']);
    }

    /**
     * Create Instagram media object
     */
    private function createMedia(string $imagePath, string $caption): string
    {
        $endpoint = self::API_BASE_URL . $this->apiVersion . '/' . $this->igAccountId . '/media';

        $data = [
            'image_url' => $this->uploadToTempUrl($imagePath), // Instagram requires public URLs for media
            'access_token' => $this->accessToken
        ];

        if ($caption) {
            $data['caption'] = $caption;
        }

        $response = $this->makeRequest($endpoint, $data);

        if (!isset($response['id'])) {
            throw new \RuntimeException('Failed to create media object');
        }

        return $response['id'];
    }

    /**
     * Publish Instagram media
     */
    private function publishMedia(string $creationId): array
    {
        $endpoint = self::API_BASE_URL . $this->apiVersion . '/' . $this->igAccountId . '/media_publish';

        $data = [
            'creation_id' => $creationId,
            'access_token' => $this->accessToken
        ];

        return $this->makeRequest($endpoint, $data);
    }

    /**
     * Get Instagram Account ID from Facebook Page ID
     */
    private function getIgAccountId(): string
    {
        $endpoint = self::API_BASE_URL . $this->apiVersion . '/' . $this->facebookPageId;

        $params = http_build_query([
            'access_token' => $this->accessToken,
            'fields' => 'instagram_business_account'
        ]);

        $ch = curl_init($endpoint . '?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400 || isset($result['error'])) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error occurred';
            throw new \RuntimeException('Facebook API error: ' . $errorMessage);
        }

        if (!isset($result['instagram_business_account']['id'])) {
            throw new \RuntimeException('No Instagram Business Account found for this Facebook Page');
        }

        return $result['instagram_business_account']['id'];
    }

    /**
     * Get Instagram account information
     */
    public function me(): array
    {
        if (!$this->igAccountId && !$this->facebookPageId) {
            throw new \RuntimeException('Instagram Account ID or Facebook Page ID is required');
        }

        if (!$this->accessToken) {
            throw new \RuntimeException('Access Token is required');
        }

        // Get Instagram Account ID if not provided
        if (!$this->igAccountId) {
            $this->igAccountId = $this->getIgAccountId();
        }

        $endpoint = self::API_BASE_URL . $this->apiVersion . '/' . $this->igAccountId;

        $params = http_build_query([
            'access_token' => $this->accessToken,
            'fields' => 'id,username,name,biography,followers_count,follows_count,media_count,profile_picture_url'
        ]);

        $ch = curl_init($endpoint . '?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400 || isset($result['error'])) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error occurred';
            throw new \RuntimeException('Instagram API error: ' . $errorMessage);
        }

        return ['data' => $result];
    }

    /**
     * Upload image to temporary URL for Instagram media creation
     * Instagram requires publicly accessible URLs for media uploads
     */
    private function uploadToTempUrl(string $imagePath): string
    {
        // If the input is already a URL, return it as-is
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return $imagePath;
        }

        // If it's a local file, check if it exists and handle accordingly
        if (file_exists($imagePath)) {
            // For local files, we need to upload them to a public URL first
            // In production, you'd implement this with AWS S3, Google Cloud Storage, etc.
            throw new \RuntimeException('Local file detected. Please upload your images to a public storage service (AWS S3, Google Cloud Storage, etc.) and provide the public URLs instead of local file paths. For testing, convert local files to URLs or use a local server.');
        }

        throw new \RuntimeException('Invalid image path. Must be a public URL or existing local file path.');
    }

    /**
     * Make HTTP request to Instagram Graph API
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400 || isset($result['error'])) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error occurred';
            throw new \RuntimeException('Instagram API error: ' . $errorMessage);
        }

        return $result;
    }
}