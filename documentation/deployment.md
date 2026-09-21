# Deploying Stock Report Application to Hostinger
 
This guide covers deploying your Laravel 12 + React 18 application to Hostinger.
 
## Prerequisites
 
- Hostinger VPS or Cloud Hosting account (required for Laravel)
- Domain name configured in Hostinger
- SSH access enabled
- Basic knowledge of Linux commands
 
> **Note**: Shared hosting won't work for this Laravel application. You need VPS, Cloud Hosting, or Business hosting with SSH access.
 
---
 
## Step 1: Choose the Right Hostinger Plan
 
### Recommended Plans
1. **VPS Hosting** (Recommended) - Full control, best for Laravel
2. **Cloud Hosting** - Good alternative with managed features
3. **Business Web Hosting** - May work if SSH access is available
 
### Minimum Requirements
- PHP 8.2 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Node.js 18+
- SSH access
- At least 2GB RAM
 
---
 
## Step 2: Initial Server Setup
 
### 2.1 Connect to Your Server via SSH
 
```bash
ssh root@your-server-ip
# Or if you have a non-root user:
ssh username@your-server-ip
```
 
### 2.2 Update System Packages
 
```bash
sudo apt update && sudo apt upgrade -y
```
 
### 2.3 Install Required Software
 
#### Install PHP 8.2 and Extensions
 
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
 
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
  php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
  php8.2-gd php8.2-sqlite3 php8.2-bcmath php8.2-intl
```
 
#### Install Composer
 
```bash
cd ~
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```
 
#### Install Node.js 18+ and npm
 
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
node --version
npm --version
```
 
#### Install MySQL/MariaDB
 
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```
 
#### Install Nginx
 
```bash
sudo apt install -y nginx
```
 
---
 
## Step 3: Setup MySQL Database
 
```bash
# Login to MySQL
sudo mysql -u root -p
 
# Create database and user
CREATE DATABASE stock_report;
CREATE USER 'stock_report_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON stock_report.* TO 'stock_report_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
 
---
 
## Step 4: Deploy Your Application
 
### 4.1 Upload Your Code
 
**Option A: Using Git (Recommended)**
 
```bash
# Navigate to web directory
cd /var/www
 
# Clone your repository
sudo git clone https://github.com/yourusername/stock-report.git
cd stock-report
 
# Set proper ownership
sudo chown -R www-data:www-data /var/www/stock-report
sudo chmod -R 775 /var/www/stock-report/storage
sudo chmod -R 775 /var/www/stock-report/bootstrap/cache
```
 
**Option B: Using FTP/SFTP**
- Use FileZilla or similar SFTP client
- Upload all files to `/var/www/stock-report`
- Ensure you upload `.env.example` and all hidden files
 
### 4.2 Install Dependencies
 
```bash
cd /var/www/stock-report
 
# Install PHP dependencies
composer install --optimize-autoloader --no-dev
 
# Install Node.js dependencies
npm install
```
 
### 4.3 Configure Environment
 
```bash
# Copy environment file
cp .env.example .env
 
# Edit the .env file
nano .env
```
 
Update the following values in `.env`:
 
```env
APP_NAME="Stock Report"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com
 
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stock_report
DB_USERNAME=stock_report_user
DB_PASSWORD=your_secure_password
 
SESSION_DRIVER=database
QUEUE_CONNECTION=database
 
# If using mail functionality
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```
 
### 4.4 Generate Application Key
 
```bash
php artisan key:generate
```
 
### 4.5 Run Database Migrations
 
```bash
php artisan migrate --force
```
 
### 4.6 Build Frontend Assets
 
```bash
# Build production assets
npm run build
 
# The compiled assets will be in public/build/
```
 
### 4.7 Optimize Laravel
 
```bash
# Cache configuration
php artisan config:cache
 
# Cache routes
php artisan route:cache
 
# Cache views
php artisan view:cache
 
# Optimize autoloader
composer dump-autoload --optimize
```
 
