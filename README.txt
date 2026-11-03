SECURE LOGIN SYSTEM - PHP / XAMPP

FEATURES
- User registration with input validation
- Password hashing using password_hash()
- Password checking using password_verify()
- PDO prepared statements
- Session ID regeneration after successful login
- Session stores user_id and role (also username for display)
- Generic "Invalid username or password" authentication failure
- Logout destroys the session and expires the session cookie
- Bonus: account locks for 15 minutes after 5 failed password attempts
- CSRF tokens on registration and login forms
- Optional 30-minute inactivity timeout

INSTALLATION
1. Copy the secure_login_system folder into C:\xampp\htdocs\
2. Start Apache and MySQL in XAMPP.
3. Open http://localhost/phpmyadmin
4. Import database.sql.
5. If your MySQL username/password differs from XAMPP defaults, edit config/database.php.
6. Open http://localhost/secure_login_system/register.php
7. Register an account, then sign in at login.php.

FILES
- index.php                 Entry point
- register.php              Registration form and handler
- login.php                 Login handler
- dashboard.php             Protected page
- logout.php                Session logout
- config/database.php       PDO database connection
- includes/bootstrap.php    Session, helpers, CSRF and authentication
- assets/style.css          Page styling
- database.sql              Database and users table

NOTES
- New registrations always receive role "user". Do not accept a role from the registration form.
- To create an administrator, update a trusted account in phpMyAdmin:
  UPDATE users SET role = 'admin' WHERE username = 'your_username';
- Use HTTPS when deploying publicly.
- Never store plain-text passwords.
