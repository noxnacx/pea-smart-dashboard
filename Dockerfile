# ==========================================
# Stage 1: Build Frontend (Vue.js / Vite)
# ==========================================
FROM node:20-alpine AS frontend_build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

# ==========================================
# Stage 2: Build Backend (PHP / Laravel)
# ==========================================
FROM php:8.2-fpm

# ติดตั้ง System Dependencies และ Library ที่จำเป็น
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    supervisor \
    cron \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ติดตั้ง PHP Extensions สำหรับ Laravel, DOMPDF, Excel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# ติดตั้ง Redis Extension
RUN pecl install redis && docker-php-ext-enable redis

# ติดตั้ง Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ตั้งค่าโฟลเดอร์ทำงาน
WORKDIR /var/www/html

# ก๊อปปี้ไฟล์โปรเจกต์ทั้งหมดลง Container
COPY . .

# ก๊อปปี้ไฟล์ Vue ที่ Build เสร็จแล้วจาก Stage 1 มาใส่
COPY --from=frontend_build /app/public/build ./public/build

# รัน Composer เพื่อติดตั้ง Packages หลังบ้าน (โหมด Production)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# ตั้งค่าสิทธิ์การเข้าถึงโฟลเดอร์ที่ต้องใช้งาน (Storage / Cache)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
