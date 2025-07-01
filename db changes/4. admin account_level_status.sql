-- Add account_level and status columns to admin table
ALTER TABLE admin
  ADD COLUMN account_level VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER password,
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER account_level;

-- Set all existing admins to approved and super_admin for the first admin
UPDATE admin SET account_level = 'super_admin', status = 'approved' WHERE email = 'admin@eventmanagement.com';
UPDATE admin SET status = 'approved' WHERE status = 'pending';