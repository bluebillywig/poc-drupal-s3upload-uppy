# How to Enable the S3 Uppy Module

After installing Drupal, you need to enable the custom S3 Uppy module. There are two ways to do this:

## Method 1: Using Drush (Command Line)

If you rebuilt the containers with Drush support:

```bash
# Enable the module
docker-compose exec drupal drush en s3_uppy -y

# Clear cache
docker-compose exec drupal drush cr
```

## Method 2: Using Drupal UI (Recommended if Drush not available)

1. Log in to Drupal as admin
2. Navigate to **Extend** page: `/admin/modules`
3. Find **S3 Uppy Video Uploader** in the modules list (under "Custom" package)
4. Check the checkbox next to it
5. Scroll down and click **Install** button
6. Click **Continue** to confirm

## After Enabling

### Set Permissions

1. Go to **People > Permissions**: `/admin/people/permissions`
2. Find the permission: **Upload videos to S3**
3. Enable it for the roles that should be able to upload videos
4. Click **Save permissions**

### Test the Upload Form

1. Visit: `/s3-video-upload`
2. You should see the Uppy upload interface
3. Try uploading a video file

## Troubleshooting

### Module doesn't appear in the list

1. **Check if the module folder exists:**
   ```bash
   docker-compose exec drupal ls -la /var/www/html/modules/custom/s3_uppy
   ```

2. **Verify file permissions:**
   ```bash
   docker-compose exec drupal chown -R www-data:www-data /var/www/html/modules/custom/s3_uppy
   ```

3. **Clear cache via UI:**
   - Go to `/admin/config/development/performance`
   - Click "Clear all caches"

### "Composer dependencies not installed" error

Install the AWS SDK:
```bash
docker-compose exec drupal bash -c "cd /var/www/html/modules/custom/s3_uppy && composer install"
```

### Drush command not found

If you see "drush: command not found", you need to rebuild the containers with the updated Dockerfile:

```bash
docker-compose down
docker-compose build
docker-compose up -d
```

Then wait 15 seconds for MySQL to initialize and try again.

### Alternative: Use PHP to enable module

If Drush is unavailable, you can enable it via PHP:

```bash
docker-compose exec drupal php -r "require_once 'core/includes/bootstrap.inc'; \
  \$module_installer = \Drupal::service('module_installer'); \
  \$module_installer->install(['s3_uppy']); \
  drupal_flush_all_caches();"
```
