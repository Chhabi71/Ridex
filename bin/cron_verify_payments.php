<?php
/**
 * Purpose: Nightly reconciliation with Khalti to verify payment statuses.
 * Website Section: Background Jobs (Payments).
 * Developer Notes: Pull pending/initiated payments, call Khalti verify, update payment/booking status, and log discrepancies.
 */