### 4.8 Set Proper Permissions
 
```bash
sudo chown -R www-data:www-data /var/www/stock-report
sudo chmod -R 755 /var/www/stock-report
sudo chmod -R 775 /var/www/stock-report/storage
sudo chmod -R 775 /var/www/stock-report/bootstrap/cache
```
 
---
 
## Step 5: Configure Nginx
 
### 5.1 Create Nginx Configuration
 
```bash
sudo nano /etc/nginx/sites-available/stock-report
```
 
Add the following configuration:
 
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/stock-report/public;
 
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
 
    index index.php;
 
    charset utf-8;
 
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
 
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
 
    error_page 404 /index.php;
 
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
 
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
 
### 5.2 Enable the Site
 
```bash
# Create symbolic link
sudo ln -s /etc/nginx/sites-available/stock-report /etc/nginx/sites-enabled/
 
# Test Nginx configuration
sudo nginx -t
 
# Restart Nginx
sudo systemctl restart nginx
```
 
---
 
## Step 6: Setup SSL Certificate (HTTPS)
 
### Install Certbot
 
```bash
sudo apt install -y certbot python3-certbot-nginx
```
 
### Obtain SSL Certificate
 
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```
 
Follow the prompts to:
- Enter your email
- Agree to terms
- Choose to redirect HTTP to HTTPS (recommended)
 
### Auto-renewal Test
 
```bash
sudo certbot renew --dry-run
```
 
---
 
## Step 7: Setup Process Management (Optional but Recommended)
 
If your application uses queues, setup a process manager:
 
### Install Supervisor
 
```bash
sudo apt install -y supervisor
```
 
### Configure Queue Worker
 
```bash
sudo nano /etc/supervisor/conf.d/stock-report-worker.conf
```
 
Add:
 
```ini
[program:stock-report-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/stock-report/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/stock-report/storage/logs/worker.log
stopwaitsecs=3600
```
 
### Start Supervisor
 
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start stock-report-worker:*
```
 
---
 
## Step 8: Setup Scheduled Tasks (Cron)
 
```bash
sudo crontab -e -u www-data
```
 
Add this line:
 
```cron
* * * * * cd /var/www/stock-report && php artisan schedule:run >> /dev/null 2>&1
```
 
---
 
## Step 9: Verify Deployment
 
### Check Application Status
 
1. Visit `https://yourdomain.com`
2. Test the landing page
3. Try accessing `/stock-report`
4. Login as manager at `/dashboard`
 
### Check Logs
 
```bash
# Laravel logs
tail -f /var/www/stock-report/storage/logs/laravel.log
 
# Nginx access logs
tail -f /var/log/nginx/access.log
 
# Nginx error logs
tail -f /var/log/nginx/error.log
```
 
---
 
## Updating Your Application
 
When you push updates to your code:
 
```bash
cd /var/www/stock-report
 
# Pull latest code
git pull origin main
 
# Install/update dependencies
composer install --optimize-autoloader --no-dev
npm install
 
# Rebuild frontend
npm run build
 
# Run migrations (if any)
php artisan migrate --force
 
# Clear and rebuild cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
 
php artisan config:cache
php artisan route:cache
php artisan view:cache
 
# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
 
# Restart queue workers (if using supervisor)
sudo supervisorctl restart stock-report-worker:*
```
 
---
 
## Troubleshooting
 
### Issue: 500 Internal Server Error
 
**Check:**
1. Laravel logs: `storage/logs/laravel.log`
2. Nginx error logs: `/var/log/nginx/error.log`
3. PHP-FPM logs: `/var/log/php8.2-fpm.log`
4. Permissions on `storage/` and `bootstrap/cache/`
 
```bash
sudo chmod -R 775 /var/www/stock-report/storage
sudo chmod -R 775 /var/www/stock-report/bootstrap/cache
sudo chown -R www-data:www-data /var/www/stock-report
```
 
### Issue: Blank Page or CSS Not Loading
 
**Check:**
1. Run `npm run build` to ensure assets are compiled
2. Verify `APP_URL` in `.env` matches your domain
3. Check if `public/build/manifest.json` exists
4. Clear browser cache
 
```bash
php artisan view:clear
php artisan config:clear
npm run build
```
 
### Issue: Database Connection Error
 
**Check:**
1. MySQL is running: `sudo systemctl status mysql`
2. Database credentials in `.env` are correct
3. Database exists: `mysql -u root -p -e "SHOW DATABASES;"`
4. User has proper permissions
 
### Issue: Permission Denied Errors
 
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/stock-report
 
# Fix permissions
sudo find /var/www/stock-report -type f -exec chmod 644 {} \;
sudo find /var/www/stock-report -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/stock-report/storage
sudo chmod -R 775 /var/www/stock-report/bootstrap/cache
```
 
### Issue: Changes Not Reflecting
 
```bash
# Clear all caches
php artisan optimize:clear
 
# Or individually:
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
 
# Rebuild
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
 
---
 
## Security Checklist
 
- [ ] `APP_DEBUG=false` in production `.env`
- [ ] Strong `APP_KEY` generated
- [ ] Secure database passwords
- [ ] SSL certificate installed (HTTPS)
- [ ] Firewall configured (UFW)
- [ ] SSH key-based authentication enabled
- [ ] Regular backups scheduled
- [ ] `storage/` and `bootstrap/cache/` writable
- [ ] `.env` file not accessible via web
- [ ] Composer dependencies up to date
 
---
 
## Performance Optimization
 
### Enable OPcache
 
```bash
sudo nano /etc/php/8.2/fpm/php.ini
```
 
Add/update:
 
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```
 
Restart PHP-FPM:
 
```bash
sudo systemctl restart php8.2-fpm
```
 
### Enable Gzip Compression in Nginx
 
Add to your nginx config:
 
```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;
```
 
---
 
## Backup Strategy
 
### Database Backup Script
 
Create `/root/backup-db.sh`:
 
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/root/backups"
mkdir -p $BACKUP_DIR
 
mysqldump -u stock_report_user -p'your_secure_password' stock_report | gzip > $BACKUP_DIR/stock_report_$DATE.sql.gz
 
# Keep only last 7 days
find $BACKUP_DIR -name "stock_report_*.sql.gz" -mtime +7 -delete
```
 
Make executable and schedule:
 
```bash
chmod +x /root/backup-db.sh
crontab -e
# Add: 0 2 * * * /root/backup-db.sh
```
 
---
 
## Additional Resources
 
- [Laravel Deployment Documentation](https://laravel.com/docs/11.x/deployment)
- [Hostinger VPS Tutorials](https://www.hostinger.com/tutorials/vps)
- [Nginx Documentation](https://nginx.org/en/docs/)
 
---
 
## Support
 
If you encounter issues:
1. Check the logs in `storage/logs/laravel.log`
2. Review Nginx error logs: `/var/log/nginx/error.log`
3. Consult Hostinger support for server-specific issues
4. Check Laravel documentation for framework-related issues
 
---
 
**Deployment Checklist:**
 
- [ ] Server provisioned with PHP 8.2+, MySQL, Nginx
- [ ] Database created and credentials configured
- [ ] Code uploaded/cloned to `/var/www/stock-report`
- [ ] Composer dependencies installed
- [ ] NPM dependencies installed
- [ ] `.env` file configured with production settings
- [ ] Application key generated
- [ ] Database migrated
- [ ] Frontend assets built (`npm run build`)
- [ ] Laravel optimized (config, route, view cache)
- [ ] Permissions set correctly
- [ ] Nginx configured and enabled
- [ ] SSL certificate installed
- [ ] Application accessible and working
- [ ] Queues configured (if needed)
- [ ] Cron jobs scheduled
- [ ] Backups scheduled