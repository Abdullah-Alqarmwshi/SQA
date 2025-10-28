// Assignment management scripts
document.addEventListener('DOMContentLoaded', function() {
    loadAssignments();

    // Assignment search & filter handlers
    document.getElementById('search').addEventListener('input', loadAssignments);
    document.getElementById('filter').addEventListener('change', loadAssignments);

    // Submissions search & filter handlers
    document.getElementById('searchSubmissions')?.addEventListener('input', filterSubmissions);
    document.getElementById('filterSubmissions')?.addEventListener('change', filterSubmissions);

    // Create assignment handler
    document.getElementById('btnCreate').addEventListener('click', () => {
        document.getElementById('formCreate').reset();
        document.getElementById('assignId').value = '';
        document.getElementById('modalCreateTitle').textContent = 'Create Assignment';
        new bootstrap.Modal(document.getElementById('modalCreate')).show();
    });

    // Form submission handler
    document.getElementById('formCreate').addEventListener('submit', handleAssignmentSubmit);
});

// Load assignments from server
function loadAssignments() {
    const search = document.getElementById('search').value;
    const filter = document.getElementById('filter').value;
    
    console.log('Loading assignments with filter:', filter); // Debug log

    fetch(`../api/assignments.php?search=${encodeURIComponent(search)}&filter=${encodeURIComponent(filter)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(assignments => {
            console.log('Received assignments:', assignments.length); // Debug log
            renderAssignments(assignments);
        })
        .catch(error => {
            console.error('Error loading assignments:', error);
            document.getElementById('assignmentsList').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading assignments. Please try again.
                </div>
            `;
        });
}

// Render assignments list
function renderAssignments(assignments) {
    const list = document.getElementById('assignmentsList');
    list.innerHTML = '';

    if (assignments.length === 0) {
        list.innerHTML = `
            <div class="text-center py-5">
                <div class="text-muted mb-3">
                    <i class="fas fa-clipboard fa-3x"></i>
                </div>
                <h3 class="h5 text-muted">No assignments found</h3>
                <p class="text-muted">Create your first assignment to get started</p>
            </div>`;
        return;
    }

    assignments.forEach(assignment => {
        const dueDate = new Date(assignment.due_date);
        const today = new Date();
        const daysUntilDue = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
        
        let statusBadge = '';
        let badgeTooltip = '';
        
        if (daysUntilDue < 0) {
            statusBadge = '<span class="badge bg-danger" title="Assignment is past due">Past Due</span>';
            badgeTooltip = 'Deadline was ' + Math.abs(daysUntilDue) + ' days ago';
        } else if (daysUntilDue <= 7) {
            statusBadge = '<span class="badge bg-warning" title="Due within 7 days">Due Soon</span>';
            badgeTooltip = 'Due in ' + daysUntilDue + ' days';
        } else {
            statusBadge = '<span class="badge bg-success" title="Assignment is active">Active</span>';
            badgeTooltip = 'Due in ' + daysUntilDue + ' days';
        }

        const card = document.createElement('div');
        card.className = 'card assignment-card mb-3';
        card.innerHTML = `
            <div>
                <div class="fw-bold fs-5">${escapeHtml(assignment.title)}</div>
                <div class="text-muted">${escapeHtml(assignment.description)}</div>
                <div class="text-muted mt-2">
                    Due: ${formatDate(assignment.due_date)} • Created by: ${escapeHtml(assignment.teacher_name)}
                </div>
            </div>
            <div class="actions d-flex gap-2">
                <button onclick="viewAssignment(${assignment.id})" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-eye"></i> View
                </button>
                <button onclick="editAssignment(${assignment.id})" class="btn btn-outline-warning btn-sm">
                    <i class="fa fa-pen"></i> Edit
                </button>
                <button onclick="viewSubmissions(${assignment.id})" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-list"></i> Submissions
                </button>
                <button onclick="deleteAssignment(${assignment.id})" class="btn btn-outline-danger btn-sm">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </div>
        `;
        list.appendChild(card);
    });
}

// Handle assignment form submission
async function handleAssignmentSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    // Log the form data for debugging
    console.log('Form data:');
    for (let pair of formData.entries()) {
        console.log(pair[0], pair[1]);
    }
    
    try {
        const response = await fetch('../api/assignments.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalCreate')).hide();
            loadAssignments();
            alert('Assignment saved successfully!');
        } else {
            const error = await response.text();
            alert('Error: ' + error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while saving the assignment.');
    }
}

