# Blue Billywig OVP Integration

## Overview

This feature automatically registers uploads with the Blue Billywig OVP backend using TOTP authentication.

## Configuration

Update your `.env` file with:

```bash
# Blue Billywig OVP Configuration
BB_PUBLICATION=YOUR_PUBLICATION
OVP_API_SECRET=<numerical_id>-<secret>
```

For example:
```bash
BB_PUBLICATION=mycompany
OVP_API_SECRET=123-mysecretstring
```

**Notes**:
- `BB_PUBLICATION` is your Blue Billywig publication name. The system will automatically construct the API hostname as `{BB_PUBLICATION}.bbvms.com`
- The secret is provided as a plain string in the .env file. It will be automatically Base32 encoded internally before being used for TOTP generation.

## How It Works

1. **Page Load**: When the upload form loads, it automatically calls `/s3-uppy/generate-upload-identifier`
2. **GUID Generation**: A GUID is generated for use as `sourceid`
3. **MediaClip Creation**: `PUT /sapi/mediaclip` with `{"sourceid": guid}`
4. **Upload Identifier Retrieval**: `GET /sapi/awsupload?autoPublish=false&type=mediaclip&mediaclipId=<id>`
5. **Display**: The upload identifier, MediaClip ID, and GUID are displayed on the page
6. **Upload**: When a file is uploaded, the identifier is used as S3 metadata (`x-amz-meta-uploadidentifier`)

## TOTP Authentication

- Uses 120-second step/window
- Sends `rpctoken` header with format: `<id>-<otp>`
- Secret configured via `OVP_API_SECRET`

## Files Modified/Created

- `custom_module/src/Service/BlueBillywigOvpClient.php` - OVP API client with TOTP
- `custom_module/src/Controller/S3UploadController.php` - Added `generateUploadIdentifier()` method
- `custom_module/src/Form/S3VideoUploadForm.php` - Removed manual identifier field, added hidden field
- `custom_module/js/s3-uppy-upload.js` - Auto-fetch identifier on page load
- `custom_module/s3_uppy.routing.yml` - Added `/s3-uppy/generate-upload-identifier` route
- `custom_module/composer.json` - Added `spomky-labs/otphp` dependency

## Troubleshooting

If the `/s3-uppy/generate-upload-identifier` endpoint returns 404:

1. Log into Drupal admin (`/user/login`)
2. Go to Configuration > Development > Performance
3. Click "Clear all caches"
4. Alternatively, use Drush: `drush cr`

Or restart the Docker container:
```bash
cd ~/work/drupal-uppy
docker-compose down
docker-compose up -d
```

## Testing

1. Set valid OVP credentials in `.env`:
   ```bash
   BB_PUBLICATION=your_publication_name
   OVP_API_SECRET=123-yoursecretstring
   ```
2. Restart containers: `docker-compose restart`
3. Visit: http://localhost:8080/s3-video-upload
4. Select a video file - the system will automatically register with OVP at `{BB_PUBLICATION}.bbvms.com`
5. Upload proceeds with OVP-generated identifier

## API Endpoints

### Generate Upload Identifier
**POST** `/s3-uppy/generate-upload-identifier`

Response:
```json
{
  "uploadidentifier": "string",
  "mediaclipId": 12345,
  "guid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
}
```

### Generate Presigned URL
**POST** `/s3-uppy/generate-url`

Request:
```json
{
  "filename": "video.mp4",
  "filetype": "video/mp4",
  "uploadidentifier": "from-ovp"
}
```

## Branch

This feature is on branch: `feature/bluebillywig-ovp-integration`
