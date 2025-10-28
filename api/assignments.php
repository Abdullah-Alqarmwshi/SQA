<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

header('Content-Type: application/json');

// GET request - Fetch assignments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if ($id) {
        // Fetch single assignment
        $stmt = $conn->prepare("SELECT a.*, u.full_name as teacher_name 
                              FROM assignments a 
                              JOIN users u ON a.teacher_id = u.id 
                              WHERE a.id = ?");
        $stmt->bind_param("i", $id);
    } else {
        // Fetch all assignments with filters
        if (isset($_GET['view']) && $_GET['view'] === 'student') {
            // Query for student view with submission status
            $query = "SELECT a.*, u.full_name as teacher_name,
                     (SELECT s.id FROM submissions s 
                      WHERE s.assignment_id = a.id 
                      AND s.student_id = ? LIMIT 1) as submission_id,
                     (SELECT s.grade FROM submissions s 
                      WHERE s.assignment_id = a.id 
                      AND s.student_id = ? LIMIT 1) as grade,
                     CASE 
                        WHEN (SELECT grade FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) IS NOT NULL THEN 'graded'
                        WHEN (SELECT id FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) IS NOT NULL THEN 'submitted'
                        ELSE 'pending'
                     END as submission_status
                     FROM assignments a 
                     JOIN users u ON a.teacher_id = u.id 
                     WHERE 1=1";
        } else {
            // Simple query for teacher view
            $query = "SELECT a.*, u.full_name as teacher_name,
                     (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count
                     FROM assignments a 
                     JOIN users u ON a.teacher_id = u.id 
                     WHERE 1=1";
        }
        
        if ($search) {
            $search = "%$search%";
            $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
        }
        
        // Handle different filter options
        if (isset($_GET['view']) && $_GET['view'] === 'student') {
            // Student-specific filters
            if ($filter === 'pending') {
                $query .= " AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?)";
            } elseif ($filter === 'submitted') {
                $query .= " AND EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ? AND s.grade IS NULL)";
            } elseif ($filter === 'graded') {
                $query .= " AND EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ? AND s.grade IS NOT NULL)";
            }
        } else {
            // Teacher filters
            if ($filter === 'active') {
                $query .= " AND a.due_date > NOW()";
            } elseif ($filter === 'due-soon') {
                $query .= " AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
            } elseif ($filter === 'past') {
                $query .= " AND a.due_date < NOW()";
            }
        }
        
        // First sort by due date, then by creation date
        $query .= " ORDER BY a.due_date ASC, a.created_at DESC";
        
        $stmt = $conn->prepare($query);
        
        if (isset($_GET['view']) && $_GET['view'] === 'student') {
            $student_id = $_SESSION['user_id'];
            if ($search && $filter) {
                $stmt->bind_param("iiiiss", $student_id, $student_id, $student_id, $student_id, $search, $search);
            } elseif ($search) {
                $stmt->bind_param("iiiiss", $student_id, $student_id, $student_id, $student_id, $search, $search);
            } elseif ($filter) {
                $stmt->bind_param("iiiii", $student_id, $student_id, $student_id, $student_id, $student_id);
            } else {
                $stmt->bind_param("iiii", $student_id, $student_id, $student_id, $student_id);
            }
        } else if ($search) {
            $stmt->bind_param("ss", $search, $search);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($id) {
        // For single assignment fetch
        $assignment = $result->fetch_assoc();
        if ($assignment) {
            // Get submission statistics
            $stats_query = "SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN grade IS NOT NULL THEN 1 END) as graded_submissions,
                COUNT(CASE WHEN grade IS NULL THEN 1 END) as pending_submissions
                FROM submissions 
                WHERE assignment_id = ?";
            $stats_stmt = $conn->prepare($stats_query);
            $stats_stmt->bind_param("i", $id);
            $stats_stmt->execute();
            $stats = $stats_stmt->get_result()->fetch_assoc();
            
            $assignment['statistics'] = $stats;
            echo json_encode($assignment);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Assignment not found']);
        }
    } else {
        // For multiple assignments fetch
        $assignments = [];
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        echo json_encode($assignments);
    }
}

// POST request - Create/Update assignment
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is a teacher
    if ($_SESSION['role'] !== 'teacher') {
        http_response_code(403);
        exit('Forbidden');
    }
    
    $assignment_id = isset($_POST['assignment_id']) ? $_POST['assignment_id'] : null;
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $teacher_id = $_SESSION['user_id'];
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Ensure uploads directory exists
        $physical_upload_dir = __DIR__ . '/../uploads/assignments/';
        if (!file_exists($physical_upload_dir)) {
            mkdir($physical_upload_dir, 0777, true);
        }
        
        // Create a URL-friendly filename
        $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['file']['name']);
        
        // Store the relative path in database (this will be prefixed with ../ when accessed)
        $file_path = 'uploads/assignments/' . $file_name;
        
        // Move the uploaded file using absolute path
        move_uploaded_file($_FILES['file']['tmp_name'], $physical_upload_dir . $file_name);
    }
    
    if ($assignment_id) {
        // Update existing assignment
        if ($file_path) {
            $sql = "UPDATE assignments SET 
                    title = ?, 
                    description = ?, 
                    due_date = ?,
                    file_path = ?
                    WHERE id = ? AND teacher_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $title, $description, $due_date, $file_path, $assignment_id, $teacher_id);
        } else {
            $sql = "UPDATE assignments SET 
                    title = ?, 
                    description = ?, 
                    due_date = ?
                    WHERE id = ? AND teacher_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $title, $description, $due_date, $assignment_id, $teacher_id);
        }
    } else {
        // Create new assignment
        $stmt = $conn->prepare("INSERT INTO assignments (title, description, due_date, file_path, teacher_id) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $description, $due_date, $file_path, $teacher_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
}

// DELETE request - Delete assignment
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($_SESSION['role'] !== 'teacher') {
        http_response_code(403);
        exit('Forbidden');
    }
    
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if (!$id) {
        http_response_code(400);
        exit('Missing assignment ID');
    }
    
    // First delete related submissions
    $stmt = $conn->prepare("DELETE FROM submissions WHERE assignment_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Then delete the assignment
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
}

$conn->close();
?>