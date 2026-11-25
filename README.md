<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Committee contribution cards

Administrators can open **Communication → Contribution Cards** to:

1. Create a campaign for an event and upload a blank JPG, PNG, or WEBP card.
2. Configure the member-name position and typography.
3. Import an Excel/CSV file whose required headings are `name` and `phone`.
4. Generate personalized cards in the queue and preview them per recipient.
5. Send generated cards through the approved Meta template `contribution_card_sw`.

The Meta template must contain an image header and one body parameter (`{{1}}`) for the committee member's name. Ensure the existing `WHATSAPP_*` Cloud API variables and a public `APP_URL` are configured.

Run image rendering and delivery as separate production workers:

```bash
php artisan queue:work database --queue=card-generation --sleep=3 --tries=3 --timeout=120
php artisan queue:work database --queue=communications,default --sleep=3 --tries=3 --timeout=120
```

Both services must deploy the same application commit, share the same database, and use shared public storage (or the same object-storage disk). The communications worker also listens to `default` so existing legacy jobs continue to run.


## Deploying production web and worker services

The Docker web entrypoint now rebuilds Laravel configuration, routes, views,
Filament components and icon manifests **at container startup** in production.
This avoids losing optimization after Coolify replaces a container and avoids
baking stale environment secrets into the Docker image. Do not override the
image command in the web service unless the replacement also runs the entrypoint.

The optional packaged worker launcher uses **its own** Coolify environment
and refreshes its config cache before starting a queue worker:

```bash
/usr/local/bin/elive-worker-entrypoint
```

Coolify worker command above is available in Docker images built from this
repository. It defaults to the communications/default queues. If your worker
runs a **different image** or checkout at `/app`, install the same repository
version and launcher in that service first; updating the web image alone
does not update a separately deployed worker.

For a dedicated WhatsApp-sending worker, set `REQUIRE_WHATSAPP_READY=true`
to make the launcher refuse to process jobs if WhatsApp configuration is
disabled or incomplete. For shared SMS/WhatsApp workers, enable this guard
only when the service is intentionally required to send WhatsApp; it will
otherwise block SMS jobs as well when WhatsApp is disabled. Use an explicit
opt-in rather than silently turning WhatsApp on.

**After deployment, verify BOTH containers**:

```bash
php artisan whatsapp:check-config
php artisan about
```

Confirm the new command exists and the worker reports WhatsApp enabled. If
it does not, inspect the service's deployed image/commit and Coolify variables.
Existing failed WhatsApp claims stay failed and must not be bulk retried.

## WhatsApp readiness in Coolify

WhatsApp sending is deliberately **off** by default. Never change the application
default to true: each deployed service needs explicit opt-in and valid Meta credentials.

Set the following variables in the **application** and **communications queue worker**
Coolify services (or use verified shared environment configuration):

- `WHATSAPP_ENABLED=true`
- `WHATSAPP_DRIVER=meta_cloud_api`
- `WHATSAPP_ACCESS_TOKEN` (secret: do not print or commit)
- `WHATSAPP_PHONE_NUMBER_ID` (your Meta sender ID)

After saving, **redeploy or restart both services**. Configuration caches can
capture old values, and existing queue worker processes continue using their
configuration until restarted. Ensure the current container's cached config was
generated from the correct environment variables. Verify each running container:

```bash
php artisan whatsapp:check-config
```

The command reports effective Laravel configuration without displaying secrets,
does not contact Meta, and never sends messages. A successful check does not
prove template approval or message delivery.

If `whatsapp_template_sends` contains previous failures such as
"WhatsApp sending is disabled", **do not bulk retry those failures automatically**.
Reconcile `message_logs` and provider receipts, test with one authorized recipient,
then selectively retry confirmed failures through the existing admin workflow.

## Production message delivery and scheduling

Invitation SMS and WhatsApp jobs, and Message Center bulk reminder/thank-you SMS
jobs, must be processed by a **persistent queue worker**. `schedule:run` is a
separate service for scheduled reminders and event-completion activities; it
does **not** replace the queue worker.

Safe initial Coolify deployment (one communications worker, shared database):

```bash
php artisan queue:work database --queue=communications,default --sleep=1 --tries=3 --timeout=120
```

Run exactly one Laravel scheduler from Coolify or the host every minute:

```cron
* * * * * 
```

```bash
cd /var/www/html && php artisan schedule:run
```

Do not launch a second scheduler if another host/container is already running one.
Use `php artisan schedule:list` to inspect the registered schedule; this does
not establish whether it is running. Ensure Coolify restarts the worker on
failure and on deployment. Do **not** place `queue:work` in a one-shot task.

Database queue configuration: `QUEUE_CONNECTION=database` and
`DB_QUEUE_RETRY_AFTER=180` (must exceed `--timeout=120`).
If you change the environment, rebuild/reload cached configuration and
restart workers so the values take effect.

SMS and WhatsApp invitation jobs default to the existing `communications`
queue. To separate them, first start persistent workers for `sms` and
`whatsapp`, then set `SMS_QUEUE=sms` and `WHATSAPP_QUEUE=whatsapp`, and
restart application workers after configuration is refreshed. Card generation
continues on its existing `card-generation` queue. Keep the `communications`
worker alive for any other legacy jobs still routed there.

Suggested starting points after provider capacity checks:

```bash
php artisan queue:work database --queue=sms --sleep=1 --tries=3 --timeout=120
php artisan queue:work database --queue=whatsapp --sleep=1 --tries=3 --timeout=120
php artisan queue:work database --queue=communications,default --sleep=1 --tries=3 --timeout=120
```

Use **two** independently supervised SMS worker instances initially; scale
only after measuring provider response times and confirming provider limits.
Do not treat worker count as a provider-approved requests-per-second limit.

Operational verification (read-only):

```bash
php artisan tinker --execute='dump(DB::table("jobs")->selectRaw("queue, COUNT(*) as total")->groupBy("queue")->pluck("total", "queue")->toArray());'
php artisan queue:failed
php artisan schedule:list
```

**Live-send warning:** Enabling the scheduler can immediately process due
automatic thank-you SMS for completed events. Check active event settings and
recipient counts first. Queuing an SMS is not confirmation of delivery.
The queue only tracks job execution; SMS delivery reports are a separate
provider-dependent process. Provider timeouts are ambiguous, so queued manual
event SMS jobs intentionally do not auto-retry; review the SMS logs before
resending.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
