# Rock County Fitness Hub

Rock County Fitness Hub is a beginner-friendly fitness tracking web application built as a student capstone project. It gives users a clean and simple place to manage core fitness data in one dashboard.

Users can create an account, verify their email, log in securely, reset their password if needed, and track calorie goals, weight history, and strength progress over time.

## Features

### Public Features
- Home page
- About page
- Contact page
- BMI calculator
- User registration
- User login
- Email verification
- Forgot password / reset password flow

### Logged-In Features
- Personal dashboard
- Calorie tracking
- Weight tracking with dated history
- Strength tracking with dated history
- Profile editing
- Profile image upload
- Default avatar based on user initials

### Security and Auth Features
- Password hashing with `password_hash()`
- Secure login sessions
- Remember me option
- Email verification tokens
- Password reset tokens
- CSRF protection
- Server-side validation and error handling

## Tech Stack

- **Backend:** PHP
- **Architecture:** Simple MVC
- **Database:** MySQL
- **Database Access:** PDO with prepared statements
- **Frontend:** HTML, CSS, JavaScript
- **Charts:** Chart.js
- **Local Development:** XAMPP
- **Planned Deployment:** Hostinger

## Project Structure

```text
fitness_hub/
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── CalorieController.php
│   │   ├── ContactController.php
│   │   ├── DashboardController.php
│   │   ├── PagesController.php
│   │   ├── ProfileController.php
│   │   ├── StrengthController.php
│   │   └── WeightController.php
│   ├── core/
│   │   ├── Controller.php
│   │   └── helpers.php
│   ├── models/
│   │   ├── CalorieLog.php
│   │   ├── Contact.php
│   │   ├── PasswordReset.php
│   │   ├── StrengthLog.php
│   │   ├── User.php
│   │   └── WeightLog.php
│   └── views/
├── config/
│   ├── database.local.php
│   └── database.php
├── public/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   ├── auth.js
│   │   └── main.js
│   ├── uploads/
│   │   └── .gitkeep
│   ├── .htaccess
│   └── index.php
├── storage/
│   ├── .gitkeep
│   └── reset_links.log
├── .gitignore
├── .htaccess
└── README.md

Local Setup

These instructions are for someone cloning the repository and running the project on their own computer.

1. Install the required software

Make sure you have these installed:

XAMPP
Git
VS Code or another code editor
2. Clone the repository

Open a terminal and run:

git clone https://github.com/YOUR-USERNAME/fitness_hub.git

Then move into the project folder:

cd fitness_hub

If you downloaded the ZIP instead, extract it and place the folder in your XAMPP htdocs directory.

3. Move the project into htdocs

The project should live inside your XAMPP web root. On Windows that is usually:

C:\xampp\htdocs\fitness_hub
4. Start Apache and MySQL

Open the XAMPP Control Panel and start:

Apache
MySQL
5. Create the database

Open phpMyAdmin in your browser:

http://localhost/phpmyadmin

Create a new database named:

fitness_hub

Use this collation if available:

utf8mb4_unicode_ci
6. Import the database schema

Import the SQL file that comes with the project, or paste the project SQL into phpMyAdmin to create the required tables.

At minimum, the app expects the tables used for:

users
calorie logs
weight logs
strength logs
contacts
password reset and verification support if included in your schema
7. Configure your local database settings

Open:

config/database.local.php

Set the database values so they match your local XAMPP setup.

A common local XAMPP setup looks like this:

host: localhost
database: fitness_hub
username: root
password: empty by default unless you changed it
8. Make sure Apache rewrite is enabled

This project uses .htaccess and front-controller routing, so Apache rewrite needs to be enabled in XAMPP.

9. Run the app

Open this in your browser:

http://localhost/fitness_hub/
Local Password Reset Testing

On local XAMPP, PHP mail usually does not work out of the box. For development, password reset links are written to:

storage/reset_links.log

To test the reset flow locally:

Go to the Forgot Password page
Submit the email address
Open storage/reset_links.log
Copy the newest reset link
Paste it into your browser
Set a new password
Notes for Developers
config/database.local.php is meant for local machine credentials and should stay out of Git if it contains private settings.
The app uses a front controller through public/index.php.
Uploaded profile images are stored in the uploads folder.
Email features may use local development fallbacks instead of real SMTP delivery.
Future Improvements
Real SMTP email delivery for production
More advanced charts and analytics
Expanded workout tracking
Meal-by-meal nutrition logging
Additional profile customization
Author

Built as a web development capstone project by Ben Kreager.

License

Educational project for learning and portfolio use.