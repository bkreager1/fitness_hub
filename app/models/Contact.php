<?php
// ============================================================
// app/models/Contact.php
// Database logic for the `contacts` table.
//
// Schema (see sql/fitness_hub.sql):
//   id            INT UNSIGNED PK
//   name          VARCHAR(100)
//   email         VARCHAR(150)
//   message       TEXT
//   submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// ============================================================

class Contact {

    // Insert a new contact submission. Returns the new row's id.
    // Inputs are assumed to be already validated by the controller.
    public static function create(string $name, string $email, string $message): int {
        $sql = 'INSERT INTO contacts (name, email, message)
                VALUES (:name, :email, :message)';
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':message' => $message,
        ]);
        return (int) db()->lastInsertId();
    }
}
