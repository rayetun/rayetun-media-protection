=== RayEtun Media Protection - Watermark and File Access Control ===
Contributors:      rayetun
Donate link:       https://wise.com/pay/me/mdrayhanu2
Tags:              watermark, pdf watermark, image protection, download protection, content protection
Requires at least: 6.7
Tested up to:      7.1
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Watermark images and PDFs, protect downloadable files, and control who can access them — in one plugin, on standard WordPress APIs.

== Description ==

🛡️ **RayEtun Media Protection is a media and document security suite for WordPress.**

RayEtun Media Protection combines image watermarking, PDF watermarking, encrypted file delivery, expiring download links, role-based access control, editor blocks for protected galleries and downloads, and e-commerce integrations in a single plugin. Every feature listed below is included and works out of the box.

Use it to watermark product photos so they stay branded when shared, stamp PDFs with each buyer's name and order number for traceable delivery, gate downloadable files behind login or role rules, and make download links expire after a set time or click count.

It is built the modern WordPress way — on the REST API, the block editor, `@wordpress/components`, and standard WordPress hooks. There is no jQuery on visitor pages and no external framework runtime.

= How RayEtun Media Protection is organized =

* **Clearly labeled** — every feature description says what it does and what it does not do. Deterrent scripts are labeled as deterrents in the settings, next to a note that no browser-side script can prevent a determined user from copying media.
* **Local first** — files, watermark rules, download logs, and settings live in your own WordPress database and file system.
* **Built on WordPress APIs** — the REST API, the block editor, `@wordpress/components`, and standard hooks. No jQuery on visitor pages, no framework runtime.
* **Accessible** — every admin screen is designed to WCAG 2.2 Level AA: keyboard operable, screen-reader labeled, focus managed, and reduced-motion aware.

---

= 🖼️ Image Watermarking =

* 🎨 **Text or image watermarks** applied on upload, in bulk, or on demand from the media library.
* 🧭 **Nine-position anchoring** with pixel or percentage offsets — put the mark exactly where you want it.
* 🌗 **Opacity and rotation** controls; separate settings for portrait and landscape sources.
* 🔁 **Non-destructive** — originals are backed up before watermarking, so you can restore or re-watermark at any time.
* 🖼️ **Preserves EXIF and IPTC metadata** — camera data, copyright fields, and captions survive the watermark pass.
* ⚡ **Dual engines** — Imagick when the server has it, GD as a fallback.
* 📐 **Small-image threshold** — automatically skip files under a size you set (thumbnails, icons).
* 🌐 **JPEG, PNG, and WebP** all supported.

= 📄 PDF Watermarking =

* 📝 **Text or image watermarks** on every page of any PDF you upload or serve as a download.
* 🧑 **Dynamic per-user watermarks** — insert the buyer's email, IP, user ID, order number, download timestamp, or the site domain into the watermark itself. This makes each downloaded copy uniquely identifiable to the person who downloaded it.
* 🎯 **Per-file rules** — different watermark settings for different products, folders, or categories.
* 🔐 **Password protection** — set an open-password on the watermarked PDF, generated per download or shared.

= 🔒 Protected File Storage =

* 🚪 **Files stored outside the webroot** when the server allows it, with an `.htaccess` fallback that blocks direct URL access on shared hosts.
* 🎫 **Signed serve URLs** — the only way to reach a protected file is through a validated request with a signed token.
* 👥 **Role and user gating** — restrict a file to logged-in users, specific roles, or specific accounts.
* ⏱️ **Expiring links** — expire by time (minutes to days) or by click count (single-use or fixed number of downloads).
* 🚫 **Hotlink prevention** — reject requests whose referrer does not belong to your site.
* 🤖 **Auto-protect on upload** — every new file uploaded to the media library can be protected automatically by an active rule.

= 🛒 E-Commerce Integrations =

Works with the three most common WordPress download-selling plugins, out of the box:

