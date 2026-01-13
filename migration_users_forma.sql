
-- 1. Add 'forma' column if it doesn't exist.
-- UNCOMMENT the line below if you are running this for the first time.
-- ALTER TABLE Usuarios ADD COLUMN forma ENUM('presencial', 'online', 'no aplica') DEFAULT 'no aplica';

-- 2. RESET defaults based on Profile
-- Non-students (Admin, Teacher, Security) -> 'no aplica'
UPDATE Usuarios SET forma = 'no aplica' WHERE perfil_id != 3;

-- Students -> Set default to 'presencial' initially
UPDATE Usuarios SET forma = 'presencial' WHERE perfil_id = 3;

-- 3. Update 'online' students
-- Logic: Students who have ANY inscription in Sucursal 'ONLINE' (id=2).
-- We prioritize Online: if they have at least one class in Online, they are considered Online.

UPDATE Usuarios u
JOIN (
    SELECT DISTINCT i.alumno_id
    FROM Inscripciones i
    JOIN Clases c ON i.clase_id = c.id
    WHERE c.sucursal_id = 2
) as online_students ON u.id = online_students.alumno_id
SET u.forma = 'online'
WHERE u.perfil_id = 3;
