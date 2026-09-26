# MusiciansWorld Laravel platform

Laravel application root:

`C:\laragon\www\Backend\Custom PHP\musiciansworld-laravel`

The original custom-PHP project remains unchanged as a fallback.

## Run locally

```powershell
cd "C:\laragon\www\Backend\Custom PHP\musiciansworld-laravel"
php artisan migrate --force
php artisan db:seed --force
php artisan serve
```

Open `http://127.0.0.1:8000`.

Admin:

- URL: `http://127.0.0.1:8000/admin`
- Email: `admin@musiciansworld.com`
- Initial password: `Admin1234`

Change the initial password before production launch.

## Background automation

In another terminal run:

```powershell
php artisan queue:work
php artisan schedule:work
```

## Existing data

Phase 1 members are imported by the database seeder. Phase 1 JSON bookings can be re-imported idempotently:

```powershell
php artisan mwcn:import-bookings "C:\laragon\www\Backend\Custom PHP\musicansproject\data\bookings.json"
```

## Production configuration still required

- Set `APP_ENV=production`, `APP_DEBUG=false`, and the production `APP_URL`.
- Configure the production Mailtrap/SMTP stream instead of the sandbox if messages should reach real customers.
- Register both Stripe webhook URLs and replace the webhook signing secret with the production endpoint secret.
- Supply Microsoft Entra tenant/client credentials and the Studio A/SoundTunnel calendar mailbox IDs.
- Review `docs/phase-2-architecture.md`, then enable Stripe Connect onboarding and transfers through ERP settings.
- Point the web server document root to this application's `public` directory.

## Validation

```powershell
php artisan test
php artisan route:list
```