* 🛒 **WooCommerce** — watermark and protect downloadable products; each buyer receives a PDF stamped with their name and order.
* 💾 **Easy Digital Downloads** — the same treatment for EDD downloads, using the customer's email and receipt.
* 📦 **Download Monitor** — protected serving and per-download watermarking for Download Monitor libraries.

= 🧱 Gutenberg Blocks =

Three editor blocks bring watermarking and access control straight into the block editor — no shortcodes to remember:

* 🖼️ **Protected Gallery** — show a photo grid with clean thumbnails. Clicking a photo opens a lightbox with the watermarked preview, and an authorized viewer downloads the clean original from a single button. Right-click is disabled on the images, and anyone who grabs the preview through a browser extension only ends up with the watermarked copy. Optional filter chips let visitors narrow the grid by tag.
* ⬇️ **Protected Download** — a single download card for a file of any type, with a matching file icon, an access rule, and a sign-in button for logged-out visitors. Two layout templates: a horizontal row card or a centered tile.
* 📚 **Protected File Library** — a grid of protected downloads with a live filter box, for delivering a set of files — a resource pack, a press kit, a set of lesson materials — behind one access rule.

Every block includes an accent-color picker, handles any file type (images, PDFs, audio, video, archives, documents) with a fitting icon, and offers a **who can download** choice of Everyone, logged-in users, or a named access rule. You can set defaults for each block once under **RayEtun Media Protection → Blocks** and reuse them everywhere.

= 🖱️ Deterrent Layer =

An optional set of client-side deterrents you can enable per rule set. They are labeled in the settings for exactly what they are:

* 🚫 **Right-click block** — a casual deterrent. Does not prevent copying by a determined user.
* 🎯 **Drag-and-drop disable** — a casual deterrent that prevents accidental image drags.
* 🔧 **DevTools open detection** — a cosmetic deterrent that will not stop anyone who disables JavaScript.

Deterrents are off by default and can be enabled per rule set.

= 📊 Analytics =

* 📉 **Download attempts and blocks** — see which files are requested most often, and which access rules fire.
* 🔥 **Attempt heatmap** — spot unusual bursts of traffic on a single file.
* 🔍 **Per-file activity** — every access decision is logged with the event, timestamp, and a hashed client fingerprint.
* 🔐 **Privacy-respecting** — IPs and user-agent strings are stored as salted hashes, never in the clear. Log entries older than 90 days are pruned automatically; the retention window is configurable.

= 🌐 Multisite =

Full network compatibility. Enable per site, or activate network-wide and manage from the network admin.

---

= 🧭 What RayEtun Media Protection Will Not Do =

Worth stating plainly, so you know what to expect:

* ❌ **No "unbreakable" claims** — no browser-side plugin can prevent a determined user from copying media. RayEtun Media Protection prevents casual copying, direct-URL access, unauthorized downloads, and unmarked resharing. The settings page states this alongside the deterrent controls.
* ❌ **No overlay** — RayEtun Media Protection changes markup and file bytes, not visitor UI.
* ❌ **No data collection** — no telemetry, no phone-home, no account. Everything is stored in your own WordPress database and file system.
* ❌ **No breaking auto-updates** — database schema migrations are versioned and idempotent.

---

= Compatible With =

* WooCommerce, Easy Digital Downloads, Download Monitor (built-in integrations)
* Block themes and classic themes
* Multisite networks
* Any hosting environment supporting the plugin minimums (PHP 8.0 or later, WordPress 6.7 or later); Imagick used when present, GD as a fallback

== Installation ==

= From your dashboard =

1. Go to **Plugins → Add New** and search for **RayEtun Media Protection**.
2. Click **Install Now**, then **Activate**.

= Manual upload =

1. Download the plugin ZIP file.
2. Go to **Plugins → Add New → Upload Plugin**, choose the ZIP, and click **Install Now**.
3. Activate the plugin.

= First steps after activation =

