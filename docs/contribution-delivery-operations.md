# Contribution card delivery operations

## Queues

Contribution card generation uses the `card-generation` queue. WhatsApp contribution deliveries use `communications`. Welcome SMS may use a separate `messages` queue. Do not assume those queues are interchangeable.

For Laravel's database queue connection, run a managed worker with the deployed application image, the same environment and database settings, and access to the same persistent public storage. For example, a dedicated Coolify worker service using the same release/image as the production web app:

```sh
php artisan queue:work database --queue=communications,card-generation --sleep=3 --tries=3 --timeout=120
```

The worker command is **not** the HTTP service command. Configure an appropriate process supervisor/restart policy. Ensure the queue connection's `retry_after` exceeds the maximum job timeout and account for more than one worker if needed.

If workers run in different containers, verify both code revision and path. An exception mentioning `/app` when the web app runs from `/var/www/html` may point to another image or an old deployment, but historical failure paths alone do not prove which worker is currently running.

After updating code, redeploy all worker instances and issue `php artisan queue:restart`. The restart signal does not create a worker; verify actual worker processes/logs in Coolify.

## Safe WhatsApp test

1. Verify the campaign's approved template name/language.
2. For `contribution_plain`, the WhatsApp payload must have an image `header` and **no** `body` component.
3. Check the application's sanitized `Contribution WhatsApp template payload structure` log line. It records types/counts only; no phone number, image URL or token.
4. Send only one authorized test card and confirm a new Meta message ID. Then review the provider's delivery/read webhook.
5. Do not use `queue:retry all`, send-all, or run the `messages` queue as a diagnostic for contribution failures.

## Duplicate numbers vs duplicate sends

Multiple contribution recipients may intentionally share one phone number. Each has a separate record and personalized/generated card. Bulk sends exclude recipients already marked sent/submitted/delivered/read/replied. An explicit individual send is a separate action and may send the same number again; use it carefully.

A card regenerated after an accepted send should not reset the sending status to pending. To send an updated card, make an explicit individual send decision.

## Logging / security

Laravel may be configured with `LOG_CHANNEL=stderr`; use Coolify container logs when `storage/logs/laravel.log` does not exist. Do not log access tokens, request payloads containing phone numbers or image URLs, or share production secrets in screenshots.
