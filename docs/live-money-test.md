# Live Money Test Run Sheet

This is the owner's step-by-step guide for testing real-money flows on live before accepting real customer bookings.

### Before you start
1. Deploy: `git pull`, `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize:clear`.
2. Fix any past cancellations: `php artisan wallet:reverse-cancelled-commissions --dry-run`, then `php artisan wallet:reverse-cancelled-commissions`.
3. Razorpay live key/secret rotated and saved in SaaS settings. RazorpayX webhook secret set.
4. `php artisan gst:turfs-missing-state` lists nothing, or only turfs you don't use for this test.
5. **Test turf:**
   - Use one of your own turfs, owned by an account whose wallet starts at ₹0 (a fresh test owner is best). Note its owner's user id.
   - Set its slot price to **₹100/hour** for every day, so a payout is possible: payout charges are ₹40.
   - Set its turf cancellation fee to **₹5/slot**.
   - Turn on online payment, part payment (50%) and pay at venue.
   - Make sure you can book a slot **later today**, at least 2 hours ahead so cancellation is allowed. Credits are released by the nightly job once the session date arrives, and you will run the job by hand.
6. Use a separate player account on the phone app.

Cost: you pay real money through Razorpay. Razorpay keeps about 2.4% of each online payment. The platform fee and commission go to your own SaaS account. Refunds take 5-7 working days to reach the card or UPI.

### Run these in order. After each step, run the command shown and expect `0 failed`.

Replace `{B}` with the booking id shown in the turf dashboard, and `{O}` with the test owner's user id.

| # | What to do | Then run |
|---|---|---|
| 1 | Player books 1 slot today, **Pay online (full)** | `php artisan money:check --booking={B}` |
| 2 | Player books 1 slot today, **Part payment**. At the venue, record the balance as **Cash** from the dashboard | `php artisan money:check --booking={B}` |
| 3 | Player books 1 slot today, **Pay at venue**. Record full **Cash** | `php artisan money:check --booking={B}` |
| 4 | Player books 1 slot **tomorrow**, pay online. Then the player cancels it from the app. **What to expect:** The owner gets back the commission and Razorpay fee for the cancelled session. Their wallet for this booking ends at the turf cancellation fee. | `php artisan money:check --booking={B}` |
| 5 | Player books 1 slot **tomorrow**, pay at venue, staff record Cash. Then cancel | `php artisan money:check --booking={B}` |
| 6 | Player books 1 slot tomorrow, pay online. Staff **reschedule** it to a cheaper slot (set one slot to ₹50 first), so a refund goes out | `php artisan money:check --booking={B}` |
| 7 | After all of today's sessions have ended: run `php artisan wallet:clear-matured-entries` | `php artisan money:check --owner={O}` |
| 8 | Request a payout. In Payout Manager → Pending transfers, transfer the net amount from your bank, then Mark as paid with the UTR. Run `money:check --owner={O}`. Also try a Reject on a second small request and run it again. | `php artisan money:check --owner={O}` |

For steps 4-6, credits are released on the session date. Run `php artisan wallet:clear-matured-entries` again the next day, then `money:check --owner={O}` once more.

**Also look at these by eye:**
- **Invoice PDFs.** Download the invoice for booking 1. It should show the turf GST lines (if the turf has GST on), a Place of Supply, and a platform-fee page with CGST + SGST for a same-state turf.
- **Business & Earnings.** The "Pending Clearance" panel equals the Pending Clearance row printed by `money:check --owner`.
- **Razorpay dashboard.** The payments and refunds match bookings 1, 2, 4 and 6.

### If anything shows FAIL
Copy the whole command output and send it to me (Claude). Don't start real bookings until all checks pass.

### After the test
- Cancel and refund anything left. Set the slot prices and cancellation fee back to the real values.
- **Do not run `bookings:reset` on live** unless no real bookings exist yet: it deletes all bookings.
- The test bookings will appear in the next month's commission invoice for the test turf (generated on the 1st), which is expected.
