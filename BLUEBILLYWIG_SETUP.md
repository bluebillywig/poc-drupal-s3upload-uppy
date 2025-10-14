# Blue Billywig OVP Integration Setup Guide

This document describes how to set up and use the Blue Billywig OVP (Online Video Platform) integration with Drupal, including video uploads and embed code rendering.

## Overview

The integration provides:
- Direct video upload to AWS S3 with automatic registration in Blue Billywig OVP
- Custom Drupal media type for Blue Billywig videos
- Automatic embed code fetching and rendering
- Drupal Media Library integration for easy content management

## Prerequisites

- Drupal 10.x
- Blue Billywig OVP account with API credentials
- AWS S3 bucket for video storage
- Required Drupal modules: Media, Media Library

## Configuration

### 1. Environment Variables

Set the following environment variables in your `.env` file or Docker configuration:

```bash
# Blue Billywig OVP Configuration
BB_PUBLICATION=your-publication-name    # Your BB publication name
BB_API_SECRET=123-yoursecret           # Format: <id>-<secret>
BB_PLAYOUT=default                      # Playout configuration name (optional)

# AWS S3 Configuration (for video uploads)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_S3_BUCKET=your-bucket-name
AWS_S3_REGION=us-east-1
AWS_S3_UPLOAD_PREFIX=upload/
```

### 2. Module Installation

The `s3_uppy` custom module provides the Blue Billywig integration. It includes:

- **Media Source Plugin**: `BlueBillywigVideo` - Defines the Blue Billywig video media type
- **Field Formatter**: `BlueBillywigEmbedFormatter` - Renders embed codes on the frontend
- **OVP Client Service**: `BlueBillywigOvpClient` - Handles API communication with Blue Billywig

Install the module:
```bash
drush en s3_uppy -y
drush cr
```

## Media Type Configuration

### Blue Billywig Video Media Type

The module automatically creates a `bluebillywig_video` media type with:

**Field Configuration:**
- **field_mediaclip_id** (string): Stores the Blue Billywig MediaClip ID
- Field type: String
- Required: Yes

**Display Configuration:**
- Uses the `bluebillywig_embed` formatter
- Fetches and renders embed code from Blue Billywig OVP API
- Supports configurable playout settings

### Media Type Settings

File: `custom_module/config/install/media.type.bluebillywig_video.yml`

```yaml
id: bluebillywig_video
label: 'Blue Billywig Video'
description: 'A video hosted on Blue Billywig OVP'
source: bluebillywig_video
source_configuration:
  source_field: field_mediaclip_id
```

## Adding Blue Billywig Videos to Content

### Step 1: Enable Media Library

1. Go to `/admin/modules`
2. Enable "Media Library" module
3. Clear cache

### Step 2: Add Media Field to Content Type

To add Blue Billywig videos to Articles (or any content type):

1. Navigate to `/admin/structure/types/manage/article/fields`
2. Click "Add field"
3. Select: **Reference** → **Media**
4. Configure the field:
   - Label: "Video" (or your preference)
   - Field name: e.g., `field_video`
   - Under "Reference type" → check **"Blue Billywig Video"**
5. Save field settings

### Step 3: Configure Form Display

1. Go to `/admin/structure/types/manage/article/form-display`
2. For your media field, set the widget to: **"Media library"**
3. Save

### Step 4: Configure View Display

**IMPORTANT**: This step is crucial for embed codes to render correctly.

1. Go to `/admin/structure/types/manage/article/display`
2. Find your Video/Media field
3. Set **FORMAT** to: **"Rendered entity"**
4. Click the ⚙️ gear icon and configure:
   - View mode: **Default**
5. Set **LABEL** to: **"Hidden"**
6. Save

## Embed Code Rendering

### How It Works

The `BlueBillywigEmbedFormatter` class handles embed code rendering:

1. **Retrieves MediaClip ID** from the media entity's `field_mediaclip_id`
2. **Fetches embed code** from Blue Billywig OVP API endpoint:
   ```
   GET https://{publication}.bbvms.com/sapi/embedcode/{mediaclip_id}/{playout}/javascript
   ```
3. **Renders JavaScript** using Drupal's `Markup::create()` to safely output the embed code

### Key Implementation Details

**File**: `custom_module/src/Plugin/Field/FieldFormatter/BlueBillywigEmbedFormatter.php`

The formatter uses:
```php
use Drupal\Core\Render\Markup;

$elements[$delta] = [
  '#markup' => Markup::create('<div class="bluebillywig-video">' . $embed_code . '</div>'),
];
```

**Why this approach?**
- Standard Twig `|raw` filter doesn't work for JavaScript in Drupal render arrays
- `Markup::create()` marks content as safe and trusted
- Allows JavaScript execution in the browser

### Authentication

The OVP Client uses TOTP (Time-based One-Time Password) authentication:

1. API secret is Base32 encoded
2. TOTP token generated with:
   - Period: 120 seconds
   - Digits: 10
3. Token format: `{api_id}-{otp}`

