# Architecture Documentation

## Overview

This application provides secure direct-to-S3 video uploads using Drupal 10 as the backend and Uppy as the client-side uploader. All URL signing happens server-side for maximum security.

## Components

### 1. Docker Stack
- **Drupal 10** (Apache + PHP)
- **MySQL 8.0** (Database)
- Persistent volumes for Drupal files and database

### 2. Custom Drupal Module (`s3_uppy`)

Located in `custom_module/`, this module provides:

#### PHP Backend
- **S3UploadController.php**
  - `generatePresignedUrl()`: Creates secure presigned S3 URLs
  - `uploadComplete()`: Handles post-upload callbacks
  - Validates file types (video only)
  - Uses AWS SDK for PHP

- **S3VideoUploadForm.php**
  - Renders upload form
  - Attaches Uppy library and JavaScript
  - Passes configuration to client

#### JavaScript Frontend
- **s3-uppy-upload.js**
  - Initializes Uppy dashboard
  - Requests presigned URLs from Drupal
  - Handles direct S3 uploads
  - Provides upload progress feedback
  - Notifies backend on completion

#### Styling
- **s3-uppy.css**: Custom styles for upload interface and status messages

## Upload Flow

```
1. User opens upload form
   ↓
2. Drupal renders form with Uppy dashboard
   ↓
3. User selects video file(s)
   ↓
4. Uppy requests presigned URL from Drupal
   POST /s3-uppy/generate-url
   { filename, filetype }
   ↓
5. Drupal validates file type
   ↓
6. Drupal generates presigned S3 URL (AWS SDK)
   - Valid for 15 minutes
   - Includes security credentials
   ↓
7. Drupal returns presigned URL to Uppy
   { url, key, bucket }
   ↓
8. Uppy uploads directly to S3
   PUT https://s3.amazonaws.com/...?credentials
   ↓
9. On success, Uppy notifies Drupal
   POST /s3-uppy/upload-complete
   { filename, key, size, type }
   ↓
10. Drupal logs upload and can trigger post-processing
```

## Security Features

### Server-side URL Signing
- AWS credentials never exposed to client
- Presigned URLs expire after 15 minutes
- Each upload gets unique credentials

### File Validation
- Server validates file types before signing
- Only video formats allowed (MP4, MOV, AVI, WebM, OGG)
- Client-side validation via Uppy restrictions
- File size limit: 2GB

### Drupal Permissions
- Custom permission: "Upload videos to S3"
- Role-based access control
- User authentication required

### S3 Security
- Files uploaded with private ACL
- Organized in date-based folders (YYYY/MM/DD)
- Unique filename generation (uniqid prefix)

## Configuration

### Environment Variables
```bash
AWS_ACCESS_KEY_ID       # AWS access key
AWS_SECRET_ACCESS_KEY   # AWS secret key
AWS_S3_BUCKET          # S3 bucket name
AWS_S3_REGION          # AWS region (default: us-east-1)
AWS_S3_ENDPOINT        # Optional: custom S3 endpoint
```

### Drupal Settings
JavaScript settings passed via `drupalSettings`:
```javascript
{
  generateUrlEndpoint: '/s3-uppy/generate-url',
  uploadCompleteEndpoint: '/s3-uppy/upload-complete',
  maxFileSize: 2147483648,  // 2GB in bytes
  allowedFileTypes: ['.mp4', '.mov', '.avi', '.webm', '.ogg']
}
```

## File Organization in S3

```
bucket/
  uploads/
    2025/
      01/
        15/
          abc123_video1.mp4
          def456_video2.mov
      01/
        16/
          ghi789_video3.mp4
```

## Extension Points

### Post-Upload Processing
In `S3UploadController::uploadComplete()`, you can:
- Create Drupal nodes/entities to track uploads
- Send email notifications
- Trigger transcoding jobs
- Update user quotas
- Generate thumbnails
- Index metadata

### Custom Validation
Extend `S3UploadController::generatePresignedUrl()` to:
- Add custom file type validation
- Implement file size quotas per user
- Check storage limits
- Validate file names

### UI Customization
Modify `S3VideoUploadForm::buildForm()` to:
- Add custom fields (title, description, tags)
- Change Uppy configuration
- Add additional upload restrictions
- Customize the interface

## Performance

### Benefits of Direct Upload
- No server bandwidth usage
- Scalable to unlimited concurrent uploads
- Fast uploads (direct to S3)
- No server storage needed
- Reduced server load

### Presigned URL Caching
Currently each upload requests a new URL. For optimization:
- Could batch generate URLs for multiple files
- Could reuse URLs within expiration window
- Could implement queue for large batches

## Monitoring

### Logs
Drupal logs available via:
```bash
docker-compose logs -f drupal
```

### S3 Access Logs
Enable S3 server access logging for audit trail

### Drupal Watchdog
Module logs to Drupal watchdog:
- Upload successes (info level)
- Upload errors (error level)

## Dependencies

### PHP (Drupal Container)
- drupal:10-apache (official image)
- aws/aws-sdk-php ^3.0 (via Composer)

### JavaScript (CDN)
- Uppy 3.24.0 (Core, Dashboard, AwsS3)

### Infrastructure
- Docker & Docker Compose
- MySQL 8.0
- AWS S3 bucket

## Development Workflow

1. Make changes to files in `custom_module/`
2. PHP changes: `docker-compose exec drupal drush cr`
3. JS/CSS changes: Hard refresh browser (Ctrl+Shift+R)
4. Commit to git
5. Test in production-like environment

## Production Considerations

- Use HTTPS in production
- Implement rate limiting
- Add file size quotas per user
- Enable S3 versioning
- Set up S3 lifecycle policies
- Implement virus scanning
- Add upload analytics
- Consider CDN for downloads
- Backup MySQL database
- Monitor costs (S3 storage and requests)
