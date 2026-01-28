const sideMenu = document.querySelector("aside");
const menuBtn = document.querySelector("#menu-btn");
const closeBtn = document.querySelector("#close-btn");
const themeToggler = document.querySelector(".theme-toggler");

// Show/hide side menu
menuBtn.addEventListener('click', () => {
    sideMenu.style.display = 'block';
});
closeBtn.addEventListener('click', () => {
    sideMenu.style.display = 'none';
});

// Apply saved theme on load
window.addEventListener('DOMContentLoaded', () => {
    const themeToggler = document.querySelector(".theme-toggler");

    // Check saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme-variables');
        if (themeToggler) {
            themeToggler.querySelector('span:nth-child(1)').classList.remove('active');
            themeToggler.querySelector('span:nth-child(2)').classList.add('active');
        }
    }

    // Toggle theme
    if (themeToggler) {
        themeToggler.addEventListener('click', () => {
            const isDark = document.body.classList.toggle('dark-theme-variables');
            themeToggler.querySelector('span:nth-child(1)').classList.toggle('active');
            themeToggler.querySelector('span:nth-child(2)').classList.toggle('active');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            console.log("Theme set to:", isDark ? 'dark' : 'light');
        });
    }
});

