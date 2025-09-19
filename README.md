# PHP Instagram Business Account Post Library

A simple PHP library for posting to Instagram Business Accounts using the Instagram Graph API. This package allows you to post images and create carousel posts to your Instagram Business account.

## Requirements

- PHP >= 8.2
- cURL extension
- JSON extension
- Facebook App with Instagram permissions
- Instagram Business Account (linked to a Facebook Page)
- Long-lived Facebook Page Access Token (with Instagram permissions)

## Setup Requirements

### 1. Instagram Business Account
- You must have an Instagram Business Account
- It needs to be linked to a Facebook Page
- Go to [Instagram Business Account Setup](https://business.instagram.com/getting-started) to create/convert

### 2. Facebook App Permissions
Your access token needs these permissions:
- `pages_show_list`
- `pages_read_engagement`
- `instagram_basic`
- `instagram_content_publish`

## Installation

```bash
composer require codechap/i
composer require codechap/i @dev
```

## Quick Start

### 1. Setup

Run the setup script to configure your Facebook page and Instagram account:

```bash
php setup.php
```

This will guide you through:
- Getting an access token from Facebook
- Selecting your Facebook page
- Verifying your Instagram Business Account connection

### 2. Post to Instagram

```php
<?php

require 'vendor/autoload.php';

use Codechap\I\I;
use Codechap\I\Msg;

// Initialize Instagram client
$ig = new I();
$ig->set('facebookPageId', 'YOUR_FACEBOOK_PAGE_ID');
$ig->set('accessToken', 'YOUR_FACEBOOK_PAGE_ACCESS_TOKEN');

// Create a photo post
$msg = new Msg();
$msg->set('content', 'Hello Instagram!');
$msg->set('image', 'https://example.com/photo.jpg'); // Public URL required

$response = $ig->post($msg);
echo "Posted! ID: " . $response['id'];
```

## Examples

### Photo Post

```php
$msg = new Msg();
$msg->set('content', 'Check out this photo!');
$msg->set('image', 'https://example.com/photo.jpg');
$ig->post($msg);
```

### Carousel Post (Multiple Photos)

```php
$photo1 = new Msg();
$photo1->set('image', 'https://example.com/photo1.jpg');

$photo2 = new Msg();
$photo2->set('image', 'https://example.com/photo2.jpg');

$photo3 = new Msg();
$photo3->set('content', 'Photo gallery from my trip!');
$photo3->set('image', 'https://example.com/photo3.jpg');

$ig->post([$photo1, $photo2, $photo3]);
```

### Get Account Info

```php
$info = $ig->me();
echo "Instagram Username: @" . $info['data']['username'];
echo "Followers: " . $info['data']['followers_count'];
```

## Getting Access Token

### Method 1: Using Setup Script (Recommended)

```bash
php setup.php
```

Follow the prompts to get your token from Facebook Graph API Explorer.

### Method 2: Manual Setup

1. Go to [Facebook Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Select your app from the dropdown
3. Click "User or Page" → "Get User Token"
4. Add these permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `instagram_basic`
   - `instagram_content_publish`
5. Click "Generate Access Token"
6. Grant permissions and select your pages
7. Copy the token

### Extend Token Lifetime

To get a long-lived token (60+ days):

1. Go to [Access Token Debugger](https://developers.facebook.com/tools/debug/accesstoken/)
2. Paste your token
3. Click "Extend Access Token"
4. Use the extended token in your code

## Facebook App Credentials

This package is configured to work with an app:

- **App ID**: `YOUR_APP_ID_HERE`
- **App Secret**: `YOUR_APP_SECRET_HERE`

You can also use your own Facebook app by updating these values in `setup.php`.

## API Reference

### I Class

Main class for Instagram operations.

#### Methods

- `set(string $key, string $value)` - Set configuration (facebookPageId, igAccountId, accessToken, apiVersion)
- `post($content)` - Post content to Instagram (accepts Msg or array of Msg)
- `me()` - Get Instagram account information

#### Configuration Keys

- `facebookPageId`: Your Facebook Page ID (required if igAccountId not set)
- `igAccountId`: Your Instagram Business Account ID (optional, will be retrieved from Facebook Page)
- `accessToken`: Facebook Page Access Token with Instagram permissions
- `apiVersion`: Facebook Graph API version (default: v18.0)

### Msg Class

Message content handler.

#### Methods

- `set(string $key, string $value)` - Set content or image URL
- `get(string $key)` - Get content or image URL
- `hasContent()` - Check if message has text
- `hasImage()` - Check if message has an image URL

## Configuration

After running `setup.php`, your configuration is saved to `.env.local`:

```ini
FACEBOOK_PAGE_ID=YOUR_PAGE_ID
FACEBOOK_PAGE_NAME="Your Page Name"
INSTAGRAM_ACCOUNT_ID=YOUR_IG_ACCOUNT_ID
FACEBOOK_ACCESS_TOKEN=YOUR_TOKEN
FACEBOOK_APP_ID=YOUR_APP_ID
FACEBOOK_APP_SECRET=YOUR_APP_SECRET_HERE
```

## Image Requirements

Instagram requires images to be publicly accessible URLs (not local file paths). You must upload images to a cloud storage service first:

### Supported Cloud Storage Services

- AWS S3
- Google Cloud Storage
- Cloudinary
- Azure Blob Storage
- Or any public file hosting service

### Example: AWS S3 Integration

```php
function uploadToCloudStorage(string $localFilePath): string {
    $s3Client = new \Aws\S3\S3Client([
        'region' => 'us-east-1',
        'version' => 'latest',
        'credentials' => [
            'key' => 'YOUR_AWS_KEY',
            'secret' => 'YOUR_AWS_SECRET',
        ],
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
}

// Usage
$publicUrl = uploadToCloudStorage('local-photo.jpg');
$msg = new Msg();
$msg->set('image', $publicUrl);
```

### Image Specifications

- **Maximum size**: 10MB
- **Supported formats**: JPEG, PNG, WEBP
- **Minimum dimensions**: 200x200 pixels
- **Aspect ratios**:
  - Single posts: 4:5 to 1.91:1
  - Carousel posts: All images should have similar aspect ratios

## Error Handling

```php
try {
    $ig->post($msg);
} catch (\RuntimeException $e) {
    // Instagram API errors
    echo "API Error: " . $e->getMessage();
} catch (\Exception $e) {
    // Other errors
    echo "Error: " . $e->getMessage();
}
```

## Rate Limits

Instagram enforces rate limits on API calls. Be mindful of:
- Posting frequency (typically 1-2 posts per hour)
- Number of API calls per hour
- Carousel post limitations (up to 10 images per carousel)

## Limitations

- Instagram does not support text-only posts (media is required)
- All images must be publicly accessible URLs
- Carousel posts require at least 2 images
- Only Instagram Business Accounts are supported (not Personal Accounts)

## Security

- **Never commit access tokens** to version control
- Use environment variables or `.env.local` files
- Add `.env.local` to `.gitignore`
- Regenerate tokens periodically
- Keep app credentials secure

## Troubleshooting

### No Instagram Business Account found
- Make sure your Facebook Page has an Instagram Business Account linked
- Go to your Facebook Page Settings → Instagram → Connect Account
- The Instagram account must be a Business Account (not Personal)

### Permission errors
- Ensure your app has all required Instagram permissions
- Regenerate token with all permissions granted
- Check that your access token hasn't expired (extend it to 60 days)

### Token expired
- Tokens expire after 60 days
- Run `setup.php` again to get a new token
- Consider implementing automatic token refresh

### Media upload errors
- Ensure images are publicly accessible via HTTPS URLs
- Check image format and size (JPEG, PNG, WEBP; max 10MB)
- Verify aspect ratios meet Instagram requirements

### API rate limits
- Wait between API calls
- Monitor your API usage in Facebook Developer Console
- Implement proper error handling and retry logic

## License

MIT License - see LICENSE file for details.