1. Visit the **RayEtun Media Protection** menu in wp-admin. The Dashboard walks you through creating your first watermark rule.
2. To protect a specific file, open **Media Library**, select any attachment, and use **Protect with RayEtun Media Protection** in the row actions.
3. To protect all new uploads automatically, turn on **Auto-protect** in **RayEtun Media Protection → Settings**.
4. If you sell downloads through WooCommerce, Easy Digital Downloads, or Download Monitor, the corresponding integration activates as soon as RayEtun Media Protection sees that plugin is active.

A sensible default rule is created on activation, so no configuration is required to try the plugin.

== Frequently Asked Questions ==

= 🛡️ Does RayEtun Media Protection prevent people from stealing my images or PDFs? =

It prevents casual copying and direct-URL access, and it makes shared copies traceable. No browser-side plugin can prevent a determined user from screenshotting an image or extracting text from a PDF. What RayEtun Media Protection does that matters for most sites: it blocks right-clicks on your product photos, keeps protected PDFs off Google's index, makes every PDF a buyer downloads uniquely identifiable to that buyer, and lets you set download links to expire after a time window or click count.

= 🖼️ Does watermarking degrade my images? =

No. Originals are backed up before watermarking, so you can restore or re-watermark at any time. Watermarks are applied to the visible copies; the backup stays untouched.

= 📄 What PDF library does RayEtun Media Protection use? =

TCPDF for rendering, and FPDI (free version) for reading existing PDFs before watermarking. Both are open source and GPL-compatible; their license notices are preserved in `vendor/`.

= 🎨 Can I put a different watermark on different files? =

Yes. Create multiple rule sets under **RayEtun Media Protection → Watermarks**, then assign them per file, per product (for WooCommerce and EDD), or as the default for all new uploads.

= 🌐 Which e-commerce plugins does RayEtun Media Protection integrate with? =

WooCommerce, Easy Digital Downloads, and Download Monitor are supported out of the box. The integration activates automatically when RayEtun Media Protection detects the other plugin is installed and active.

= 🔐 Where are protected files stored? =

Outside the webroot where the server allows it, otherwise inside a protected folder within `wp-content/uploads/` with a strict `.htaccess` rule that blocks direct URL access. Either way, the only route to a protected file is through RayEtun Media Protection's serve handler, which validates access on every request.

= 🧱 How do the Protected Gallery and download blocks work? =

Add any of the three blocks — Protected Gallery, Protected Download, or Protected File Library — from the block inserter. The gallery shows clean thumbnails, opens a watermarked preview in a lightbox, and lets an authorized viewer download the clean original; the two download blocks present a file (or a filterable set of files) behind an access rule, with a sign-in button for logged-out visitors. Each block has an accent-color picker and a **who can download** setting (Everyone, logged-in users, or a named access rule). Set per-block defaults once under **RayEtun Media Protection → Blocks**.

= 🚫 Will right-click block work on mobile? =

It disables the mobile long-press context menu on browsers that support that hook, but mobile devices vary. Treat it as a casual deterrent on any platform. The reliable protection is the file-serving layer, not the JavaScript overlay.

= 🔒 Does RayEtun Media Protection send my data anywhere? =

No. There is no telemetry, no phone-home, no account, and no license check. Downloads, logs, and settings are stored only in your own WordPress database. See the External Services section — it is empty.

= 🧩 Does it conflict with other content-protection plugins? =

Running two watermarking or right-click plugins on the same file is not supported; disable one before activating the other. File-protection plugins can coexist for files RayEtun Media Protection is not managing.

= ♿ Is RayEtun Media Protection accessible? =

Yes. Every admin screen is designed to WCAG 2.2 Level AA — keyboard operable, focus managed, screen-reader labeled, and reduced-motion aware. The color palette meets the contrast requirements in both light and dark themes.

= ♻️ What happens if I uninstall RayEtun Media Protection? =

Everything is removed cleanly: the three RayEtun Media Protection database tables, all plugin options, custom capabilities, scheduled cron events, and the protected-uploads directory. Your original media, posts, and pages are never touched.

= 💬 Get Support =

