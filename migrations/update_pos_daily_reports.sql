-- Migration: Update pos_daily_reports table structure
-- Remove unnecessary columns and add payment method tracking

-- Step 1: Add new columns for payment method breakdown
ALTER TABLE pos_daily_reports
ADD COLUMN IF NOT EXISTS cash_count INT DEFAULT 0 AFTER cash_sales,
ADD COLUMN IF NOT EXISTS gcash_count INT DEFAULT 0 AFTER cash_count,
ADD COLUMN IF NOT EXISTS membership_count INT DEFAULT 0 AFTER membership_sales;

-- Step 2: Remove unnecessary columns
ALTER TABLE pos_daily_reports
DROP COLUMN IF EXISTS digital_sales,
DROP COLUMN IF EXISTS service_sales,
DROP COLUMN IF EXISTS class_package_sales,
DROP COLUMN IF EXISTS walkin_sales;

-- Verify the new structure
DESCRIBE pos_daily_reports;
