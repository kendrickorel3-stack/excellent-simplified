USE excellent_academy;

-- Add explanation to questions (if absent)
ALTER TABLE questions ADD COLUMN IF NOT EXISTS explanation TEXT DEFAULT NULL;

-- Ensure subject_id column exists (some installs lacked it)
ALTER TABLE questions ADD COLUMN IF NOT EXISTS subject_id INT NULL;

-- Ensure correct_answer exists
ALTER TABLE questions ADD COLUMN IF NOT EXISTS correct_answer CHAR(1) DEFAULT NULL;

-- Add time_taken to answers (so INSERT in practice_test.php succeeds)
ALTER TABLE answers ADD COLUMN IF NOT EXISTS time_taken INT DEFAULT NULL;

-- Helpful index
CREATE INDEX IF NOT EXISTS idx_questions_subject ON questions(subject_id);