Post in the [WordPress.org support forum](https://wordpress.org/support/plugin/rayetun-media-protection/).

== Screenshots ==

1. Dashboard — protected files, watermarks applied, blocked attempts, and active rules at a glance.
2. Watermark rule editor — text and image watermarks with nine-position anchoring, opacity, and rotation.
3. Dynamic PDF watermark — insert buyer email, order number, IP, or timestamp into every downloaded PDF.
4. Protected files list — every managed file, its rule, its serve count, and last access time.
5. Access rule builder — combine role, expiration, hotlink, and IP rules in a single rule set.
6. Analytics — per-file download and block activity, with hashed client fingerprints.
7. Integrations — detected store plugins (WooCommerce, Easy Digital Downloads, Download Monitor) and the per-buyer watermark preset applied to their downloads.
8. Settings — auto-protect uploads, deterrent layer, log retention, and theme controls.
9. Blocks — Protected Gallery, Protected Download, and Protected File Library in the editor, with per-block defaults.

== Changelog ==

= 1.0.0 =
* Initial release of RayEtun Media Protection.
* Image watermarking — text or image marks, nine-position anchoring, opacity and rotation, non-destructive with EXIF and IPTC preservation, dual Imagick and GD engines, small-image threshold.
* PDF watermarking — text or image marks on every page, dynamic tokens (`{user_email}`, `{user_ip}`, `{user_id}`, `{order_id}`, `{date}`, `{site}`, `{download_id}`), per-file rules, optional password protection.
* Protected file storage — files stored outside the webroot where possible with an `.htaccess` fallback; signed-token serve handler; role, user, expiration, click-count, and hotlink access rules.
* Auto-protect on upload — every new media library upload can be protected automatically by an active rule.
* WooCommerce, Easy Digital Downloads, and Download Monitor integrations — protected serving and per-buyer PDF watermarking, built in.
* Gutenberg blocks — Protected Gallery (clean thumbnails, watermarked lightbox preview, authorized clean download, right-click disabled, tag filter), Protected Download, and Protected File Library; accent-color picker, any file type with a matching icon, per-block defaults under RayEtun Media Protection → Blocks.
* Deterrent layer — right-click, drag-and-drop, and devtools deterrents, labeled as deterrents in the settings.
* Analytics — download attempt log with hashed client fingerprints, per-file activity view, automatic 90-day pruning (configurable).
* Multisite — full network compatibility, per-site or network-wide activation.
* Admin UI — sidebar navigation, light and dark themes, WCAG 2.2 Level AA throughout, no jQuery, built on `@wordpress/components`.
* Clean uninstall — all tables, options, capabilities, cron events, and the protected-uploads directory removed on delete.

== Upgrade Notice ==

= 1.0.0 =
Initial release of RayEtun Media Protection.

== External Services ==

RayEtun Media Protection sends no data anywhere.

There is no telemetry, no analytics phone-home, no account, and no license check. Watermarking, file storage, access rules, and download logs are stored only in your own WordPress database and file system. There are no third-party API calls, no CDNs contacted, and no external fonts or assets loaded on your visitors' pages.

== Development ==

RayEtun Media Protection is open source and built with the official [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) toolchain (webpack). The complete, un-minified source — every module and admin screen — ships in the plugin's `src/` and `src-ui/` directories; the files in `build/` are compiled from them. Nothing is obfuscated.

To build from source:

`composer install --no-dev && npm install && npm run build`

That regenerates everything in `build/`. `npm test` runs the unit suite, and `composer test` runs the PHP tests. No build step is needed to *use* the plugin — the compiled output is included.

Bug reports are welcome in the [support forum](https://wordpress.org/support/plugin/rayetun-media-protection/).

== Credits ==

RayEtun Media Protection bundles the following open-source libraries; their copyright and license notices are preserved in `vendor/`:

* [TCPDF](https://tcpdf.org/) by Nicola Asuni — LGPL-3.0-or-later — used for PDF watermarking output.
* [FPDI](https://www.setasign.com/fpdi) by Setasign GmbH & Co. KG (free version) — MIT — used for reading existing PDFs before watermarking.

Everything else is original work under GPL-2.0-or-later.
