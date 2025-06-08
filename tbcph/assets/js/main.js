// Main JavaScript file for TBCPH

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
});

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            showError(field, 'This field is required');
        } else {
            field.classList.remove('error');
            removeError(field);
        }
    });

    return isValid;
}

// Show error message
function showError(field, message) {
    const errorDiv = field.nextElementSibling;
    if (errorDiv && errorDiv.classList.contains('error-message')) {
        errorDiv.textContent = message;
    } else {
        const div = document.createElement('div');
        div.className = 'error-message';
        div.textContent = message;
        field.parentNode.insertBefore(div, field.nextSibling);
    }
}

// Remove error message
function removeError(field) {
    const errorDiv = field.nextElementSibling;
    if (errorDiv && errorDiv.classList.contains('error-message')) {
        errorDiv.remove();
    }
}

// Busker Filter
function filterBuskers() {
    const genre = document.getElementById('genre-filter').value;
    const buskerCards = document.querySelectorAll('.busker-card');

    buskerCards.forEach(card => {
        const cardGenre = card.dataset.genre;
        if (genre === 'all' || cardGenre === genre) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
}); 