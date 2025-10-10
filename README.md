# Drupal S3 Video Uploader with Uppy

A Drupal 10 application with direct S3 video uploads using Uppy on the client-side and server-side URL signing, integrated with Blue Billywig OVP for media management.

## Features

- Latest stable Drupal 10
- Docker-based development environment
- Direct S3 uploads with presigned URLs (up to 20GB)
- Uppy client-side file uploader with drag-and-drop
- Server-side URL signing for security
- **Blue Billywig OVP Integration** with TOTP authentication
- Automatic MediaClip registration on file selection
- Video file validation (MP4, MOV, AVI, WebM, OGG, MXF, MPG, MPEG, MKV)
- Optional title and description metadata
- Configurable S3 upload paths

## Prerequisites

- Docker and Docker Compose
- AWS S3 bucket and credentials
- Blue Billywig OVP account with API credentials (optional)

## Setup

1. **Clone and configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your AWS credentials and OVP settings (optional)
   ```

   Environment variables (optional - can also be configured via admin UI):
   ```bash
   # AWS S3 Configuration
   AWS_ACCESS_KEY_ID=your_access_key
   AWS_SECRET_ACCESS_KEY=your_secret_key
   AWS_S3_BUCKET=your-bucket-name
   AWS_S3_REGION=eu-west-1
   AWS_S3_UPLOAD_PREFIX=upload/YOUR_PUBLICATION/

   # Blue Billywig OVP Configuration
   BB_PUBLICATION=YOUR_PUBLICATION
   BB_API_SECRET=123-yoursecretstring
   ```

   **Note:** All settings can be configured through Drupal's admin interface at `/admin/config/media/s3-uppy` after installation. Environment variables are used as fallback values.

2. **Start the Docker stack:**
   ```bash
   docker-compose up -d
   ```

3. **Install Drupal:**
   - Visit http://localhost:8080
   - Follow the installation wizard
   - Use these database credentials:
     - Database: drupal
     - Username: drupal
     - Password: drupal
     - Host: mysql

4. **Enable the custom module:**
   ```bash
   docker-compose exec drupal drush en s3_uppy -y
   docker-compose exec drupal drush cr
   ```

5. **Configure settings:**
   - Visit http://localhost:8080/admin/config/media/s3-uppy
   - Enter your AWS S3 credentials
   - Enter your Blue Billywig OVP settings (optional)
   - Or rely on environment variables from `.env` file

6. **Access the upload form:**
   - Visit http://localhost:8080/s3-video-upload

## How It Works

### Upload Flow

1. User fills in optional title and description fields
2. User selects or drops a video file
3. System automatically registers upload with Blue Billywig OVP:
   - Generates GUID for `sourceid`
   - Creates MediaClip with metadata (filename, title, description)
   - Retrieves upload identifier
4. System generates S3 presigned URL with upload identifier as metadata
5. File uploads directly to S3 from browser
6. Upload identifier links S3 object to MediaClip in OVP

### Architecture

- **Drupal**: Form rendering, OVP integration, S3 presigned URL generation
- **Uppy**: Client-side file uploader with progress tracking and validation
- **Blue Billywig OVP**: Media management and metadata storage with TOTP auth
- **S3**: Direct upload destination using presigned URLs for security

## Blue Billywig OVP Integration

The system integrates with Blue Billywig OVP for automatic media management:

- **TOTP Authentication**: 120-second window, 10-digit codes
- **MediaClip Creation**: Automatic registration on file selection
- **Metadata Support**: Original filename, title, and description
- **Upload Tracking**: Identifier stored as S3 metadata (`x-amz-meta-uploadidentifier`)

For detailed OVP integration documentation, see [OVP_INTEGRATION.md](OVP_INTEGRATION.md).

## Configuration

### Admin UI (Recommended)

Configure all settings through the Drupal admin interface:
- Navigate to: **Configuration > Media > S3 Uppy Settings**
- Or visit: http://localhost:8080/admin/config/media/s3-uppy

The settings form includes:
- **AWS S3 Configuration**: Access key, secret key, bucket, region, upload prefix, endpoint
- **Blue Billywig OVP Configuration**: Publication name, API secret

### Environment Variables (Fallback)

If settings are not configured in the admin UI, the system will fall back to environment variables from the `.env` file. This allows for:
- Container-level configuration
- Different settings per environment (dev/staging/prod)
- Secure credential management

## Development

The custom module is located in `custom_module/` and is mounted into the Drupal container.

### Module Structure

```
custom_module/
├── src/
│   ├── Controller/
│   │   └── S3UploadController.php    # S3 presigned URLs & upload registration
│   ├── Form/
│   │   ├── S3VideoUploadForm.php     # Upload form with title/description
│   │   └── S3UppySettingsForm.php    # Admin configuration form
│   └── Service/
│       └── BlueBillywigOvpClient.php # OVP API client with TOTP
├── config/
│   └── schema/
│       └── s3_uppy.schema.yml        # Configuration schema
├── js/
│   └── s3-uppy-upload.js             # Uppy integration
├── css/
│   └── s3-uppy.css                   # Styling
├── s3_uppy.links.menu.yml            # Admin menu link
└── composer.json                      # Dependencies (AWS SDK, TOTP)
```

## Security

- All S3 uploads use presigned URLs generated server-side
- URLs expire after 15 minutes
- Only video file types are allowed (MP4, MOV, AVI, WebM, OGG, MXF, MPG, MPEG, MKV)
- Maximum file size: 20GB
- OVP authentication via TOTP with Base32-encoded secrets

## Troubleshooting

If the OVP integration endpoint returns 404:
1. Log into Drupal admin (`/user/login`)
2. Go to Configuration > Development > Performance
3. Click "Clear all caches"

Or restart the Docker container:
```bash
docker-compose restart drupal
```

## License

MIT
