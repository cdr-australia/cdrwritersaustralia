H01 STACK, LAYOUT AND ENVIRONMENT v2 (file 2 of 8)

1. PURPOSE
An admin panel for a static, JSON-driven website. Editors change content in forms.
On Publish, the server re-renders the affected HTML pages and writes them into the
public web folder. Visitors only ever see static files.

2. DECISIONS (fixed)
    Framework        Laravel (current release), Blade views, Alpine.js
    Auth             Laravel Breeze (Blade). Two roles: admin, editor. 2FA in B5.
    Database         MySQL or MariaDB: users, drafts, revisions, audit log, redirects
    Content storage  JSON FILES are the live source of truth for all site content.
                     The database holds only drafts and revisions (copies).
    Renderer         mustache/mustache v3, using the SAME .html templates as the
                     existing Node builder in site/
    Public site      Static HTML written into the output folder (public_html in
                     production)
    Admin location   Subdomain (for example admin.example.com) whose folder is
                     cdrwa-app/public. Locally: php artisan serve.

3. ONE REPOSITORY
    cdrwa-app/                     Laravel project and the only Git repository
        app/ routes/ resources/ database/ config/ ...   Laravel
        site/                      the existing Node project, moved here
            build.js, package.json         Node static builder (dev tool and parity reference)
            src/templates/                 Mustache templates (layout, partials, sections)
            src/assets/                    css, js, icons, screenshots
            src/static/                    manifest, sw.js, offline.html, .htaccess source
            src/data/                      SEED content (starting JSON)
            registry/sections.json         section types and editable fields (created in B2)
            dist/                          Node build output (ignored by Git)
        handover/                  the owner's archive of these files
        site-out/                  local PHP builder output (ignored by Git)
    The node_modules folder inside site/ stays there. It is NOT merged with vendor/.
    The admin UI has its own node_modules at the repository root (Vite). Neither
    node_modules folder is deployed.

4. DESIGN AND CONTENT HAVE DIFFERENT OWNERS
    DESIGN   templates, assets, static, registry. Changed by the owner on a PC,
             stored in Git, deployed with git pull. The PHP builder reads them in
             place from site/.
    CONTENT  the JSON in the data folder. Changed by editors in the admin.
             It lives OUTSIDE Git in a persistent folder, so a deployment never
             overwrites it.
    site/src/data is only the SEED. Command: php artisan cdrwa:init-data copies the
    seed into CDRWA_DATA_PATH only when that folder is empty or missing. It never
    overwrites existing content and prints "nothing to do" when data exists.
    Local development mirrors production: local content lives in
    storage/app/cdrwa/data, initialised from the seed.

5. ENVIRONMENT KEYS (.env, local values; use forward slashes)
    CDRWA_SITE_PATH=C:/laragon/app/cdrwa-app/site
    CDRWA_TEMPLATE_PATH=C:/laragon/app/cdrwa-app/site/src/templates
    CDRWA_ASSETS_PATH=C:/laragon/app/cdrwa-app/site/src/assets
    CDRWA_STATIC_PATH=C:/laragon/app/cdrwa-app/site/src/static
    CDRWA_REGISTRY_PATH=C:/laragon/app/cdrwa-app/site/registry
    CDRWA_SEED_DATA_PATH=C:/laragon/app/cdrwa-app/site/src/data
    CDRWA_DATA_PATH=C:/laragon/app/cdrwa-app/storage/app/cdrwa/data
    CDRWA_BACKUP_PATH=C:/laragon/app/cdrwa-app/storage/app/cdrwa/backups
    CDRWA_OUTPUT_PATH=C:/laragon/app/cdrwa-app/site-out
    CDRWA_SITE_URL=https://example.com
    CDRWA_ADMIN_EMAIL=...        (used only by the seeder)
    CDRWA_ADMIN_PASSWORD=...     (used only by the seeder; never committed)
    Production (Hostinger): the design paths point into the deployed repository,
    for example /home/<account>/cdrwa-app/site/src/templates. CDRWA_DATA_PATH and
    CDRWA_BACKUP_PATH point to a folder outside the repository, for example
    /home/<account>/cdrwa-data/data. CDRWA_OUTPUT_PATH is /home/<account>/public_html.
    All paths are read through config/cdrwa.php, never with env() elsewhere.

6. OUTPUT FOLDER RULES
    The builder NEVER deletes or wipes the output folder. It only overwrites files it
    generated and records them in a generated.json file kept in CDRWA_DATA_PATH's
    parent folder. It never touches files it did not generate.
    A full rebuild also copies static/ and assets/ into the output.
    The .htaccess from static/ is copied once; a marked block for redirects is
    regenerated inside it (see H04).
    Every file write uses a temporary file and rename (atomic).

7. DEPLOYMENT (the owner's task; the junior only writes the checklist and script in B5)
    Release = git push on the owner's PC, then on the server, one script that runs:
        git pull
        composer install --no-dev --optimize-autoloader
        php artisan migrate --force
        php artisan config:cache
        php artisan cdrwa:build        (design may have changed: re-render the site)
    PHP version in hPanel must match local (8.5, or 8.4 if a package needs it).
    Admin UI assets are built on the owner's PC (npm run build at the repository
    root) because the server has no Node. Whether public/build is committed is
    decided in B5.

8. OUT OF SCOPE UNTIL TOLD
    Contact form, media library, e-commerce and pricing sections, multi-language,
    payments, API. The 12 section types in H02 are FROZEN until the owner says
    otherwise.

END OF H01. Reply only: RECEIVED H01