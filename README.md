VIGOR GYM – Membership & Attendance System

1. Project Description

Vigor Gym is a web-based Membership and Attendance Management System designed to help manage gym members, membership information, and attendance records. It provides separate access for administrators and members through a login authentication system. The system includes account management, membership plan information, account status monitoring, and attendance-related management. It also features a purple-themed interface that reflects the Vigor Gym branding.

2. Technology Stack

- PHP– Handles server-side operations, login authentication, and session management.
- MySQL – Stores user accounts and membership-related information.
- MySQLi – Establishes database connectivity between PHP and MySQL.
- HTML5 – Structures the web pages.
- CSS3 – Handles the system's layout, styling, and visual design.
- Google Fonts (Poppins) – Provides the system typography.
- InfinityFree – Provides free web hosting for deploying the application online.

3. Deployment Steps

 Step 1: Create an InfinityFree Account
1. Visit the official InfinityFree website at https://www.infinityfree.com/.
2. Click the sign-up option to create a free account.
3. Enter the required registration information.
4. Verify your email address if required.
5. Log in to your InfinityFree account.

 Step 2: Create a Hosting Account
1. Access the InfinityFree Members Area.
2. Select the option to create a new hosting account.
3. Choose a free subdomain or connect your own domain.
4. Enter your preferred website name.
5. Complete the account creation process.
6. Wait until your hosting account is activated.
7. Open the hosting account's Control Panel.

 Step 3: Prepare the Project Files
1. Locate your Vigor Gym project folder on your computer.
2. Make sure all necessary PHP, CSS, JavaScript, image, and other project files are included.
3. Check that the `index.php` file is located in the main project directory.
4. Organize the files and folders according to their existing directory structure.
5. Prepare an SQL backup of your local database using phpMyAdmin:
   - Open your local phpMyAdmin.
   - Select the `vigor_gymm` database.
   - Click the Export tab.
   - Select the SQL format.
   - Click Export or Go to download the database file.

Step 4: Upload the Project Files to InfinityFree
1. Open your InfinityFree Control Panel.
2. Find and open the File Manager.
3. Navigate to the `htdocs` folder.
4. Remove unnecessary default hosting files if present.
5. Upload all the contents of your Vigor Gym project folder into `htdocs`.
6. Make sure the `index.php` file is directly inside the `htdocs` directory.
7. Preserve all existing subfolders and file locations to avoid broken file paths.
8. Wait until all files have finished uploading.

Alternatively, you can use FileZilla and the FTP credentials provided by InfinityFree to upload your project files.

 Step 5: Create a MySQL Database
1. Return to your InfinityFree Control Panel.
2. Locate and open MySQL Databases.
3. Enter a database name for your Vigor Gym system.
4. Click the option to create the database.
5. Take note of the following database credentials:
   - MySQL Hostname
   - Database Name
   - Database Username
   - Database Password

These credentials will be needed when connecting your PHP application to the online database.

Step 6: Import the Database
1. Open phpMyAdmin through your InfinityFree Control Panel.
2. Select the newly created Vigor Gym database.
3. Click the Import tab.
4. Click Choose File and select the SQL backup exported from your local database.
5. Make sure the format is set to SQL.
6. Click Import or Go.
7. Wait until the database import is completed.
8. Verify that the required tables and records are visible in phpMyAdmin.

Step 7: Configure the Database Connection
1. Open your Vigor Gym project files using Visual Studio Code.
2. Locate the PHP file responsible for connecting to the database. In the supplied project, this connection is currently in `index.php`.
3. Find the existing database connection:

```php
$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
```

4. Replace the local database credentials with the credentials provided by InfinityFree.

Example:

```php
$conn = mysqli_connect(
    "YOUR_MYSQL_HOSTNAME",
    "YOUR_DATABASE_USERNAME",
    "YOUR_DATABASE_PASSWORD",
    "YOUR_DATABASE_NAME"
);
```

5. Save the changes.
6. Upload the updated PHP file to InfinityFree, replacing the previous version.

Important: Do not use `localhost` or your local XAMPP database credentials for the online connection. Use the actual MySQL credentials from your InfinityFree Control Panel.

 Step 8: Access the Deployed Website
1. Return to your InfinityFree Members Area.
2. Locate your hosting account and copy its assigned domain or subdomain.
3. Open a new browser tab.
4. Enter your website address.
5. Press Enter to open the Vigor Gym login page.
6. Check whether the website loads correctly.

 Step 9: Test the System
1. Log in using an existing administrator account.
2. Verify that the Admin Dashboard opens successfully.
3. Log in using an existing member account.
4. Verify that the Member Dashboard opens successfully.
5. Test the login validation and failed-login attempt restrictions.
6. Check whether membership information and attendance records are properly displayed and saved.
7. Test the logout function.
8. Verify that the images, buttons, navigation links, and other system features work properly.

 Step 10: Finalize the Deployment
1. Check that all pages and system functionalities are working correctly.
2. Ensure that the website is accessible through its assigned domain.
3. Enable HTTPS using the available SSL option if supported for your domain.
4. Perform a final review of the deployed system.
5. Once everything is verified, the Vigor Gym Membership and Attendance System is ready for online access.VIGOR GYM – Membership & Attendance System

