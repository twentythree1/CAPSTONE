const sideMenu = document.querySelector("aside");
const menuBtn = document.querySelector("#menu-btn");
const closeBtn = document.querySelector("#close-btn");
const themeToggler = document.querySelector(".theme-toggler");

menuBtn.addEventListener('click', () => {
    sideMenu.style.display = 'block';
});
closeBtn.addEventListener('click', () => {
    sideMenu.style.display = 'none';
});

window.addEventListener('DOMContentLoaded', () => {
    const themeToggler = document.querySelector(".theme-toggler");

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme-variables');
        if (themeToggler) {
            themeToggler.querySelector('span:nth-child(1)').classList.remove('active');
            themeToggler.querySelector('span:nth-child(2)').classList.add('active');
        }
    }

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

// Wait for the DOM to be fully loaded before attaching event listeners
document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = document.querySelectorAll(".sidebar-dropdown");
    
    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector(".dropdown-toggle");
        const menu = dropdown.querySelector(".dropdown-menu");
        if (!toggle || !menu) return;

        const icon = toggle.querySelector(".material-icons-sharp");
        let dropdownType = 'unknown';
        if (icon) {
            if (icon.textContent.trim() === 'event') {
                dropdownType = 'schedules';
            } else if (icon.textContent.trim() === 'agriculture') {
                dropdownType = 'machines';
            }
        }

        if (!dropdown.classList.contains('open')) {
            menu.style.display = 'none';
            toggle.setAttribute('aria-expanded', 'false');
        } else {
            menu.style.display = 'flex';
            toggle.setAttribute('aria-expanded', 'true');
        }

        toggle.addEventListener('click', (ev) => {
            ev.preventDefault();
            
            dropdowns.forEach((otherDropdown) => {
                if (otherDropdown !== dropdown && otherDropdown.classList.contains('open')) {
                    const otherMenu = otherDropdown.querySelector(".dropdown-menu");
                    const otherToggle = otherDropdown.querySelector(".dropdown-toggle");
                    otherDropdown.classList.remove('open');
                    if (otherMenu) otherMenu.style.display = 'none';
                    if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
                }
            });

            dropdown.classList.toggle('open');
            const opened = dropdown.classList.contains('open');
            menu.style.display = opened ? 'flex' : 'none';
            toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');

            if (opened) {
                localStorage.setItem(`${dropdownType}DropdownOpen`, '1');
            } else {
                localStorage.removeItem(`${dropdownType}DropdownOpen`);
            }
        });

        // Normalize function to compare status values (handles null, undefined, and trims whitespace)
        const normalize = s => (s || '').toString().replace(/\s+/g, ' ').trim().toLowerCase();
        const params = new URLSearchParams(window.location.search);
        const urlStatus = normalize(params.get('status'));

        const currentPath = window.location.pathname.toLowerCase();
        const isSchedulesPage = currentPath.includes('/schedules/') || currentPath.endsWith('/schedule.php') || currentPath.includes('schedule.php');
        const isMachinesPage = currentPath.includes('/machines/') || currentPath.endsWith('/machine.php') || currentPath.includes('machine.php');

        let shouldOpen = false;

        Array.from(menu.querySelectorAll('a')).forEach(a => {
            let hrefStatus = '';
            try {
                hrefStatus = normalize((new URL(a.href, window.location.origin)).searchParams.get('status'));
            } catch (e) {
                const parts = (a.getAttribute('href') || '').split('?')[1] || '';
                hrefStatus = normalize((new URL('?' + parts, window.location.origin)).searchParams.get('status'));
            }

            if (urlStatus && hrefStatus === urlStatus) {
                a.classList.add('active');
                shouldOpen = true;
            } else {
                a.classList.remove('active');
            }

            a.addEventListener('click', () => {
                const st = hrefStatus || urlStatus;
                if (st && dropdownType !== 'unknown') {
                    localStorage.setItem(`${dropdownType}Status`, st);
                }
            });
        });

        // Check localStorage for dropdown open state if URL doesn't match
        if (shouldOpen) {
            dropdown.classList.add('open');
            menu.style.display = 'flex';
            toggle.setAttribute('aria-expanded', 'true');
        } else {
            const savedState = localStorage.getItem(`${dropdownType}DropdownOpen`);
            if (savedState && 
                ((dropdownType === 'schedules' && isSchedulesPage) || 
                 (dropdownType === 'machines' && isMachinesPage))) {
                dropdown.classList.add('open');
                menu.style.display = 'flex';
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    });
});