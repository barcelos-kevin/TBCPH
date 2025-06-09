-- Add address column to client table
ALTER TABLE client
ADD COLUMN address VARCHAR(500) AFTER phone;

-- Update existing records with a default value (optional)
UPDATE client
SET address = 'Not provided'
WHERE address IS NULL; 