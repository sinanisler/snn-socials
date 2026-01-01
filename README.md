# SNN Socials - WordPress Social Media Publisher

Publish images and videos to X (Twitter), LinkedIn, Instagram, and YouTube directly from your WordPress dashboard.

## Features

- **Multi-Platform Publishing**: Share content to X (Twitter), LinkedIn, Instagram, and YouTube
- **Media Support**: Upload and publish images and videos
- **Auto Updates**: Get automatic plugin updates from GitHub
- **Easy Configuration**: Simple API credentials setup
- **Batch Publishing**: Publish to multiple platforms simultaneously

## Installation

### From GitHub Release (Recommended)

1. Download the latest `snn-socials.zip` from [Releases](../../releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

### From Source

1. Clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/sinanisler/snn-socials.git
   ```
2. Activate the plugin from WordPress Admin

## Configuration

### 1. API Credentials Setup

Navigate to **SNN Socials → Settings** in your WordPress admin and configure the API credentials for each platform:

#### X (Twitter)
1. Go to [X Developer Portal](https://developer.x.com/en/portal/dashboard)
2. Create a new app or select existing
3. Set app permissions to "Read and Write"
4. Generate API Keys and Access Tokens
5. Copy credentials to plugin settings

#### LinkedIn
1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Create a new app
3. Add products: "Share on LinkedIn" and "Sign In with LinkedIn"
4. Get Client ID and Secret from Auth tab
5. Use OAuth 2.0 flow to get access token

#### Instagram
1. Convert Instagram to Business account
2. Connect to a Facebook Page
3. Go to [Facebook Developers](https://developers.facebook.com/)
4. Create an app and add Instagram Graph API product
5. Get long-lived access token and Business Account ID

#### YouTube
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable YouTube Data API v3
4. Create OAuth 2.0 credentials
5. Use OAuth playground to get refresh token
6. Required scope: `https://www.googleapis.com/auth/youtube.upload`

### 2. Publishing Content

1. Go to **SNN Socials → Publish**
2. Write your post text/caption
3. (Optional) Select media (image or video)
4. Choose target platforms
5. Click "Publish Now"

## Auto-Update Setup

This plugin includes automatic update functionality from GitHub releases.

### How It Works

1. Plugin checks GitHub for new releases
2. When a new version is available, you'll see an update notification in WordPress admin
3. Update with one click like any other WordPress plugin

### For Plugin Developers

The GitHub repository is configured in [github-update.php](github-update.php). Update these lines if you fork the plugin:

```php
$github_repo_owner = 'sinanisler';  // Change to your GitHub username
$github_repo_name  = 'snn-socials'; // Change to your repo name
```

## Creating Releases

### Automatic Release (GitHub Actions)

This plugin uses GitHub Actions for automatic releases.

**To create a new release:**

1. Update the version number in `snn-socials.php` header comment
2. Commit with message containing "release":
   ```bash
   git commit -m "release: version 1.0.1"
   git push
   ```
3. GitHub Actions will automatically:
   - Create a ZIP file
   - Create a GitHub release
   - Attach the ZIP file

**Or use auto-bump:**

Just include "release" in your commit message and the workflow will auto-increment the patch version:

```bash
git commit -m "release: Added new feature"
git push
```

### Manual Release

```bash
# Create ZIP file
zip -r snn-socials.zip . -x "*.git*" "*.github*" "node_modules/*" ".DS_Store"

# Create GitHub release manually and upload ZIP
```

## Rate Limits

- **X (Twitter)**: 500 posts/month (Free tier)
- **LinkedIn**: No strict limit
- **Instagram**: 25 posts/day
- **YouTube**: 10,000 quota units/day

## Support

For issues, feature requests, or contributions, please visit:
- [GitHub Issues](../../issues)
- [Author Website](https://sinanisler.com)

## License

GPL v2 or later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

## Credits

Developed by [Sinan Isler](https://sinanisler.com)
