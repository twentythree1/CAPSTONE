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

document.addEventListener('DOMContentLoaded', () => {
  const scheduleDropdown = document.querySelector(".sidebar-dropdown");
  if (!scheduleDropdown) return;

  const toggle = scheduleDropdown.querySelector(".dropdown-toggle");
  const menu = scheduleDropdown.querySelector(".dropdown-menu");
  if (!toggle || !menu) return;

  // Helper: detect whether current page is part of schedules area
  const isSchedulesPage = () => {
    const p = window.location.pathname.toLowerCase();
    // adjust checks if your schedules pages live in a different path
    return p.includes('/schedules/') || p.endsWith('/schedule.php') || p.includes('schedule.php');
  };

  // Ensure initial closed state (unless server-side already marked open)
  if (!scheduleDropdown.classList.contains('open')) {
    menu.style.display = 'none';
    toggle.setAttribute('aria-expanded', 'false');
  } else {
    // If server-side added 'open' (because ?status=...), keep menu visible
    menu.style.display = 'flex';
    toggle.setAttribute('aria-expanded', 'true');
  }

  // Toggle when user clicks the dropdown toggle
  toggle.addEventListener('click', (ev) => {
    ev.preventDefault();
    scheduleDropdown.classList.toggle('open');
    const opened = scheduleDropdown.classList.contains('open');
    menu.style.display = opened ? 'flex' : 'none';
    toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');

    // Persist that the user explicitly opened it. Remove when closed.
    if (opened) {
      localStorage.setItem('schedulesDropdownOpen', '1');
    } else {
      localStorage.removeItem('schedulesDropdownOpen');
    }
  });

  // Restore open state only when on a schedules page (prevents opening on other pages)
  const saved = localStorage.getItem('schedulesDropdownOpen');
  if (saved && isSchedulesPage()) {
    scheduleDropdown.classList.add('open');
    menu.style.display = 'flex';
    toggle.setAttribute('aria-expanded', 'true');
  }

  // Highlight active link based on ?status=... and keep link logic (unchanged)
  const normalize = s => (s || '').toString().replace(/\s+/g, ' ').trim().toLowerCase();
  const params = new URLSearchParams(window.location.search);
  const urlStatus = normalize(params.get('status'));

  Array.from(menu.querySelectorAll('a')).forEach(a => {
    let hrefStatus = '';
    try {
      hrefStatus = normalize((new URL(a.href, window.location.origin)).searchParams.get('status'));
    } catch (e) {
      // fallback for relative links
      const parts = (a.getAttribute('href') || '').split('?')[1] || '';
      hrefStatus = normalize((new URL('?' + parts, window.location.origin)).searchParams.get('status'));
    }

    if (urlStatus && hrefStatus === urlStatus) {
      a.classList.add('active');
      // If URL has a status and we are on a non-schedules page, we still want the submenu visible.
      scheduleDropdown.classList.add('open');
      menu.style.display = 'flex';
      toggle.setAttribute('aria-expanded', 'true');
    } else {
      a.classList.remove('active');
    }

    // Save user's last chosen status into localStorage only when they click a menu item.
    a.addEventListener('click', () => {
      const st = hrefStatus || urlStatus;
      if (st) localStorage.setItem('schedulesStatus', st);
    });
  });
});