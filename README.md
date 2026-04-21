# Rock County Fitness Hub

A beginner-friendly fitness tracking web application. Track your calorie targets, weight history, and strength progression (bench / squat / deadlift) in one clean dashboard.

## Features

- Public BMI calculator (no signup needed)
- User registration, login, and logout
- Personal dashboard with:
  - Calorie calculator (maintenance / cutting / bulking) with saved history
  - Weight tracking with date-stamped history + chart
  - Strength tracking (bench, squat, deadlift) with history + chart
- Profile editing with image upload (CSS-generated initials avatar as default)
- Contact form (saves to database)

## Tech Stack

- **Backend:** PHP (simple MVC), MySQL, PDO prepared statements
- **Frontend:** HTML5, CSS3, JavaScript, Chart.js
- **Auth:** password_hash + PHP sessions
- **Local Dev:** XAMPP (Apache + MySQL)
- **Deployment:** Hostinger

## Folder Structure

```
fitness_hub/
├── app/
│   ├── controllers/    PHP classes that handle requests
│   ├── models/         PHP classes that talk to the database
│   └── views/          HTML templates rendered to the user
│       ├── auth/       login, register
│       ├── calorie/    calorie tracker pages
│       ├── dashboard/  logged-in home screen
│       ├── errors/     404 and error pages
│       ├── layouts/    shared header / footer
│       ├── pages/      home, about, contact
│       ├── profile/    profile editing
│       ├── strength/   strength tracker pages
│       └── weight/     weight tracker pages
├── config/
│   └── database.php    DB connection settings
├── public/             ← this is the webroot
│   ├── css/style.css   all styles
│   ├── js/main.js      all JavaScript
│   ├── uploads/        user-uploaded profile images
│   └── index.php       front controller (every request lands here)
├── .gitignore
├── .htaccess           redirects root traffic into /public
└── README.md
```

## Local Setup

1. Start Apache + MySQL in XAMPP
2. Open `http://localhost/phpmyadmin`
3. Create database `fitness_hub` (collation `utf8mb4_unicode_ci`)
4. Run the SQL from Phase 1 to create all tables
5. Visit `http://localhost/fitness_hub/` in your browser

## License

Educational capstone project.