// View assignment details
function viewAssignment(id) {
    console.log('Viewing assignment:', id);
    
    fetch(`../api/assignments.php?id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch assignment details');
            }
            return response.json();
        })
        .then(assignment => {
            console.log('Assignment data:', assignment);
            
            if (!assignment || typeof assignment !== 'object') {
                throw new Error('Invalid assignment data received');
            }
            
            // Set title and description
            document.getElementById('viewTitle').textContent = assignment.title || 'Untitled Assignment';
            document.getElementById('viewDesc').textContent = assignment.description || 'No description provided';
            
            // Set due date
            const dueElement = document.getElementById('viewDue');
            if (assignment.due_date) {
                try {
                    const dueDate = new Date(assignment.due_date);
                    if (!isNaN(dueDate.getTime())) {
                        dueElement.textContent = dueDate.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    } else {
                        throw new Error('Invalid date');
                    }
                } catch (e) {
                    console.error('Date parsing error:', e);
                    dueElement.textContent = assignment.due_date;
                }
            } else {
                dueElement.textContent = 'No due date set';
            }
            
            // Set file attachment
            const fileElement = document.getElementById('viewFile');
            if (assignment.file_path) {
                const fileName = assignment.file_path.split('/').pop();
                const fileLink = `<a href="../${assignment.file_path}" target="_blank" class="text-primary">
                    <i class="fas fa-download me-1"></i>${fileName}</a>`;
                fileElement.innerHTML = fileLink;
            } else {
                fileElement.textContent = 'No file attached';
            }
            
            // Update statistics
            if (assignment.statistics) {
                document.getElementById('statsTotal').textContent = assignment.statistics.total_submissions || 0;
                document.getElementById('statsGraded').textContent = assignment.statistics.graded_submissions || 0;
                document.getElementById('statsPending').textContent = assignment.statistics.pending_submissions || 0;
            } else {
                document.getElementById('statsTotal').textContent = '0';
                document.getElementById('statsGraded').textContent = '0';
                document.getElementById('statsPending').textContent = '0';
            }
            
            // Store current assignment ID for submission viewing
            window.currentAssignmentId = id;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('modalView'));
            modal.show();
        })
        .catch(error => {
            console.error('Error loading assignment:', error);
            alert('Error loading assignment details. Please try again.');
        });
}

// Edit assignment
function editAssignment(id) {
    console.log('Editing assignment:', id); // Debug log
    fetch(`../api/assignments.php?id=${id}`)
        .then(response => response.json())
        .then(assignment => {
            console.log('Assignment data:', assignment); // Debug log
            // Make sure we're setting the assignment ID for the edit
            document.getElementById('assignId').value = id;
            document.getElementById('inpTitle').value = assignment.title || '';
            document.getElementById('inpDesc').value = assignment.description || '';
            // Format the date to YYYY-MM-DD format for the input field
            if (assignment.due_date) {
                const dueDate = new Date(assignment.due_date);
                const formattedDate = dueDate.toISOString().split('T')[0];
                document.getElementById('inpDue').value = formattedDate;
            }
            document.getElementById('modalCreateTitle').textContent = 'Edit Assignment';
            const modal = new bootstrap.Modal(document.getElementById('modalCreate'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading assignment details. Please try again.');
        });
}

// View submissions
let allSubmissions = []; // Store all submissions globally

function filterSubmissions() {
    const searchInput = document.getElementById('searchSubmissions').value.toLowerCase();
    const filterValue = document.getElementById('filterSubmissions').value;
    
    let filteredSubmissions = allSubmissions.filter(submission => {
        // Apply search filter
        const matchesSearch = submission.student_name.toLowerCase().includes(searchInput);
        
        // Apply status filter
        let matchesFilter = true;
        if (filterValue === 'graded') {
            matchesFilter = submission.grade !== null;
        } else if (filterValue === 'pending') {
            matchesFilter = submission.grade === null;
        }
        
        return matchesSearch && matchesFilter;
    });
    
    renderSubmissions(filteredSubmissions);
}

function renderSubmissions(submissions) {
    const container = document.getElementById('submissionsList');
    container.innerHTML = '';

    if (submissions.length === 0) {
        container.innerHTML = '<div class="text-muted p-3">No submissions found.</div>';
        return;
    }

    submissions.forEach(submission => {
        const row = document.createElement('div');
        row.className = 'border-bottom p-3';
        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold">${escapeHtml(submission.student_name)}</div>
                    <div class="text-muted">Submitted: ${formatDateTime(submission.submitted_at)}</div>
                    <div class="mt-1">
                        ${submission.file_path ? `
                            <a href="../${submission.file_path}" class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download me-1"></i>Download Submission
                            </a>
                        ` : 'No file attached'}
                    </div>
                </div>
                <div style="min-width: 320px;">
                    <form class="grading-form" data-submission-id="${submission.id}">
                        <div class="d-flex gap-2">
                            <input type="number" class="form-control form-control-sm" 
                                   placeholder="Grade" value="${submission.grade || ''}" 
                                   name="grade" min="0" max="100" style="width: 90px;">
                            <input type="text" class="form-control form-control-sm" 
                                   placeholder="Feedback" value="${escapeHtml(submission.feedback || '')}" 
                                   name="feedback">
                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        container.appendChild(row);
        
        // Add form submit handler
        setupGradingForm(row.querySelector('.grading-form'));
    });
}

function setupGradingForm(form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('submission_id', form.dataset.submissionId);
        
        try {
            const response = await fetch('../api/grade_submission.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                // Update the submission in allSubmissions array
                const submissionId = form.dataset.submissionId;
                const grade = formData.get('grade');
                const feedback = formData.get('feedback');
                
                const submission = allSubmissions.find(s => s.id === submissionId);
                if (submission) {
                    submission.grade = grade;
                    submission.feedback = feedback;
                }
                
                alert('Grade saved successfully!');
            } else {
                alert('Error saving grade.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while saving the grade.');
        }
    });
}

function viewSubmissions(assignmentId) {
    fetch(`../api/submissions.php?assignment_id=${assignmentId}`)
        .then(response => response.json())
        .then(submissions => {
            allSubmissions = submissions; // Store all submissions
            const container = document.getElementById('submissionsList');
            container.innerHTML = '';

            if (submissions.length === 0) {
                container.innerHTML = '<div class="text-muted p-3">No submissions yet.</div>';
                new bootstrap.Modal(document.getElementById('modalSubmissions')).show();
                return;
            }

            submissions.forEach(submission => {
                const row = document.createElement('div');
                row.className = 'border-top p-3';
                row.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold">${escapeHtml(submission.student_name)}</div>
                            <div class="text-muted">Submitted: ${formatDateTime(submission.submitted_at)}</div>
                            <div class="text-muted">File: ${escapeHtml(submission.file_path || '—')}</div>
                        </div>
                        <div style="min-width: 320px;">
                            <form class="grading-form" data-submission-id="${submission.id}">
                                <div class="d-flex gap-2">
                                    <input type="number" class="form-control form-control-sm" 
                                           placeholder="Grade" value="${submission.grade || ''}" 
                                           name="grade" style="width: 90px;">
                                    <input type="text" class="form-control form-control-sm" 
                                           placeholder="Feedback" value="${escapeHtml(submission.feedback || '')}" 
                                           name="feedback">
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                `;

                // Add grading form handler
                const form = row.querySelector('.grading-form');
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('submission_id', submission.id);
                    
                    try {
                        const response = await fetch('../api/grade_submission.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            alert('Grade saved successfully!');
                        } else {
                            alert('Error saving grade.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while saving the grade.');
                    }
                });

                container.appendChild(row);
            });

            new bootstrap.Modal(document.getElementById('modalSubmissions')).show();
        })
        .catch(error => console.error('Error:', error));
}

// Delete assignment
function deleteAssignment(id) {
    if (!confirm('Delete this assignment and all related submissions?')) return;

    fetch(`../api/assignments.php?id=${id}`, {
        method: 'DELETE'
    })
    .then(response => {
        if (response.ok) {
            loadAssignments();
            alert('Assignment deleted successfully!');
        } else {
            alert('Error deleting assignment.');
        }
    })
    .catch(error => console.error('Error:', error));
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
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    // Check if date is valid
    if (isNaN(date.getTime())) return '—';
    // Format: Month DD, YYYY
    return date.toLocaleDateString('en-US', { 
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    // Check if date is valid
    if (isNaN(date.getTime())) return '—';
    // Format: Month DD, YYYY, HH:MM AM/PM
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}