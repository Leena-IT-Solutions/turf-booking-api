# SEO Optimization Plan - turf.infoleena.com

This document outlines the strategic and technical SEO optimization plan for the TurfBooking platform.

---

## 1. On-Page SEO Optimizations

### 1.1 Clean Title Tag Repetition
* **Issue**: The current title tag in [`marketing-layout.blade.php`](file:///d:/Projects/turf/turf-booking-api/resources/views/components/marketing-layout.blade.php) appends `- Premium Turf Scheduling & Booking Platform` unconditionally:
  ```html
  <title>{{ $title ?? config('app.name', 'TurfBooking') }} - Premium Turf Scheduling & Booking Platform</title>
  ```
  This creates double-branded and excessively long titles when individual views define their own custom titles (e.g. `How It Works - TurfBooking - Premium Turf Scheduling & Booking Platform`).
* **Fix**: Change it to output the custom title directly, with a fallback default:
  ```html
  <title>{{ $title ?? 'TurfBooking - Premium Turf Scheduling & Booking Platform' }}</title>
  ```

### 1.2 Dynamic Meta Descriptions
* **Issue**: Currently, the meta description is hardcoded in the master layout, leading to duplicate meta descriptions across all pages.
* **Fix**: Support a `$description` slot/variable:
  ```html
  <meta name="description" content="{{ $description ?? 'Manage your turf, schedule bookings, collect online payments, and grow your sports business. Easy turf bookings for players.' }}">
  ```

### 1.3 Add Canonical Link Tags
* **Fix**: Ensure search engines index only the primary URL for each page by adding a canonical tag to the `<head>` section:
  ```html
  <link rel="canonical" href="{{ url()->current() }}">
  ```

---

## 2. Technical SEO Optimizations

### 2.1 robots.txt Configuration
Create a proper `public/robots.txt` file to instruct search engines to index the marketing site and avoid crawling administrative panel routes:
```txt
User-agent: *
Allow: /
Disallow: /dashboard
Disallow: /profile
Disallow: /saas/
Disallow: /turf/

Sitemap: https://turf.infoleena.com/sitemap.xml
```

### 2.2 XML Sitemap Generation
Implement a dynamic sitemap in Laravel that lists:
* All static marketing pages (Home, Features, How It Works, Pricing, FAQs, Contact).
* All verified public turf detail pages (when players look up specific turfs).

We can build a simple route and a sitemap controller/view that outputs valid XML:
* **Route**: `Route::get('/sitemap.xml', [SitemapController::class, 'index']);`

---

## 3. Structured Data (Schema Markup)

Add JSON-LD Structured Data to tell search engines what the platform is and how individual turf pages represent local businesses.

### 3.1 Organization / SaaS Product Schema (On Landing Page)
```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "TurfBooking",
  "operatingSystem": "All",
  "applicationCategory": "BusinessApplication",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "INR"
  }
}
```

### 3.2 SportsActivityLocation Schema (For individual turf profiles)
For turf pages, inject a schema definition detailing:
* Physical address and geo-coordinates.
* Telephone number and opening hours.
* Sport type categories (e.g., Football Field, Cricket Pitch).

---

## 4. Local SEO Strategy (High Impact)

Since players search for sports venues locally (e.g., *"football turf near me"*, *"cricket turf in [City]"*):
1. **Location Landing Pages**: Automatically generate pages for cities with active turfs (e.g., `turf.infoleena.com/cities/mumbai`).
2. **Google Business Profile Integration**: Encourage listed turf owners to link to their booking pages on their Google Business Profiles.
