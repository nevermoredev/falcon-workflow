#!/bin/sh
set -e

[ -f .env ] || cp .env.example .env
# artisan serve ÔÂÂ˜ËÚ˚‚‡ÂÚ .env, ÔÓ˝ÚÓÏÛ Ì‡ÒÚÓÈÍË ¡ƒ ËÁ docker-compose ÔÓÔËÒ˚‚‡ÂÏ ÚÛ‰‡
for key in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
    eval "value=\${$key:-}"
    [ -n "$value" ] && sed -i "s|^$key=.*|$key=$value|" .env
done
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan storage:link >/dev/null 2>&1 || true

# –ñ–¥—ë–º, –ø–æ–∫–∞ Postgres –Ω–∞—á–Ω—ë—Ç –ø—Ä–∏–Ω–∏–º–∞—Ç—å –ø–æ–¥–∫–ª—é—á–µ–Ω–∏—è
until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do echo "waiting for postgres..."; sleep 1; done

php artisan migrate --force
# –î–µ–º–æ-–¥–∞–Ω–Ω—ã–µ –∑–∞–ª–∏–≤–∞—é—Ç—Å—è —Ç–æ–ª—å–∫–æ –≤ –ø—É—Å—Ç—É—é –±–∞–∑—É
if [ "$(php artisan tinker --execute='echo App\Models\User::count();')" = "0" ]; then
    php artisan db:seed --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000
