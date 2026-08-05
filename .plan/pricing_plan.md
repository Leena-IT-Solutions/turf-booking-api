# Implementation Plan - Dynamic Pricing Plan Integration

We will connect the static pricing page to load active subscription packages from the database (configured via the SaaS Admin CRUD). Each pricing plan will dynamically display its cost, validity period, and custom commission rates.

## Proposed Changes

### Routes Configuration

#### [MODIFY] [web.php](file:///d:/Projects/turf/turf-booking-api/routes/web.php)
- Replace `Route::view('/pricing', 'pricing')` with a closure or controller query that retrieves active subscription packages:
  ```php
  Route::get('/pricing', function () {
      $packages = \App\Models\SubscriptionPackage::where('is_active', true)
          ->orderBy('sort_order', 'asc')
          ->get();
      return view('pricing', compact('packages'));
  })->name('pricing');
  ```

---

### Marketing Content Views

#### [MODIFY] [pricing.blade.php](file:///d:/Projects/turf/turf-booking-api/resources/views/pricing.blade.php)
- Add a Blade block at the top to compute fallback package models if the database contains 0 active plans, preventing empty states on clean setups.
- Re-style the toggle switch to select between **Monthly Equivalent** and **Full Package Duration** price display modes.
- Replace the static plan cards with a dynamic `@foreach ($displayPackages as $pkg)` loop.
- Populate pricing card titles, descriptions, and feature lists dynamically.
- Display each package's individual `commission_percentage` (e.g. `{{ number_format($pkg->commission_percentage, 1) }}% platform commission`).
- Update the FAQ section to clarify that commission fees vary dynamically per plan.

---

## Verification Plan

### Automated Tests
- Run `php artisan test --filter=MarketingPagesTest` to ensure that the page continues to load successfully.
- Run `npm run build` to verify Vite asset compilation.

### Manual Verification
- Test toggling between Monthly Equivalent and Full Package prices to verify calculations function correctly.
- Verify the dynamic plans are styled cleanly, keeping the dark gradient theme for the popular/growth tier.
