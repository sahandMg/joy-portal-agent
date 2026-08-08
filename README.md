# Joy Portal Agent

یک Agent مستقل Laravel برای خواندن آمار تفکیک‌شده کاربران از API محلی Xray روی
سرورهای Portal است.

نسخه فعلی عمداً **Read-only** است:

- هیچ Clientی به Xray اضافه یا از آن حذف نمی‌کند.
- هیچ حجمی از حساب کاربران کم نمی‌کند.
- هیچ داده‌ای به سرور مرکزی ارسال نمی‌کند.
- فقط Total و Delta هر `email` را در SQLite ثبت می‌کند.

## پیش‌نیاز

- PHP 8.0.2 یا جدیدتر
- Composer
- Xray با `StatsService`
- API محلی Xray که فقط روی `127.0.0.1` گوش دهد

API مدیریتی Xray را روی IP عمومی یا `0.0.0.0` باز نکنید.

## فعال‌کردن آمار کاربران Xray

در کانفیگ نهایی تولیدشده توسط Xray/x-ui این بخش‌ها باید وجود داشته باشند:

```json
{
  "stats": {},
  "policy": {
    "levels": {
      "0": {
        "statsUserUplink": true,
        "statsUserDownlink": true
      }
    }
  },
  "api": {
    "tag": "api",
    "services": ["StatsService"]
  }
}
```

API باید با inbound/routing متناسب با نسخه Xray روی loopback در دسترس باشد. مثال
آدرس این پروژه `127.0.0.1:10085` است. ابتدا مستقیماً روی Portal آزمایش کنید:

```bash
/usr/local/x-ui/bin/xray-linux-amd64 api statsquery \
  --server=127.0.0.1:10085 \
  -pattern 'user>>>' \
  -reset=false
```

اگر خروجی `user>>>EMAIL>>>traffic>>>uplink/downlink` ندارد، Agent هم چیزی برای
تفکیک‌کردن نخواهد داشت. در این حالت ابتدا باید تنظیمات Policy، email هر Client و
StatsService اصلاح شود.

## نصب Agent روی Portal

```bash
git clone YOUR_REPOSITORY_URL joy-portal-agent
cd joy-portal-agent
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

در `.env` مسیرهای واقعی را تنظیم کنید:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/opt/joy-portal-agent/database/database.sqlite
XRAY_BINARY=/usr/local/x-ui/bin/xray-linux-amd64
XRAY_API_ADDRESS=127.0.0.1:10085
XRAY_API_TIMEOUT=5
XRAY_STATS_PATTERN=user>>>
XRAY_RESET_AFTER_READ=false
XRAY_COLLECTION_ENABLED=false
```

سپس:

```bash
php artisan xray:stats:test
php artisan migrate --force
php artisan xray:usage:collect --include-zero
```

دستور `xray:stats:test` هیچ چیزی در دیتابیس نمی‌نویسد و اولین تست ارتباط با
StatsService است. تا زمانی که این دستور emailهای مستقل را نشان نداده، Migration
و Collector را مبنای محاسبه قرار ندهید.

اجرای اول فقط Baseline می‌سازد و Delta را صفر نگه می‌دارد. بعد از ایجاد ترافیک
آزمایشی، دستور را دوباره اجرا کنید:

```bash
php artisan xray:usage:collect
```

خروجی باید برای هر email مستقل شبیه این باشد:

```text
email             up total   down total   delta      state
ios:account-101   1.20 MB    21.50 MB     4.10 MB    ok
ios:account-102   0.80 MB    52.00 MB     12.30 MB   ok
```

## اجرای زمان‌بندی‌شده

فقط پس از موفقیت آزمایش دستی مقدار زیر را فعال کنید:

```dotenv
XRAY_COLLECTION_ENABLED=true
```

Cron لاراول:

```cron
* * * * * cd /opt/joy-portal-agent && php artisan schedule:run >> /dev/null 2>&1
```

## نکات ایمنی محاسبه

- `XRAY_RESET_AFTER_READ` در فاز آزمایش باید `false` بماند.
- اولین مشاهده مصرف قبلی Xray را به‌عنوان Baseline ذخیره می‌کند و آن را Delta
  حساب نمی‌کند.
- اگر شمارنده Xray به علت Restart صفر شود، Agent آن را تشخیص داده و Sample را با
  `counter_reset_detected=1` ذخیره می‌کند.
- جدول `xray_usage_samples` تاریخچه هر برداشت و جدول `xray_usage_snapshots`
  آخرین Total مشاهده‌شده را نگه می‌دارد.

## مرحله بعد

بعد از مقایسه مصرف دو UUID آزمایشی با فایل‌های دانلودشده، ارسال امن و idempotent
Deltaها به Laravel مرکزی اضافه می‌شود. تا قبل از تأیید اختلاف آماری، این Agent
نباید روی حجم واقعی مشتریان اثر بگذارد.
