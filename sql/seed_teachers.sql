-- Seed Data for Teachers Only
-- Run this in phpMyAdmin or MySQL to insert sample teachers for testing
-- Assumes departments with IDs 1-5 exist

-- 15 Teachers (3 per department across 5 departments) with unique emails
INSERT IGNORE INTO teachers (full_name, email, department_id, is_active, created_at) VALUES 
-- Department 1: College of Business Administration
('Dr. Maria Santos', 'maria.santos1@school.edu', 1, 1, NOW()),
('Prof. Robert Smith', 'robert.smith1@school.edu', 1, 1, NOW()),
('Dr. Jessica Martinez', 'jessica.martinez1@school.edu', 1, 1, NOW()),

-- Department 2: College of Entrepreneurship
('Prof. Juan Dela Cruz', 'juan.delacruz1@school.edu', 2, 1, NOW()),
('Dr. Sarah Johnson', 'sarah.johnson1@school.edu', 2, 1, NOW()),
('Prof. James Taylor', 'james.taylor1@school.edu', 2, 1, NOW()),

-- Department 3: College of Accountancy
('Dr. Ana Reyes', 'ana.reyes1@school.edu', 3, 1, NOW()),
('Prof. Michael Brown', 'michael.brown1@school.edu', 3, 1, NOW()),
('Dr. Jennifer Anderson', 'jennifer.anderson1@school.edu', 3, 1, NOW()),

-- Department 4: College of Education
('Prof. Pedro Garcia', 'pedro.garcia1@school.edu', 4, 1, NOW()),
('Dr. Emily Davis', 'emily.davis1@school.edu', 4, 1, NOW()),
('Prof. Christopher Thomas', 'christopher.thomas1@school.edu', 4, 1, NOW()),

-- Department 5: College of Computer Studies
('Dr. Elena Lopez', 'elena.lopez1@school.edu', 5, 1, NOW()),
('Prof. David Wilson', 'david.wilson1@school.edu', 5, 1, NOW()),
('Dr. Amanda Jackson', 'amanda.jackson1@school.edu', 5, 1, NOW());
