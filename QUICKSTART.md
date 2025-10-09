# Quick Start Guide

## 1. Configure AWS Credentials

```bash
cp .env.example .env
# Edit .env with your AWS S3 credentials
```

Required environment variables:
- `AWS_ACCESS_KEY_ID` - Your AWS access key
- `AWS_SECRET_ACCESS_KEY` - Your AWS secret key
- `AWS_S3_BUCKET` - Your S3 bucket name
- `AWS_S3_REGION` - AWS region (default: us-east-1)

## 2. Run Installation Script

```bash
./install.sh
```

This will:
- Start Docker containers (Drupal + MySQL)
- Install AWS SDK via Composer

## 3. Install Drupal

Visit http://localhost:8080 and complete the installation:

**Database Configuration:**
- Database name: `drupal`
- Username: `drupal`
- Password: `drupal`
- Host: `mysql`

## 4. Enable the Module

```bash
docker-compose exec drupal drush en s3_uppy -y
docker-compose exec drupal drush cr
```

## 5. Set Permissions

1. Log in as admin
2. Go to `/admin/people/permissions`
3. Enable "Upload videos to S3" for desired roles

## 6. Upload Videos

Visit http://localhost:8080/s3-video-upload

## How It Works

1. **Client selects file** - User selects video file using Uppy dashboard
2. **Request presigned URL** - JavaScript requests presigned S3 URL from Drupal backend
3. **Server signs URL** - Drupal generates secure presigned URL (valid 15 min)
4. **Direct upload** - Uppy uploads file directly to S3 using presigned URL
5. **Completion callback** - JavaScript notifies Drupal of successful upload

## Troubleshooting

**Module not found:**
```bash
docker-compose restart drupal
docker-compose exec drupal drush cr
```

**Permission denied:**
- Check that you enabled "Upload videos to S3" permission
- Verify you're logged in

**Upload fails:**
- Check AWS credentials in `.env`
- Verify S3 bucket exists and is accessible
- Check browser console for errors

## Development

The custom module is located in `custom_module/` and is mounted into the Drupal container at `/var/www/html/modules/custom/s3_uppy`.

Changes to PHP files require cache clear:
```bash
docker-compose exec drupal drush cr
```

Changes to JS/CSS are immediately available (hard refresh browser).

## Useful Commands

```bash
# View logs
docker-compose logs -f drupal

# Access Drupal shell
docker-compose exec drupal bash

# Clear cache
docker-compose exec drupal drush cr

# Stop containers
docker-compose down

# Stop and remove volumes (clean start)
docker-compose down -v
```
