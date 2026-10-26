# Google Tag Manager setup (evodrive.lv)

Agency (New Black) manages tags inside GTM. The site only loads the GTM container — no direct GA4 / Meta snippets.

## 1. Create the container

1. Open [Google Tag Manager](https://tagmanager.google.com/) with the company Google account.
2. Create a container for **evodrive.lv** (Web).
3. Copy the ID (`GTM-XXXXXXX`).

## 2. Put the ID on the server

In `/var/www/evodrive/.env`:

```env
GTM_CONTAINER_ID=GTM-XXXXXXX
```

Then:

```bash
cd /var/www/evodrive
php artisan config:clear
# or restart php-fpm / containers if config is cached in production
```

## 3. Invite the agency

In GTM → Admin → User Management → invite:

- **Email:** `digital@newblack.lv`
- **Role:** Publish (or Edit + Publish)

## 4. What the agency should configure in GTM

| Tag | Notes |
|-----|--------|
| GA4 Configuration | Measurement ID `G-NTWCKVPZ63` (previous direct ID) |
| Meta Pixel | Pixel ID `522495200131012` |
| TikTok / Google Ads | As needed |

**Conversion:** thank-you URL `/en/apply/thanks` (also `/lv/…`, `/ru/…`) **or** Custom Event `generate_lead` (pushed on that page).

## 5. getguru.business

Separate product (Symfony partner registration). Needs its own GTM container + UTM/conversion work in that codebase — not covered by this Laravel app.
