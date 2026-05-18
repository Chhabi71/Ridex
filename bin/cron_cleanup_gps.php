<?php
/**
 * Purpose: Cron script to purge old GPS log data beyond retention.
 * Website Section: Background Jobs (GPS Tracking).
 * Developer Notes: Delete gps_logs older than retention window, ensure indexing remains healthy, and log deletions.
 */
