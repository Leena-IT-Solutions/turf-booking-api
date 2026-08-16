# Implementation Plan - Refund/Cancellation Policy Page

We need to add a "Refund/Cancellation Policy" page. As per the requirements, there is no standard rule for all turfs; refund and cancellation charges are applied as per the individual turf's own policy, and players must check this policy before making a booking.

We will create a new blade template, define the route, and update the footer link in the marketing layout.

## Proposed Changes

### Routes

#### [MODIFY] [web.php](file:///d:/Projects/turf/turf-booking-api/routes/web.php)
- Add a new route for `/refund-policy` pointing to the `refund-policy` view, named `refund-policy`.

### Views

#### [NEW] [refund-policy.blade.php](file:///d:/Projects/turf/turf-booking-api/resources/views/refund-policy.blade.php)
- Create a new static blade page using `<x-marketing-layout>`.
- Content will clearly state:
  - Refund and cancellation charges are determined individually by each turf.
  - No standard/blanket refund rules apply across all venues.
  - Players must review the specific turf's refund and cancellation terms before checking out.
  - Add stylized sections with icons and tips to make it visually engaging and readable.

#### [MODIFY] [marketing-layout.blade.php](file:///d:/Projects/turf/turf-booking-api/resources/views/components/marketing-layout.blade.php)
- Update the "Refund/Cancellation Policy" link in the footer to point to `{{ route('refund-policy') }}` instead of `{{ url('/#') }}`.

## Verification Plan

### Manual Verification
- Access `/refund-policy` in the browser to ensure the page renders correctly.
- Click the "Refund/Cancellation Policy" link in the website footer to verify it redirects to the new policy page.
- Check responsive styles on mobile view.
