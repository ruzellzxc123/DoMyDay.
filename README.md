# Teacher Evaluation System

## Overview
A web-based teacher evaluation system that allows students, program heads, and deans to evaluate teachers. Students can only evaluate teachers assigned to their section.

## Features
- Role-based evaluation (Student 50%, Program Head 30%, Dean 20%)
- Anonymous student evaluations
- Section-based teacher assignment for students
- Admin dashboard with reports
- Email reminders for pending evaluations

## Users
- **Admin**: admin@school.edu / admin123
- **Dean**: dean@school.edu / dean123
- **Program Head**: ph@school.edu / ph123
- **Student**: student@school.edu / student123

## Student Section System

### How Students Evaluate Teachers
Students can only evaluate teachers **assigned to their section**. This ensures that:
1. Students evaluate teachers who actually teach them
2. Teachers cannot be evaluated by students not in their section

### Database Tables
- `sections` - Section information tied to program and department
- `section_teachers` - Multiple teachers can teach a section
- `section_students` - Students enrolled in sections

### Admin Management
Go to **Sections** in the admin dashboard to:
1. Create sections (e.g., "A", "B" for a program)
2. Assign teachers to sections
3. Enroll students in sections

## Technology
- PHP + MySQL
- Vanilla JS + Chart.js
- Bootstrap-like CSS

## Setup
1. Run `sql/schema.sql` to create database
2. Access via http://localhost/HACKATHON/
3. Login with admin credentials
