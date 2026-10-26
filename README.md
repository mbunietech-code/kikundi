# Mjeshi: mfumo wa kikundi (Laravel + Beem USSD)

Mfumo wa wanachama, ada, akiba na michango ya ugonjwa/msiba, wenye menyu ya USSD kupitia Beem Africa.
Nyaraka kamili ziko kwenye `nyaraka/`.

## Kuanza (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # data ya majaribio tu (PIN 1234)
php artisan mjeshi:create-admin admin@example.com "System Admin"
php artisan serve
```

Fungua http://localhost:8000/login, ingia kama admin, kisha **Mipangilio** ubadilishe jina la mfumo.
Jina hilo linaonekana kwenye tovuti, menyu ya USSD na SMS.

## USSD (Beem)

1. Weka `BEEM_USSD_TOKEN` kwenye `.env` (neno refu la siri, mfano `openssl rand -hex 24`).
2. Kwenye Beem, weka callback URL:
   `https://<domain-yako>/integrations/beem/ussd/<BEEM_USSD_TOKEN>`
3. Jaribu kwa simulator ya Beem kwanza.

Beem haielezi saini (signature) kwenye callback ya USSD, kwa hiyo URL yenyewe ni ya siri.
Ukipata IP za Beem, ziweke kwenye `BEEM_USSD_ALLOWED_IPS` (zitenganishe kwa koma).

Menyu: 1 Ada, 2 Ugonjwa, 3 Msiba, 4 Akiba yangu, 5 Akiba ya kikundi, 6 Matangazo, 7 Changia rafiki, 0 Toka.
Mwanachama anatambuliwa kwa namba ya simu (2557XXXXXXXX) na PIN ya tarakimu 4:

```bash
php artisan mjeshi:set-pin 1 M001
```

Jaribu kwa curl:

```bash
curl -X POST http://localhost:8000/integrations/beem/ussd/$BEEM_USSD_TOKEN \
  -H 'Content-Type: application/json' \
  -d '{"command":"initiate","msisdn":"255700000001","operator":"vodacom","session_id":"t1","payload":{"request_id":0,"response":0}}'
```

## Malipo

Mpaka Beem Payments iidhinishwe, malipo kupitia USSD yanaunda **ombi linalosubiri** (pending) lenye kumbukumbu.
Mweka hazina akithibitisha pesa kwenye taarifa rasmi:

```bash
php artisan mjeshi:verify-payment MJXXXXXXXX <namba-ya-muamala>
```

Hapo tu ndipo salio linabadilika na SMS ya risiti inatumwa (`BEEM_SMS_DRIVER=beem` kutuma SMS halisi).

## Tests

```bash
php artisan test
```
