-- Add description column to api_consumers table
ALTER TABLE api_consumers
ADD COLUMN description TEXT NULL
AFTER website_url;
