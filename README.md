# Drupal S3 Video Uploader with Uppy

A Drupal 10 application with direct S3 video uploads using Uppy on the client-side and server-side URL signing.

## Features

- Latest stable Drupal 10
- Docker-based development environment
- Direct S3 uploads with presigned URLs
- Uppy client-side file uploader
- Server-side URL signing for security
- Video file validation

## Prerequisites

- Docker and Docker Compose
- AWS S3 bucket and credentials

## Setup

1. **Clone and configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your AWS credentials
   ```

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

5. **Access the upload form:**
   - Visit http://localhost:8080/s3-video-upload

## Architecture

- **Drupal**: Handles authentication, form rendering, and S3 presigned URL generation
- **Uppy**: Client-side file uploader with progress tracking and validation
- **S3**: Direct upload destination using presigned URLs for security

## Development

The custom module is located in `custom_module/` and is mounted into the Drupal container.

## Security

- All S3 uploads use presigned URLs generated server-side
- URLs expire after 15 minutes
- Only video file types are allowed
- File size limits can be configured

## License

MIT
