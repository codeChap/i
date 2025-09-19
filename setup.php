<?php

/**
 * Instagram Business Account Setup Script
 * Simple setup for the Instagram posting package
 */

echo "================================================\n";
echo "Instagram Business Account Setup\n";
echo "================================================\n\n";

echo "⚠️  IMPORTANT: Instagram Business Account Requirements\n";
echo "• You MUST have an Instagram Business Account\n";
echo "• It MUST be connected to a Facebook Page\n";
echo "• Convert at: https://business.instagram.com/getting-started\n\n";

// App Credentials
$appId = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/FACEBOOK_APP_ID.txt'));
$appSecret = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/FACEBOOK_APP_SECRET.txt'));

echo "App ID: {$appId}\n\n";

echo "STEP 1: Get Your USER Access Token\n";
echo "===================================\n\n";

echo "IMPORTANT: Meta's interface is confusing - follow these steps in order!\n\n";

echo "1. Go to Facebook Graph API Explorer:\n";
echo "   https://developers.facebook.com/tools/explorer/\n\n";

echo "2. Under 'Meta App', select your app: '{$appId}'\n\n";

echo "3. Add Permissions FIRST (required before you can get a token):\n";
echo "   • Click 'Add a Permission'\n";
echo "   • Add these permissions:\n";
echo "     - business_management (REQUIRED to unlock user token)\n";
echo "     - pages_show_list (REQUIRED to see your pages)\n";
echo "   • (Instagram permissions will be requested later in the popup)\n\n";

echo "4. Click the blue 'Generate Access Token' button\n";
echo "   (This is the big blue button, NOT the 'Get Token' dropdown)\n\n";

echo "5. A popup will appear:\n";
echo "   • Login if needed\n";
echo "   • Grant the requested permissions (including Instagram permissions)\n";
echo "   • SELECT YOUR FACEBOOK PAGES (important!)\n";
echo "   • Click Continue/OK through all screens\n\n";

echo "6. After the popup closes, you'll see your USER token in the Access Token field\n";
echo "   (it will start with 'EAA...')\n\n";

echo "7. Copy this entire token\n\n";

echo "Paste your USER access token here: ";
$userToken = trim(fgets(STDIN));

if (empty($userToken)) {
    echo "\n❌ No token provided. Exiting.\n";
    exit(1);
}

echo "\n================================================\n";
echo "STEP 2: Convert to Long-Lived User Token\n";
echo "================================================\n";

// Exchange short-lived user token for long-lived user token
$url = "https://graph.facebook.com/v18.0/oauth/access_token?" . http_build_query([
    'grant_type' => 'fb_exchange_token',
    'client_id' => $appId,
    'client_secret' => $appSecret,
    'fb_exchange_token' => $userToken
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$longLivedToken = $userToken; // Default to original

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $longLivedToken = $data['access_token'];
        echo "✓ Long-lived USER token obtained (valid for 60+ days)\n";
    }
} else {
    echo "Using original USER token\n";
}

echo "\n================================================\n";
echo "STEP 3: Get Page Access Tokens\n";
echo "================================================\n";
echo "Using your user token to fetch page tokens...\n\n";

