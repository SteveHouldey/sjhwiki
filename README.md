# Wiki

A CakePHP 5 wiki inspired by Wiki.js. It supports multiple public or private
wikis, a rich-text editor, protected image uploads, hierarchical navigation,
search, per-wiki custom roles, revision history and restore, and a shared Git
repository.

## Setup

### Browser setup for shared hosting

This installs a **new** wiki without shell access on the web server. It does not
upgrade an existing installation or database. Prepare the deployment on another
computer with PHP 8.2+, Composer and Node.js: run `composer install --no-dev`,
`npm ci`, and `npm run build`, then upload the application including `vendor`,
`config/Migrations`, `resources/highlight-languages.json`, and the built
`webroot` assets. Do **not** upload your
local `config/app_local.php`, databases, secrets, or `node_modules`.

1. Point the website's document root at the application's `webroot` directory,
   not the project root. Keep `config`, `resources`, databases, logs and other
   private files outside anything the web server can serve. Use HTTPS and
   configure URL rewriting to `webroot/index.php`. Behind a reverse proxy,
   configure the web server/PHP to recognize the original HTTPS connection.
2. Enable the required CakePHP PHP extensions, plus `fileinfo`, `dom`, `openssl`,
   and `pdo_mysql` or `pdo_sqlite`. Make `config`, `tmp`, `tmp/cache`,
   `tmp/cache/models`, `tmp/cache/persistent`, `logs` and `resources` writable
   by PHP, without making them publicly accessible or writable by other users.
   The installer reports missing requirements it can detect.
3. Using the hosting file manager, create `config/setup-token.txt` containing
   only a **unique, randomly generated 64-character lowercase hexadecimal
   secret** (32 random bytes). A password manager capable of generating random
   hexadecimal values can create one. Restrict its file permissions and never
   reuse or publish it. Do not copy the example test values from the code.
4. Visit `/setup` over HTTPS. Enter the secret, public URL, database settings,
   SMTP settings and first administrator details. MySQL/MariaDB requires an
   already-created **empty** database and a user with schema-creation rights.
   SQLite needs an absolute file path in an existing writable private directory.
   The installer refuses databases containing tables and never overwrites an
   existing `config/app_local.php`. It will not start if `DATABASE_URL` is set;
   this protects environment-configured installations.
5. Sign in, then use **Resend verification** for the administrator email.
   Confirm receipt before relying on password recovery. The installer does not
   send a test email automatically. It removes the setup token when successful;
   if it could not, remove the file with the hosting file manager. The generated
   `config/app_local.php` contains database and SMTP credentials and must remain
   private. Back it up securely with the database and uploaded media.

The public URL must match the host used for setup. HTTP is allowed only when
accessing localhost from the same machine, for local testing such as MailHog;
use HTTPS everywhere else. SMTP supports STARTTLS, implicit TLS and unencrypted
local testing. If setup fails after starting migrations, it may leave partial
tables; use a new empty database or have your host reset the dedicated setup
database before retrying. It never removes existing tables for you.

Git synchronization requires a Git executable, PHP process execution and a
preconfigured repository. It will not work on hosts that disable process
execution; wiki editing remains available and changes remain queued. Scheduled
CLI synchronization and upgrading an existing database also require host/CLI
support. File uploads additionally require the PHP and web-server limits
described below.

### Command-line setup

1. Install PHP 8.2 or newer with the CakePHP extensions, `fileinfo` and `dom`,
   Composer, Node.js, npm, Git, and a supported database. Run `composer install`,
   `npm ci`, and `npm run build`. Bootstrap 6 alpha and the Tiptap editor are
   bundled locally; the application does not depend on a CDN.
2. Configure the existing `default` datasource in `config/app_local.php` or
   with `DATABASE_URL`. Keep credentials out of version control.
3. Back up the database and run `bin/cake migrations migrate`, then run
   `bin/cake wiki_migrate_content`. For upgrades, the second command converts
   current Markdown pages to sanitized HTML and queues Git updates without
   changing historical Markdown revisions or discarding pending Git work.
   It is safe to rerun. If conversion fails (for example, because HTML exceeds
   the page size limit or a page was edited concurrently), the affected page
   remains Markdown and the command reports the page; resolve it and rerun.
4. Configure email and `APP_FULL_BASE_URL` as described below.
5. Set `WIKI_SETUP_TOKEN` in the web server's environment to a random secret of
   at least 32 characters. For local development:

   ```sh
   export WIKI_SETUP_TOKEN="$(php -r 'echo bin2hex(random_bytes(32));')"
   printf 'Setup token: %s\n' "$WIKI_SETUP_TOKEN"
   bin/cake server -H 127.0.0.1 -p 8765
   ```

6. Visit `http://127.0.0.1:8765/setup`, enter the token and create the first
   application administrator. Setup is disabled once an administrator is
   created. Remove the setup token from the server environment afterwards.

For production, serve the app over HTTPS, set `DEBUG=false` and
`SESSION_COOKIE_SECURE=true`, and configure `App.fullBaseUrl` for the public
HTTPS URL. Protect the setup token and Git credentials with your deployment's
secret management. Login throttling is built in; use additional web-server or
proxy rate limiting for Internet-facing deployments.

## Wikis and access

Visitors can register accounts and must verify their email before signing in.
An application administrator can also create accounts and wikis. A wiki is private
unless public reading is enabled. Administrators can create custom roles for
each wiki and assign existing users to those roles. A role can grant reading,
page creation, editing, deletion, history, restoring revisions, wiki settings,
and role management. Roles with any permissions must include read access.
Someone allowed to manage roles can grant any permission within that wiki.
Application administrators can access every wiki.

