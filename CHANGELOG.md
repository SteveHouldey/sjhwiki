# Changelog

## 1.0.1.2

- Added AJAX update progress modal with live status and percentage.
- Added full-width update status notifications.
- Removed dismiss controls from update status notifications.
- Improved update available, up-to-date, and update failure messaging.
- Kept test-email controls inside the Email settings tab.
- Fixed SMTP host, sender, username, port, and encryption settings persistence.
- Preserved an existing SMTP password when the password field is left blank.
- Automated database migration execution during updates.
- Backed up and restored `config/app_local.php` during updates.
- Added SQLite database backup and restore support for failed migrations.

## 1.0.1.1

- Added editable landing-page banner text and feature cards.
- Added an Admin Center rich-text editor for the About page.
- Added a compact mobile hamburger menu.
- Added a responsive account dropdown menu.
- Added a tabbed Admin Center with email test delivery.
- Added AJAX update progress modal and dismissible update notifications.

## 1.0.1

- Added a global Admin Center for site, account, registration, and email settings.
- Added dynamic site name, tagline, footer, and public URL settings.
- Added an admin-only application update checker using GitHub releases.
- Added release changelogs to the Updates page.