1. Project Description

Vigor Gym is a web-based Membership and Attendance Management System designed to help manage gym members, membership information, and attendance records. It provides separate access for administrators and members through a login authentication system. The system includes account management, membership plan information, account status monitoring, and attendance-related management. It also features a purple-themed interface that reflects the Vigor Gym branding.

2. Technology Stack

- PHP– Handles server-side operations, login authentication, and session management.
- MySQL – Stores user accounts and membership-related information.
- MySQLi – Establishes database connectivity between PHP and MySQL.
- HTML5 – Structures the web pages.
- CSS3 – Handles the system's layout, styling, and visual design.
- Google Fonts (Poppins) – Provides the system typography.
- InfinityFree – Provides free web hosting for deploying the application online.

3. Deployment Steps

 Step 1: Create an InfinityFree Account
1. Visit the official InfinityFree website at https://www.infinityfree.com/.
2. Click the sign-up option to create a free account.
3. Enter the required registration information.
4. Verify your email address if required.
5. Log in to your InfinityFree account.

 Step 2: Create a Hosting Account
1. Access the InfinityFree Members Area.
2. Select the option to create a new hosting account.
3. Choose a free subdomain or connect your own domain.
4. Enter your preferred website name.
5. Complete the account creation process.
6. Wait until your hosting account is activated.
7. Open the hosting account's Control Panel.

 Step 3: Prepare the Project Files
1. Locate your Vigor Gym project folder on your computer.
2. Make sure all necessary PHP, CSS, JavaScript, image, and other project files are included.
3. Check that the `index.php` file is located in the main project directory.
4. Organize the files and folders according to their existing directory structure.
5. Prepare an SQL backup of your local database using phpMyAdmin:
   - Open your local phpMyAdmin.
   - Select the `vigor_gymm` database.
   - Click the Export tab.
   - Select the SQL format.
   - Click Export or Go to download the database file.

Step 4: Upload the Project Files to InfinityFree
1. Open your InfinityFree Control Panel.
2. Find and open the File Manager.
3. Navigate to the `htdocs` folder.
4. Remove unnecessary default hosting files if present.
5. Upload all the contents of your Vigor Gym project folder into `htdocs`.
6. Make sure the `index.php` file is directly inside the `htdocs` directory.
7. Preserve all existing subfolders and file locations to avoid broken file paths.
8. Wait until all files have finished uploading.

Alternatively, you can use FileZilla and the FTP credentials provided by InfinityFree to upload your project files.

 Step 5: Create a MySQL Database
1. Return to your InfinityFree Control Panel.
2. Locate and open MySQL Databases.
3. Enter a database name for your Vigor Gym system.
4. Click the option to create the database.
5. Take note of the following database credentials:
   - MySQL Hostname
   - Database Name
   - Database Username
   - Database Password

These credentials will be needed when connecting your PHP application to the online database.

Step 6: Import the Database
1. Open phpMyAdmin through your InfinityFree Control Panel.
2. Select the newly created Vigor Gym database.
3. Click the Import tab.
4. Click Choose File and select the SQL backup exported from your local database.
5. Make sure the format is set to SQL.
6. Click Import or Go.
7. Wait until the database import is completed.
8. Verify that the required tables and records are visible in phpMyAdmin.

Step 7: Configure the Database Connection
1. Open your Vigor Gym project files using Visual Studio Code.
2. Locate the PHP file responsible for connecting to the database. In the supplied project, this connection is currently in `index.php`.
3. Find the existing database connection:

```php
$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
```

4. Replace the local database credentials with the credentials provided by InfinityFree.

Example:

```php
$conn = mysqli_connect(
    "YOUR_MYSQL_HOSTNAME",
    "YOUR_DATABASE_USERNAME",
    "YOUR_DATABASE_PASSWORD",
    "YOUR_DATABASE_NAME"
);
```

5. Save the changes.
6. Upload the updated PHP file to InfinityFree, replacing the previous version.

Important: Do not use `localhost` or your local XAMPP database credentials for the online connection. Use the actual MySQL credentials from your InfinityFree Control Panel.

 Step 8: Access the Deployed Website
1. Return to your InfinityFree Members Area.
2. Locate your hosting account and copy its assigned domain or subdomain.
3. Open a new browser tab.
4. Enter your website address.
5. Press Enter to open the Vigor Gym login page.
6. Check whether the website loads correctly.

 Step 9: Test the System
1. Log in using an existing administrator account.
2. Verify that the Admin Dashboard opens successfully.
3. Log in using an existing member account.
4. Verify that the Member Dashboard opens successfully.
5. Test the login validation and failed-login attempt restrictions.
6. Check whether membership information and attendance records are properly displayed and saved.
7. Test the logout function.
8. Verify that the images, buttons, navigation links, and other system features work properly.

 Step 10: Finalize the Deployment
1. Check that all pages and system functionalities are working correctly.
2. Ensure that the website is accessible through its assigned domain.
3. Enable HTTPS using the available SSL option if supported for your domain.
4. Perform a final review of the deployed system.
5. Once everything is verified, the Vigor Gym Membership and Attendance System is ready for online access.