Registered users can read public wikis; a private wiki requires a role granting
read access. Registration grants no private-wiki roles or editing permissions.
Administrators can add an email address to existing or administrator-created
accounts. These accounts continue to work without verification, but need a
verified address to use password recovery. An email change invalidates existing
links and sessions; registered accounts must verify the new address before
signing in. Users can request another verification link from the sign-in page.

### Email and password recovery

For local testing with MailHog at `127.0.0.1:1025`, set these environment
variables for the CakePHP web process (not just a separate terminal):

```sh
export EMAIL_TRANSPORT_DEFAULT_URL='smtp://127.0.0.1:1025'
export APP_FULL_BASE_URL='http://127.0.0.1:8765'
export WIKI_MAIL_FROM='wiki@example.test'
```

MailHog's web interface normally runs at `http://127.0.0.1:8025`. Set the
base URL to the actual address users visit. For production, use HTTPS and a real
authenticated mail transport and sender; MailHog is only a test inbox. Configure
SMTP credentials securely, and protect the mail server and inboxes.
Links are sent only to verified addresses for password resets. Verification
links expire after 24 hours and reset links after one hour; requesting a new
link invalidates the previous one. Links are single-use, and changing a password
revokes existing sessions. Keep access logs and mailboxes private: emailed links
contain temporary secrets. Delivery failures are logged without the link, and
administrators should monitor the application logs. Reset and resend responses
do not reveal whether an address exists. Account emails include themed HTML and
a plain-text alternative.

Search only includes pages the current user can read and returns up to 100
matches. Deleted pages remain in history and can be restored by users with
history and restore permissions. Wiki slugs and page paths are immutable;
page paths use lowercase slash-separated segments. New content is sanitized
HTML, limited to 60,000 bytes per page. The editor supports text formatting,
headings, links, lists, tables, code, alignment and images. If JavaScript is
unavailable, the form accepts HTML directly and still sanitizes it. Legacy
Markdown revisions remain readable and can be restored as new HTML revisions.
Search matches the stored source as well as visible text.

Select a code block in the editor and use **Code language** to choose its
syntax highlighting. Page views and revision previews show line numbers, the
language (or an automatic detection label for unlabelled legacy blocks), and
a copy button. Copy requires a secure browser context (HTTPS or localhost) and
clipboard permission; otherwise select and copy the code manually. These
display controls are not stored in page content or Git exports.

The table toolbar creates tables with 1–20 rows and columns (up to 200 cells),
adds or removes rows and columns, toggles header rows, header columns and
semantic footer rows, and merges or splits cells. Drag column borders to resize
them. Select a body row when adding a row to a table with a footer; split
vertically merged cells before changing the footer, and do not merge cells
across its boundary.

Users with page creation or editing permission may upload JPEG, PNG, GIF or
WebP images up to 20 MiB and 100 megapixels. The server verifies the content,
not the filename or browser-provided type. Images are served only to users
who can read their wiki. They are stored outside the web root, by default in
`resources/wiki-media`; this directory must be writable by the web and Git
worker accounts and protected from other users. To use another existing
directory, set `WIKI_MEDIA_DIR` to its absolute path for both processes. All
instances sharing a database must use the same media storage. Back up this
directory alongside the database. Uploads are retained even if no page uses
them, because historical revisions can still refer to them.

Configure PHP's `upload_max_filesize` to at least `20M`, `post_max_size` to
more than `20M` (for example `21M`), and `memory_limit` to at least `256M`.
Also adjust any web-server or reverse-proxy request size limit. PHP or proxy
limits can otherwise reject an upload before the application receives it.

## Shared Git repository

Git synchronization is push-only: remote edits are not imported. **Private
wiki content and previous revisions are included**, so use a restricted,
private repository and restrict access to its credentials and local clone.

Create a dedicated bare clone outside the web root, configure its `origin`
and branch, and configure a Git committer identity for the web/worker account:

```sh
git clone --bare "REPOSITORY_URL" /srv/wiki-sync/wiki.git
git --git-dir=/srv/wiki-sync/wiki.git config user.name "Wiki"
git --git-dir=/srv/wiki-sync/wiki.git config user.email "wiki@example.invalid"
```

For an empty remote, ensure its desired branch is selected, for example:

```sh
git --git-dir=/srv/wiki-sync/wiki.git symbolic-ref HEAD refs/heads/main
```

Set `WIKI_GIT_DIR=/srv/wiki-sync/wiki.git` for both the web process and the
CLI worker. The service account needs read/write access to the bare clone and
noninteractive credentials for `origin`; do not embed credentials in the
repository URL. Restrict filesystem access to the clone. All application
instances sharing a database must use the same clone and filesystem lock.
Do not make independent changes to the clone or its remote branch.

Each saved revision is committed under `wikis/<wiki-slug>/<page-path>.md`.
HTML is embedded in the Markdown file to preserve rich formatting, although
some Git viewers restrict HTML and CSS. Images are committed under
`wikis/<wiki-slug>/_media/`, and local image references are rewritten to
relative paths in Git. Pages and images share one ordered queue. The database
save succeeds even if Git is unavailable; unsynchronized items and a safe
error message appear under **Git sync**
for application administrators. Retry there, or schedule:

```sh
bin/cake wiki_sync
```

The command processes up to 100 items and returns a nonzero exit status
if synchronization fails or more remain queued. If the remote branch has
advanced independently, pushes fail rather than force-pushing or importing
remote changes. An administrator must reconcile the repositories before
retrying; never force-push over history you intend to retain.

## Tests

Run `composer check` with an isolated test database configured via
`DATABASE_TEST_URL`. The test migrator may rebuild the test database; never
point it at the application database. Git tests use temporary local repositories
and do not push to the configured remote. Run `npm test` for the frontend tests.