// Get pages and their tokens using the user token
$url = "https://graph.facebook.com/v18.0/me/accounts?" . http_build_query([
    'access_token' => $longLivedToken,
    'fields' => 'id,name,access_token,is_published,tasks'
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Failed to get pages.\n";
    echo "Make sure you:\n";
    echo "1. Used a USER token (not a page token)\n";
    echo "2. Granted page permissions when generating the token\n";
    echo "3. Selected your pages in the permissions dialog\n";
    exit(1);
}

$data = json_decode($response, true);

if (!isset($data['data']) || empty($data['data'])) {
    echo "❌ No pages found.\n";
    echo "Make sure you:\n";
    echo "1. Are an admin of at least one Facebook page\n";
    echo "2. Selected your pages when generating the USER token\n";
    exit(1);
}

$pages = $data['data'];
echo "Found " . count($pages) . " page(s):\n";
echo "(Note: Only pages where you're an admin with proper permissions are shown)\n\n";

foreach ($pages as $index => $page) {
    $status = isset($page['is_published']) && !$page['is_published'] ? " [UNPUBLISHED]" : "";
    $tasks = isset($page['tasks']) ? " (Roles: " . implode(", ", $page['tasks']) . ")" : "";
    echo ($index + 1) . ". " . $page['name'] . " (ID: " . $page['id'] . ")" . $status . $tasks . "\n";
}

echo "\n";

if (count($pages) === 1) {
    $selectedPage = $pages[0];
} else {
    echo "Select a Facebook page (1-" . count($pages) . "): ";
    $choice = intval(trim(fgets(STDIN))) - 1;
    $selectedPage = $pages[$choice] ?? $pages[0];
}

$pageToken = $selectedPage['access_token'];
$pageName = $selectedPage['name'];
$pageId = $selectedPage['id'];

echo "\n================================================\n";
echo "STEP 4: Verify Instagram Business Account\n";
echo "================================================\n";

// Check if the selected page has an Instagram Business Account connected
$url = "https://graph.facebook.com/v18.0/{$pageId}?" . http_build_query([
    'access_token' => $pageToken,
    'fields' => 'instagram_business_account{id,username,name}'
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$instagramAccountId = null;
$instagramUsername = null;

if ($httpCode === 200) {
    $igData = json_decode($response, true);
    if (isset($igData['instagram_business_account'])) {
        $instagramAccountId = $igData['instagram_business_account']['id'];
        $instagramUsername = $igData['instagram_business_account']['username'];
        echo "✓ Instagram Business Account found: @" . $instagramUsername . "\n";
    } else {
        echo "⚠️  No Instagram Business Account found for this page.\n";
        echo "Please connect an Instagram Business Account to your Facebook Page:\n";
        echo "1. Go to your Facebook Page\n";
        echo "2. Settings → Instagram → Connect Account\n";
        echo "3. Follow the setup process\n";
        echo "4. Run this setup script again\n\n";
        exit(1);
    }
} else {
    echo "❌ Could not verify Instagram Business Account.\n";
    echo "Please check your permissions and try again.\n";
    exit(1);
}

echo "\n================================================\n";
echo "SUCCESS! Instagram Configuration Complete\n";
echo "================================================\n\n";

echo "Facebook Page: {$pageName}\n";
echo "Facebook Page ID: {$pageId}\n";
echo "Instagram Account: @{$instagramUsername}\n";
echo "Instagram Account ID: {$instagramAccountId}\n";
echo "Token Type: Page Access Token (never expires)\n\n";

// Save configuration
$envContent = "# Instagram Business Account Configuration\n";
$envContent .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
$envContent .= "FACEBOOK_PAGE_ID={$pageId}\n";
$envContent .= "FACEBOOK_PAGE_NAME=\"{$pageName}\"\n";
$envContent .= "INSTAGRAM_ACCOUNT_ID={$instagramAccountId}\n";
$envContent .= "INSTAGRAM_USERNAME=\"{$instagramUsername}\"\n";
$envContent .= "FACEBOOK_ACCESS_TOKEN={$pageToken}\n";
$envContent .= "FACEBOOK_APP_ID={$appId}\n";
$envContent .= "FACEBOOK_APP_SECRET={$appSecret}\n";

file_put_contents('.env.local', $envContent);
echo "✓ Configuration saved to .env.local\n\n";

// Update example.php
if (file_exists('example.php')) {
    $example = file_get_contents('example.php');
    $example = preg_replace('/\$facebookPageId = \'[^\']*\'/', "\$facebookPageId = '{$pageId}'", $example);
    $example = preg_replace('/\$igAccountId = \'[^\']*\'/', "\$igAccountId = '{$instagramAccountId}'", $example);
    $example = preg_replace('/\$accessToken = \'[^\']*\'/', "\$accessToken = '{$pageToken}'", $example);
    file_put_contents('example.php', $example);
    echo "✓ Updated example.php\n\n";
}

echo "===============================================\n";
echo "CREDENTIALS FOR YOUR RECORDS\n";
echo "===============================================\n\n";

echo "App ID:\n";
echo $appId . "\n\n";

echo "App Secret:\n";
echo $appSecret . "\n\n";

echo "Facebook Page ID:\n";
echo $pageId . "\n\n";

echo "Instagram Account ID:\n";
echo $instagramAccountId . "\n\n";

echo "Page Access Token (never expires!):\n";
echo $pageToken . "\n\n";

echo "===============================================\n";
echo "NEXT STEPS FOR YOUR APPLICATION\n";
echo "===============================================\n\n";

echo "1. Your images MUST be uploaded to a public URL first\n";
echo "   • Instagram requires publicly accessible images\n";
echo "   • Use AWS S3, Google Cloud Storage, or similar\n";
echo "   • See example.php for upload helper functions\n\n";

echo "2. Test your setup with: php example.php\n\n";

echo "3. IMPORTANT: Instagram does not support text-only posts\n";
echo "   • All posts must include media (photos)\n";
echo "   • Plan your content strategy accordingly\n\n";

echo "Done! 🎉\n";
echo "Your Instagram Business Account is ready for automated posting!\n";
