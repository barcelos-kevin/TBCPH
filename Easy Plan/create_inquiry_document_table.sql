-- Create the inquiry_document junction table
CREATE TABLE inquiry_document (
    inquiry_id INT NOT NULL,
    docs_id INT NOT NULL,
    PRIMARY KEY (inquiry_id, docs_id),
    FOREIGN KEY (inquiry_id) REFERENCES inquiry(inquiry_id) ON DELETE CASCADE,
    FOREIGN KEY (docs_id) REFERENCES supporting_document(docs_id) ON DELETE CASCADE
);

-- Migrate existing documents to the new table
INSERT INTO inquiry_document (inquiry_id, docs_id)
SELECT inquiry_id, docs_id
FROM inquiry
WHERE docs_id IS NOT NULL;

-- Remove the docs_id column from inquiry table
ALTER TABLE inquiry DROP FOREIGN KEY inquiry_ibfk_3;
ALTER TABLE inquiry DROP COLUMN docs_id; 