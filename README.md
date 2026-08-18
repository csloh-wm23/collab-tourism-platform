# JomCommunicate

JomCommunicate is a collaborative multilingual tourism platform. This version is intentionally written using the technologies taught in class: HTML5, CSS3, Bootstrap 5, vanilla JavaScript, PHP 8+, and MySQL/MariaDB.

There is no TypeScript, React, Next.js, Node.js build step, or npm dependency.

## Features

- text translation demo with English, Bahasa Melayu, Mandarin and Tamil examples
- browser speech recognition and text-to-speech
- local glossary and guided tourism scenarios
- emergency and dietary assistance
- business profile, phrases and visitor QR area
- saved phrases and privacy choices
- tourism insight and administration dashboards
- PHP JSON API with prepared SQL statements
- automatic localStorage fallback when MySQL is not connected
- responsive desktop and mobile layout

## Run with XAMPP

1. Install and open XAMPP.
2. Start **Apache** and **MySQL**.
3. Copy this project folder to `C:\xampp\htdocs\jomcommunicate` on Windows or `/Applications/XAMPP/htdocs/jomcommunicate` on macOS.
4. Open `http://localhost/phpmyadmin`.
5. Select **Import**, choose `database/jomcommunicate.sql`, then run the import.
6. Open `http://localhost/jomcommunicate/`.

XAMPP normally uses MySQL user `root` with a blank password, which is already the default in `config/database.php`. If your settings differ, set the environment variables shown in `.env.example` or change the local values in `config/database.php`. Never commit a real password.

## Project structure

```text
jomcommunicate/
├── api/records.php          # GET, POST and DELETE JSON endpoint
├── assets/css/styles.css    # responsive interface
├── assets/js/app.js         # navigation, translation and interactions
├── config/database.php      # PDO connection
├── database/jomcommunicate.sql
├── index.php                # main application page
└── README.md
```

## Database API

- `GET api/records.php` — list recent records
- `GET api/records.php?type=phrase` — filter by type
- `POST api/records.php` — save a JSON record
- `DELETE api/records.php?id=1` — delete a record

Allowed record types are `translation`, `phrase`, `report`, `business`, `emergency`, and `consent`.

## Translation service

The included translator is a small JavaScript demonstration dictionary, so the project works without an API key. For production, create a server-side PHP endpoint for Google Cloud Translation and keep its API key outside the repository in an environment variable. Do not put API keys in JavaScript.

## Browser support

The interface works in current Chrome, Edge, Firefox and Safari. Speech recognition support is best in Chrome or Edge. Text-to-speech has wider browser support.

## Team workflow

1. Create a branch: `git switch -c feature/short-name`
2. Make and test a small change.
3. Commit it: `git commit -m "Describe the change"`
4. Push the branch and open a pull request.

Do not commit database passwords, API keys, XAMPP logs, or personal visitor information.
