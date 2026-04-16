# Production Checklist

## 1. Environment

- Copy `.env.production.example` to `.env`
- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Fill `APP_URL` and `FRONTEND_URL`
- Configure database credentials
- Configure Gmail SMTP app password

## 2. Backend preparation

Run these commands in `backend`:

```powershell
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3. Queue worker

Emails now use the queue. Start a worker in production:

```powershell
php artisan queue:work --tries=3
```

Keep it supervised in production with a service manager.

## 4. Frontend build

Run in `frontend`:

```powershell
npm install
npm run build
```

Deploy the `dist` output to your web server.

## 5. Final manual checks

- Customer registration and login
- Forgot password email
- Add product in admin
- Place order as customer
- Confirm order in admin
- Move order to `En livraison`
- Mark order as `Livree`
- Verify customer and admin emails

## 6. Recommended always-on processes

- Web server / PHP runtime
- Database
- `php artisan queue:work`

## 7. Security reminders

- Never commit the real `.env`
- Rotate Gmail app passwords if they were exposed
- Use HTTPS in production
- Keep `APP_DEBUG=false`
