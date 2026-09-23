# Daily Dish Restaurant — Website & Order System

## What's new (Sept 2026 redesign)

- Brand-new look across every public page, with the new professional food photos.
- Tap-a-dish ordering: dishes open in a sheet with a big photo, quantity and one-tap add; the order bar follows you down the page.
- Live menu search and sticky section tabs.
- Checkout with delivery areas and fees, pickup, ASAP or scheduled time, and cash / transfer-on-arrival / transfer-now payment.
- Order tracking page (`/track.php`) with a live status timeline; customers can also send their order to WhatsApp in one tap.
- Admin: new **Site Photos** page (homepage, about and gallery photos), delivery areas/fees and bank details in **Settings**, pending-order badge, WhatsApp-the-customer button on each order.
- Existing databases upgrade themselves automatically on first load; no orders or menu items are lost.

**Delivery fees are placeholders** (Gwarinpa ₦1,500 … Maitama ₦3,500). Set the real ones in Admin → Settings → Delivery & Payment.


A full website for Daily Dish Restaurant (Shop 4, RVS Mall, Third Avenue,
Gwarinpa, Abuja) — public site with online ordering, plus an admin panel
to manage the menu and incoming orders.

## What's inside

- **Public site**: Home, full Menu (organized into the same sections as
  your printed menu), About, Contact (with map), Cart, Checkout, Order
  Confirmation.
- **Ordering**: customers add items with a set price to their cart and
  check out with their name, phone and delivery/pickup details. Items
  without a listed price show "Ask for price" with a Call-to-Order
  button instead, since your printed menu doesn't price everything.
- **Admin panel** (`/admin`): dashboard with order stats, full order
  list + status tracking (pending → confirmed → preparing → out for
  delivery → delivered/cancelled), menu item management (add/edit/
  delete, set price, upload photo, toggle available/featured),
  category management, and a settings page for your business info
  (phone numbers, address, hours, WhatsApp, etc).
- Plain PHP + PDO, SQLite database (no separate database server to set
  up) — matches a standard cPanel shared-hosting setup. No frameworks,
  no build step.

## Getting it online (cPanel / shared hosting)

1. Zip is already built for upload. In cPanel → **File Manager**, go to
   `public_html` (or a subfolder if you want the site at a sub-path),
   click **Upload**, upload this zip, then **Extract** it there.
2. Make sure the `data/` folder is writable (cPanel usually sets this
   automatically; if the site errors on first load, set `data/` to
   permissions 755 or 775 via File Manager).
3. Visit your domain — the database and all your menu items are
   created automatically the first time the site is loaded.
4. Go to `yourdomain.com/admin/login.php` to reach the admin panel.

### Default admin login

- **Username:** `admin`
- **Password:** `DailyDish2026!`

**Please log in and change this password immediately** — go to
Admin → Settings → "Change Admin Password".

## Testing it on your own computer first (optional)

If you have PHP installed locally:

```
cd site
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## Things I filled in that you should double-check

- **Railway hosting**: the SQLite database and uploaded photos live on the container disk. Attach a Railway volume (mounted at `/var/www/html/data`) so orders survive redeploys.
- **Opening hours**: your signboard and menu didn't list hours, so I
  set a placeholder of "Mon – Sun: 8:00 AM – 9:00 PM" — update this
  in Admin → Settings.
- **Prices**: your printed menu only lists prices for the National
  Dish (soup + swallow) and House Special Rice sections (₦15,000
  each) plus a couple of items I added as "Chef's Specials" from your
  kitchen photos with estimated prices (Loaded Cheese Fries ₦6,500,
  Foil-Grilled Pepper Fish ₦9,500). Everything else is set to
  "Ask for price" so customers call to confirm — go into
  Admin → Menu Items and add a price to any dish to make it directly
  orderable online instead.
- **Payment**: checkout currently confirms orders by phone, with
  payment by cash or bank transfer on delivery/pickup — this matches
  how your two phone numbers are used on your signboard. If you'd
  like online card/transfer payment added later (e.g. via
  Flutterwave), that can be built in as a next step.
- **WhatsApp number**: set to `2348021333972` (your primary line) —
  update in Admin → Settings if you'd rather use a different number
  for WhatsApp orders.

## Switching the database to MySQL later

The site uses SQLite by default (zero setup, works everywhere). If you
outgrow it, everything runs through `get_db()` in
`includes/db.php` — swap that PDO connection for a MySQL DSN and the
rest of the code (plain PDO calls) will keep working with minimal
changes.

## Folder structure

```
site/
  index.php, menu.php, about.php, contact.php,
  cart.php, checkout.php, order-confirmation.php
  includes/        shared config, db, header/footer, logo
  assets/          css, js, images
  admin/           admin panel (protected by login)
  data/            SQLite database (auto-created, do not delete
                   unless you want to reset everything)
```

---
Built by Digital WebOracle ICT Services.
