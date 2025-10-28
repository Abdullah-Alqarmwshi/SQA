// Student Assignment Management Scripts
document.addEventListener('DOMContentLoaded', function() {
    loadAssignments();

    // Search & filter handlers
    document.getElementById('search').addEventListener('input', loadAssignments);
    document.getElementById('filter').addEventListener('change', loadAssignments);

    // Submit form handler
    document.getElementById('formSubmit').addEventListener('submit', handleSubmission);
});

// Load assignments from server
function loadAssignments() {
    const search = document.getElementById('search').value;
    const filter = document.getElementById('filter').value;

    fetch(`../api/assignments.php?view=student&search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`)
        .then(response => response.json())
        .then(assignments => {
            renderAssignments(assignments);
        })
        .catch(error => console.error('Error:', error));
}

// Get submission status for an assignment
async function getSubmissionStatus(assignmentId) {
    try {
        const response = await fetch(`../api/submissions.php?assignment_id=${assignmentId}`);
        const submissions = await response.json();
        return submissions[0] || null; // Return first submission or null
    } catch (error) {
        console.error('Error:', error);
        return null;
    }
}

// Render assignments list
function renderAssignments(assignments) {
    const list = document.getElementById('assignmentsList');
    list.innerHTML = '';

    if (assignments.length === 0) {
        list.innerHTML = '<div class="card"><div class="text-muted p-3">No assignments found.</div></div>';
        return;
    }

    assignments.forEach(assignment => {
        // Add due date status badge
        const dueDate = new Date(assignment.due_date);
        const now = new Date();
        const sevenDaysFromNow = new Date();
        sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7);

        let dueDateClass = '';
        let dueDateText = '';
        
        if (dueDate < now) {
            dueDateClass = 'past-due';
            dueDateText = 'Past Due';
        } else if (dueDate <= sevenDaysFromNow) {
            dueDateClass = 'due-soon';
            dueDateText = 'Due Soon';
        }
        const card = document.createElement('div');
        card.className = 'card d-flex justify-content-between align-items-center';
        card.style.padding = '12px';

        const submissionStatus = assignment.submission_status === 'graded'
            ? `<div class="badge bg-success me-2">Graded</div>`
            : assignment.submission_status === 'submitted'
            ? `<div class="badge bg-primary me-2">Submitted</div>`
            : `<div class="badge bg-warning me-2">Pending</div>`;

        const dueDateBadge = dueDateClass
            ? `<div class="badge ${dueDateClass} me-2">${dueDateText}</div>`
            : '';

        card.innerHTML = `
            <div>
                <div class="fw-bold fs-5">${escapeHtml(assignment.title)}</div>
                <div class="text-muted">${escapeHtml(assignment.description)}</div>
                <div class="text-muted mt-2">
                    Due: ${formatDate(assignment.due_date)} • Teacher: ${escapeHtml(assignment.teacher_name)}
                </div>
            </div>
            <div class="actions d-flex gap-2 align-items-center">
                ${dueDateBadge}
                ${submissionStatus}
                <button onclick="viewAssignment(${assignment.id})" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-eye"></i> View Details
                </button>
            </div>
        `;
        list.appendChild(card);
    });
}

// View assignment details
async function viewAssignment(id) {
    try {
        const [assignment, submission] = await Promise.all([
            fetch(`../api/assignments.php?id=${id}`).then(r => r.json()),
            getSubmissionStatus(id)
        ]);

        document.getElementById('viewTitle').textContent = assignment.title;
        document.getElementById('viewDesc').textContent = assignment.description;
        document.getElementById('viewDue').textContent = formatDate(assignment.due_date);
        document.getElementById('viewTeacher').textContent = assignment.teacher_name;
        document.getElementById('viewFile').innerHTML = assignment.file_path 
            ? `<a href="../${assignment.file_path}" target="_blank">${assignment.file_path.split('/').pop()}</a>`
            : '—';

        // Show submission status
        const statusDiv = document.getElementById('submissionStatus');
        const actionsDiv = document.getElementById('submissionActions');
        
        if (submission) {
            statusDiv.innerHTML = `
                <div class="alert ${submission.grade ? 'alert-success' : 'alert-info'}">
                    <strong>Status:</strong> ${submission.grade ? 'Graded' : 'Submitted'}<br>
                    <strong>Submitted:</strong> ${formatDateTime(submission.submitted_at)}<br>
                    <strong>File:</strong> <a href="../${submission.file_path}" target="_blank">
                        ${submission.file_path.split('/').pop()}
                    </a>
                    ${submission.grade ? `<br><strong>Grade:</strong> ${submission.grade}` : ''}
                    ${submission.feedback ? `<br><strong>Feedback:</strong> ${submission.feedback}` : ''}
                </div>
            `;

            // Allow re-submission before deadline
            if (isBeforeDeadline(assignment.due_date)) {
                actionsDiv.innerHTML = `
                    <button onclick="submitAssignment(${assignment.id})" class="btn btn-primary">
                        <i class="fa fa-upload"></i> Re-submit
                    </button>
                `;
            } else {
                actionsDiv.innerHTML = '<div class="text-muted">Deadline has passed</div>';
            }
        } else {
            statusDiv.innerHTML = '<div class="alert alert-warning">Not submitted yet</div>';
            if (isBeforeDeadline(assignment.due_date)) {
                actionsDiv.innerHTML = `
                    <button onclick="submitAssignment(${assignment.id})" class="btn btn-primary">
                        <i class="fa fa-upload"></i> Submit Assignment
                    </button>
                `;
            } else {
                actionsDiv.innerHTML = '<div class="text-danger">Deadline has passed</div>';
            }
        }

        new bootstrap.Modal(document.getElementById('modalView')).show();
    } catch (error) {
        console.error('Error:', error);
        alert('Error loading assignment details');
    }
}

// Open submit modal
function submitAssignment(id) {
    document.getElementById('submitAssignmentId').value = id;
    document.getElementById('submitFile').value = '';
    new bootstrap.Modal(document.getElementById('modalSubmit')).show();
}

// Handle assignment submission
async function handleSubmission(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const response = await fetch('../api/submissions.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSubmit')).hide();
            bootstrap.Modal.getInstance(document.getElementById('modalView')).hide();
            loadAssignments();
            alert('Assignment submitted successfully!');
        } else {
            const error = await response.text();
            alert('Error: ' + error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while submitting the assignment.');
    }
}

// Utility functions
function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString();
}

function formatDateTime(dateStr) {
    return new Date(dateStr).toLocaleString();
}

function isBeforeDeadline(dueDate) {
    return new Date(dueDate) > new Date();
}