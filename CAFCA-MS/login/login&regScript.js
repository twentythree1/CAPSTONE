const container = document.querySelector('.container');
const registerBtn = document.querySelector('.register-btn');
const loginBtn = document.querySelector('.login-btn');
const errorMessages = document.querySelectorAll('.error-message');
const successMessages = document.querySelectorAll('.success-message');

// Toggle manually
registerBtn.addEventListener('click', () => {
    container.classList.add('active');
});

loginBtn.addEventListener('click', () => {
    container.classList.remove('active');
});

// Handle initial form based on PHP logic, display whether registration form or log in form
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initialForm !== 'undefined' && initialForm === 'register') {
        container.classList.add('active');
    } else {
        container.classList.remove('active');
    }
});

// Auto-hide error message
document.addEventListener('DOMContentLoaded', function() {
    errorMessages.forEach(function(errorMessage) {
        setTimeout(() => {
            errorMessage.style.opacity = '0';
            errorMessage.style.transition = 'opacity 1s ease';
            
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 1000);
        }, 4500);
    });
});


// Auto-hide success message
document.addEventListener('DOMContentLoaded', function() {
    successMessages.forEach(function(successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            successMessage.style.transition = 'opacity 1s ease';
            
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 1000);
        }, 4500);
    });
});