## Upload Workflow

### Video Upload Process

1. **User uploads video** via Uppy interface (handled by S3 Uppy upload form)
2. **Video stored in S3** with GUID as filename
3. **MediaClip created** in Blue Billywig OVP via API:
   ```php
   PUT https://{publication}.bbvms.com/sapi/mediaclip
   ```
4. **Upload identifier retrieved** for backend processing
5. **Drupal media entity created** with MediaClip ID
6. **Video transcoded** by Blue Billywig in the background
7. **Embed codes become available** once transcoding completes

### API Endpoints Used

The `BlueBillywigOvpClient` service provides:

```php
// Create MediaClip
public function createMediaClip($guid, $originalFilename, $title, $description)

// Get upload identifier
public function getUploadIdentifier($mediaclipId)

// Get embed code
public function getEmbedCode($mediaclipId, $playout = 'default')

// Complete workflow
public function registerUpload($guid, $originalFilename, $title, $description)
```

## Troubleshooting

### Embed Code Not Rendering

**Symptom**: Video field shows a link instead of the player

**Solution**:
1. Check that field formatter is set to "Rendered entity" (not "Label")
2. Verify media entity display mode is configured correctly
3. Clear Drupal cache: `drush cr`
4. Clear entity cache if needed: `TRUNCATE cache_entity;`

### Authentication Errors

**Symptom**: 401 Unauthorized or authentication failures

**Check**:
1. Verify `BB_API_SECRET` format is correct: `{id}-{secret}`
2. Confirm `BB_PUBLICATION` matches your OVP publication name
3. Check system clock is synchronized (TOTP is time-sensitive)

### Video Not Appearing

**Symptom**: MediaClip ID stored but video doesn't show

**Possible causes**:
1. Video still transcoding (check Blue Billywig dashboard)
2. Invalid MediaClip ID
3. Playout configuration mismatch
4. Network/API connectivity issues

**Debug**: Check Drupal logs at `/admin/reports/dblog`

### Cache Issues

If changes aren't appearing:

```bash
# Clear all caches
drush cr

# Or clear specific caches
drush sql-query "TRUNCATE cache_entity;"
drush sql-query "TRUNCATE cache_render;"
drush sql-query "TRUNCATE cache_data;"
```

## Architecture

### File Structure

```
custom_module/
├── config/install/
│   ├── media.type.bluebillywig_video.yml
│   ├── field.storage.media.field_mediaclip_id.yml
│   ├── field.field.media.bluebillywig_video.field_mediaclip_id.yml
│   └── core.entity_view_display.media.bluebillywig_video.default.yml
├── src/
│   ├── Plugin/
│   │   ├── Field/FieldFormatter/
│   │   │   └── BlueBillywigEmbedFormatter.php
│   │   └── media/Source/
│   │       └── BlueBillywigVideo.php
│   └── Service/
│       └── BlueBillywigOvpClient.php
└── s3_uppy.module
```

### Dependencies

**Composer packages** (included in module):
- `spomky-labs/otphp`: TOTP authentication
- `paragonie/constant_time_encoding`: Base32 encoding for secrets

**Drupal modules**:
- Media (core)
- Media Library (core) - for content editor UI
- Field (core)

## Security Considerations

1. **API Secrets**: Never commit API secrets to version control
2. **TOTP Authentication**: Ensures secure API communication
3. **Markup Safety**: `Markup::create()` used only for trusted embed codes from OVP
4. **Input Validation**: MediaClip IDs validated before API calls

## Testing

### Manual Testing Checklist

- [ ] Upload video via S3 Uppy interface
- [ ] Verify MediaClip created in Blue Billywig dashboard
- [ ] Create Drupal media entity with MediaClip ID
- [ ] Add media to article content
- [ ] View article - confirm embed code renders
- [ ] Verify video player loads and plays
- [ ] Check responsive behavior on mobile
- [ ] Test with multiple playout configurations

### Test Embed Code Fetch

Create a test script to verify API connectivity:

```php
<?php
$ovp_client = new \Drupal\s3_uppy\Service\BlueBillywigOvpClient();
$embed_code = $ovp_client->getEmbedCode('YOUR_MEDIACLIP_ID', 'default');
echo "Embed code length: " . strlen($embed_code) . " characters\n";
```

## Future Enhancements

Potential improvements:
- Thumbnail generation and display
- Video metadata sync (duration, dimensions)
- Bulk upload interface
- Advanced playout selector field
- Video analytics integration
- Automated testing suite

## Support

For issues or questions:
1. Check Drupal logs at `/admin/reports/dblog`
2. Review Blue Billywig API documentation
3. Verify configuration in `/admin/config/s3_uppy/settings`

## References

- [Blue Billywig OVP API Documentation](https://support.bluebillywig.com/)
- [Drupal Media System](https://www.drupal.org/docs/8/core/modules/media)
- [Drupal Field Formatters](https://www.drupal.org/docs/drupal-apis/entity-api/creating-a-custom-field-formatter)
