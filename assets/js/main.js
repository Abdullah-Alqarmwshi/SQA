// Profile dropdown functionality
function toggleDropdown() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Close dropdown when clicking outside
window.addEventListener('click', function(e) {
    if (!e.target.closest('.user-info')) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown) {
            dropdown.classList.remove('active');
        }
    }
});

// Stop propagation when clicking inside user-info to prevent immediate closing
document.addEventListener('DOMContentLoaded', function() {
    const userInfo = document.querySelector('.user-info');
    if (userInfo) {
        userInfo.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
