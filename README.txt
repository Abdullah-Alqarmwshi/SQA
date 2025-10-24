# ClassConnect Installation Instructions

## Step 1: Fix MySQL Error

Your MySQL is showing "shutdown unexpectedly" error. This is usually due to port conflicts or corrupted data.

### Quick Fix:
1. Open XAMPP Control Panel
2. Click "Config" button next to MySQL
3. Select "my.ini"
4. Find this line (appears twice): port=3306
5. Change BOTH to: port=3307
6. Save the file
7. Start MySQL in XAMPP Control Panel

### If that doesn't work:
1. Stop all XAMPP services
2. Navigate to C:\xampp\mysql
3. Rename "data" folder to "data_old"
4. Copy the "backup" folder and rename the copy to "data"
5. Restart XAMPP and start MySQL

## Step 2: Access Setup Page

After MySQL starts successfully:
1. Make sure Apache is running in XAMPP
2. Open your browser
3. Go to: http://localhost/mywebsite/setup.php
4. This will create all database tables

## Step 3: Login

After setup completes:
- Go to: http://localhost/mywebsite/index.php
- Login as admin:
  Username: admin
  Password: admin

## System Features:
- Admin can register teachers
- Students can self-register
- Teachers and students can edit profiles
- Teachers can manage lessons and assignments
- Students can submit assignments
- Announcements system
- Profile management and account deletion

