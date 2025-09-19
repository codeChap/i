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

    // Using the provided image URL for testing
    $imageUrl = 'https://holidaystays.co.za/imgCache/2270/d71db83657b0aca07ccfda1ceb69f82f.webp';

    $msg = new Msg();
    $msg->set('content', 'Hello Instagram! This is a test post from the PHP I package.');
    $msg->set('image', $imageUrl);

    $response = $ig->post($msg);
    echo "✓ Posted successfully! ID: " . $response['id'] . "\n\n";

    // Example 2: Photo post using local file (requires upload to public URL)
    if (file_exists('test-image.jpg')) {
        echo "Example 2: Photo post with local file\n";
        echo "NOTE: Instagram requires public URLs. You need to upload local files to a service like AWS S3, Google Cloud Storage, etc.\n";

        // This example shows how you would handle local files in production:
        $localImage = 'test-image.jpg';

        // Step 1: Upload to public storage and get URL
        // $publicUrl = uploadToCloudStorage($localImage);

        // Step 2: Create post with public URL
        // $photoMsg = new Msg();
        // $photoMsg->set('content', 'Check out this amazing photo!');
        // $photoMsg->set('image', $publicUrl);
        // $response = $ig->post($photoMsg);
        // echo "✓ Photo posted! ID: " . $response['id'] . "\n\n";

        echo "❌ Local file example commented out - requires public URL upload implementation\n\n";
    }

    // Example 3: Carousel post with multiple photos
    echo "Example 3: Carousel post with multiple photos\n";

    // For carousel posts, using the provided image URL
    $carouselImages = [
        'https://holidaystays.co.za/imgCache/2270/d71db83657b0aca07ccfda1ceb69f82f.webp', // Using provided URL
        'https://holidaystays.co.za/imgCache/2270/d71db83657b0aca07ccfda1ceb69f82f.webp', // Using same URL for testing
    ];

    $photo1 = new Msg();
    $photo1->set('content', 'Beautiful view from my vacation!');
    $photo1->set('image', $carouselImages[0]);

    $photo2 = new Msg();
    $photo2->set('content', 'Amazing sunset scene');
    $photo2->set('image', $carouselImages[1]);

    $photo3 = new Msg();
    $photo3->set('content', 'Photo gallery from my recent trip!');
    $photo3->set('image', $carouselImages[0]); // Using the same image for testing

    $response = $ig->post([$photo1, $photo2, $photo3]);
    echo "✓ Carousel post created! ID: " . $response['id'] . "\n\n";

    // Example 4: Get Instagram account info
    echo "Example 4: Instagram account information\n";
    $info = $ig->me();
    echo "✓ Instagram Username: " . $info['data']['username'] . "\n";
    echo "✓ Account Name: " . $info['data']['name'] . "\n";
    echo "✓ Followers Count: " . ($info['data']['followers_count'] ?? 'Not available') . "\n";
    echo "✓ Media Count: " . ($info['data']['media_count'] ?? 'Not available') . "\n\n";

    // Example 5: Get Instagram Account ID from Facebook Page
    echo "Example 5: Get Instagram Account ID from Facebook Page\n";
    if (!$igAccountId) {
        // The post() method above would have already retrieved this, so we can access it
        $ig->set('facebookPageId', $facebookPageId);
        $ig->set('accessToken', $accessToken);

        // This will trigger the retrieval if we don't have igAccountId
        $tempIg = new I();
        $tempIg->set('facebookPageId', $facebookPageId);
        $tempIg->set('accessToken', $accessToken);

        try {
            $accountInfo = $tempIg->me();
            echo "✓ Found Instagram Account: @" . $accountInfo['data']['username'] . " (ID: " . $accountInfo['data']['id'] . ")\n\n";
        } catch (\Exception $e) {
            echo "❌ No Instagram Business Account linked to this Facebook Page\n";
            echo "Make sure your Facebook Page has an Instagram Business Account connected.\n\n";
        }
    }

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
