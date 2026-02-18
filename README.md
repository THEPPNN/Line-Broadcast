# 📡 LINE Broadcast Webhook System (Laravel)

ระบบ Backend สำหรับรับ Webhook จาก LINE Messaging API
เพื่อบันทึกข้อความทั้งหมดในกลุ่ม พร้อมรองรับการตรวจจับ unsend message
และดาวน์โหลด media อัตโนมัติ

------------------------------------------------------------------------

## 🚀 Features

-   รับ Webhook จาก LINE
-   เก็บ Event Raw JSON ทุก event
-   บันทึกข้อความทุกประเภท (text, image, video, audio, file)
-   ดาวน์โหลดไฟล์ media อัตโนมัติ
-   บันทึกชื่อผู้ส่ง
-   รองรับส่งข้อความในกลุ่ม
-   ตรวจจับ Unsend message
-   Deploy บน Railway ได้

------------------------------------------------------------------------

## 🏗 Tech Stack

-   Laravel
-   MySQL
-   Railway
-   LINE Messaging API

------------------------------------------------------------------------

## 📂 Database Tables

### line_events

เก็บ webhook raw

### line_messages

เก็บข้อความ

### line_unsends

เก็บ log การยกเลิกข้อความ

### groups

เก็บข้อมูลกลุ่ม

------------------------------------------------------------------------

## ⚙️ Environment Variables (.env)

APP_URL=https://your-domain.up.railway.app

DB_CONNECTION=mysql\
DB_HOST=\
DB_PORT=3306\
DB_DATABASE=\
DB_USERNAME=\
DB_PASSWORD=

ADMIN_PASSWORD=

LINE_CHANNEL_TOKEN=\
LINE_CHANNEL_SECRET=

------------------------------------------------------------------------

## 🧪 Local Setup

    git clone repo
    cd project
    composer install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    php artisan serve

------------------------------------------------------------------------

## 🌍 Railway Deploy

1.  Push code ไป GitHub
2.  ไป Railway → New Project
3.  Deploy from GitHub
4.  เพิ่ม MySQL Plugin
5.  Copy DB credentials ใส่ Variables
6.  Generate Domain
7.  Deploy

------------------------------------------------------------------------

## 🔗 Webhook URL

    https://your-domain.up.railway.app/api/webhook/line

นำ URL นี้ไปใส่ใน LINE Developers Console

------------------------------------------------------------------------

## 🧠 Flow การทำงาน

LINE → Webhook → Laravel → Save DB → Download Media → Done

กรณี unsend

LINE → unsend event → update message → mark is_unsent

------------------------------------------------------------------------

## 📦 Storage

storage/app/public/line_media/

------------------------------------------------------------------------

## 🛠 Debug Logs

storage/logs/laravel.log

หรือดูผ่าน Railway Logs

------------------------------------------------------------------------

## 🔐 Security Notes

-   Verify signature ทุก webhook
-   ห้าม push .env
-   ใช้ HTTPS เท่านั้น

------------------------------------------------------------------------