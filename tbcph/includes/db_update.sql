-- Update location table structure
ALTER TABLE location 
    CHANGE COLUMN barangay city VARCHAR(100) NOT NULL,
    DROP COLUMN region;

-- Update existing data to match new structure
UPDATE location SET city = 'Manila' WHERE city = 'Ermita';
UPDATE location SET city = 'Pasay' WHERE city = 'Pasay';
UPDATE location SET city = 'Makati' WHERE city = 'Makati';
UPDATE location SET city = 'Quezon City' WHERE city = 'Bagumbayan';
UPDATE location SET city = 'Taguig' WHERE city = 'Fort Bonifacio';
UPDATE location SET city = 'Quezon City' WHERE city = 'Diliman';
UPDATE location SET city = 'Quezon City' WHERE city = 'Greater Lagro';
UPDATE location SET city = 'Marikina' WHERE city = 'Marikina Heights';
UPDATE location SET city = 'Quezon City' WHERE city = 'South Triangle';
UPDATE location SET city = 'Pasay' WHERE city = 'Pasay';

-- Add is_custom column if it doesn't exist
ALTER TABLE location 
    ADD COLUMN IF NOT EXISTS is_custom BOOLEAN DEFAULT 0; 