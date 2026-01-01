# SNN Socials - WordPress Social Media Publisher

Publish images and videos to X (Twitter), LinkedIn, Instagram, and YouTube directly from your WordPress dashboard.

## Features

- **Multi-Platform Publishing**: Share content to X (Twitter), LinkedIn, Instagram, and YouTube
- **Media Support**: Upload and publish images and videos
- **Auto Updates**: Get automatic plugin updates from GitHub
- **Easy Configuration**: Simple API credentials setup
- **Batch Publishing**: Publish to multiple platforms simultaneously


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
