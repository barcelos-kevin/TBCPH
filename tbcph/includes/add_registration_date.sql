    -- Add registration_date column to busker table
    ALTER TABLE busker
    ADD COLUMN registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

    -- Update existing records to have registration_date
    UPDATE busker
    SET registration_date = CURRENT_TIMESTAMP
    WHERE registration_date IS NULL; 