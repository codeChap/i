<?php

require 'vendor/autoload.php';

use codechap\i\I;
use codechap\i\Msg;

// App Credentials
$appId = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/FACEBOOK_APP_ID.txt'));
$appSecret = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/FACEBOOK_APP_SECRET.txt'));

// Configuration - Run setup.php to get these values
$facebookPageId = ''; // Will be set by setup.php
$igAccountId = ''; // Optional: Will be retrieved from Facebook page if not set
$accessToken = ''; // Will be set by setup.php

// Load from .env.local if it exists
if (file_exists('.env.local')) {
    $env = parse_ini_file('.env.local');
    $facebookPageId = $env['FACEBOOK_PAGE_ID'] ?? $facebookPageId;
    $igAccountId = $env['INSTAGRAM_ACCOUNT_ID'] ?? $igAccountId;
    $accessToken = $env['FACEBOOK_ACCESS_TOKEN'] ?? $accessToken;
    $pageName = $env['FACEBOOK_PAGE_NAME'] ?? 'Your Page';
    $appId = $env['FACEBOOK_APP_ID'] ?? $appId;
    $appSecret = $env['FACEBOOK_APP_SECRET'] ?? $appSecret;
    echo "Using configuration for: {$pageName}\n\n";
}

// Initialize Instagram client
$ig = new I();
$ig->set('facebookPageId', $facebookPageId);
$ig->set('accessToken', $accessToken);

if ($igAccountId) {
    $ig->set('igAccountId', $igAccountId);
}

try {
    // Example 1: Simple photo post with caption
    echo "Example 1: Simple photo post with caption\n";

    // Using the famous Lenna test image for testing
    $imageUrl = 'https://raw.githubusercontent.com/codeChap/i/refs/heads/master/lenna1.jpg';

    $msg = new Msg();
    $msg->set('content', 'Sharing the iconic Lenna test image! Lenna is a classic standard in image processing, used worldwide since the 1970s for digital image algorithms and computer vision research.');
    $msg->set('image', $imageUrl);

    $response = $ig->post($msg);
    echo "✓ Posted successfully! ID: " . $response['id'] . "\n\n";

    // Example 2: Carousel post with multiple photos
    echo "Example 2: Carousel post with multiple photos\n";

    // For carousel posts, using the Lenna image
    $carouselImages = [
        'https://raw.githubusercontent.com/codeChap/i/refs/heads/master/lenna1.jpg', // Lenna image
        'https://raw.githubusercontent.com/codeChap/i/refs/heads/master/lenna2.jpg', // Same for testing
    ];

    $photo1 = new Msg();
    $photo1->set('content', 'The iconic Lenna test image, a staple in image processing since 1973!');
    $photo1->set('image', $carouselImages[0]);

    $photo2 = new Msg();
    $photo2->set('content', 'Lenna: The face that launched a thousand algorithms in computer vision.');
    $photo2->set('image', $carouselImages[1]);

    $photo3 = new Msg();
    $photo3->set('content', 'Celebrating Lenna, the benchmark image for digital image compression and enhancement.');
    $photo3->set('image', $carouselImages[0]); // Using the same image for testing

    $response = $ig->post([$photo1, $photo2, $photo3]);
    echo "✓ Carousel post created! ID: " . $response['id'] . "\n\n";

} catch (\RuntimeException $e) {
    echo "❌ Instagram API Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "Done!\n";

// Helper function example for uploading to cloud storage
function uploadToCloudStorage(string $localFilePath): string {
    // This is a placeholder - implement based on your cloud storage provider
    // Examples: AWS S3, Google Cloud Storage, etc.

    // Example AWS S3 implementation:
    /*
    $s3Client = new \Aws\S3\S3Client([
        'region' => 'us-east-1',
        'version' => 'latest'
    ]);

    $bucketName = 'your-bucket-name';
    $key = 'instagram-uploads/' . basename($localFilePath);

    $result = $s3Client->putObject([
        'Bucket' => $bucketName,
        'Key' => $key,
        'SourceFile' => $localFilePath,
        'ACL' => 'public-read'
    ]);

    return $result['ObjectURL'];
    */

    throw new \Exception('uploadToCloudStorage() function needs to be implemented based on your cloud storage provider');
}
