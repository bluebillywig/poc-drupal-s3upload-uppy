#!/bin/bash

echo "=== Drupal S3 Uppy Setup Script ==="
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Creating .env file from template..."
    cp .env.example .env
    echo "⚠️  Please edit .env file with your AWS credentials before continuing!"
    echo "Press Enter when ready to continue..."
    read
fi

# Start Docker containers
echo "Starting Docker containers..."
docker-compose up -d

echo "Waiting for MySQL to be ready..."
sleep 10

# Install Composer dependencies for the custom module
echo "Installing AWS SDK via Composer..."
docker-compose exec -T drupal bash -c "cd /var/www/html/modules/custom/s3_uppy && composer install"

echo ""
echo "=== Setup Complete! ==="
echo ""
echo "Next steps:"
echo "1. Visit http://localhost:8080 to install Drupal"
echo "2. Use these database credentials during installation:"
echo "   - Database: drupal"
echo "   - Username: drupal"
echo "   - Password: drupal"
echo "   - Host: mysql"
echo ""
echo "3. After installation, enable the module:"
echo "   docker-compose exec drupal drush en s3_uppy -y"
echo "   docker-compose exec drupal drush cr"
echo ""
echo "4. Grant permissions to users:"
echo "   Visit /admin/people/permissions and enable 'Upload videos to S3'"
echo ""
echo "5. Access the upload form at:"
echo "   http://localhost:8080/s3-video-upload"
echo ""
