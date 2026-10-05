# Student Management System

A PHP and MySQL project for managing student records. I used PDO for database access, forms for data entry, and server-side validation.

## Features

- List and search student records.
- Add, edit and delete students.
- Validate names, email, phone, major, GPA and enrollment date format.
- Store records in MySQL with a unique email field.

## Technologies

PHP, MySQL, HTML and CSS.

## Run locally

1. Start Apache and MySQL using XAMPP or WAMP.
2. Place the repository in `htdocs` or `www`.
3. Create a database named `student_management`.
4. Review the connection settings in `adham22/db.php`. The configured host is `127.0.0.1` and port is `3307`; use your local MySQL port.
5. Open `adham22/index.php` through localhost. The application creates the students table inside the existing database.

## Files

All application files are in `adham22`: `db.php`, `index.php`, `search.php`, `add.php`, `edit.php` and `delete.php`.

## Project scope

This is a local database practice project. It has no user login or role management. Use sample records when trying it.

## Author

Adham Muayad Hashem
