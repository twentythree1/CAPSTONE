// ── Password visibility toggle (global so data-target wiring works) ──
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bx-show', 'bx-hide');
    } else {
        input.type = 'password';
        icon.classList.replace('bx-hide', 'bx-show');
    }
}

document.addEventListener('DOMContentLoaded', () => {

    // ── Remove no-transition class after first paint ────────────
    // (prevents the green panel from animating on page load)
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const c = document.getElementById('mainContainer');
            if (c) c.classList.remove('no-transition');
        });
    });

    // ── Toggle panels ───────────────────────────────────────────
    const container   = document.getElementById('mainContainer');
    const registerBtn = document.querySelector('.register-btn');
    const loginBtn    = document.querySelector('.login-btn');

    if (registerBtn) registerBtn.addEventListener('click', () => container.classList.add('active'));
    if (loginBtn)    loginBtn.addEventListener('click',    () => container.classList.remove('active'));

    // ── Wire show/hide password buttons ────────────────────────
    document.querySelectorAll('.toggle-pass').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            togglePass(this.dataset.target, this);
        });
    });

    // ── Password strength meter ─────────────────────────────────
    const regPassInput = document.getElementById('regPassword');
    if (regPassInput) {
        regPassInput.addEventListener('input', function () {
            const val   = this.value;
            const fill  = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');

            let score = 0;
            if (val.length >= 8)           score++;
            if (/[A-Z]/.test(val))         score++;
            if (/[0-9]/.test(val))         score++;
            if (/[^A-Za-z0-9]/.test(val))  score++;

            const levels = [
                { pct: '0%',   cls: '',       text: 'Password strength' },
                { pct: '25%',  cls: 'weak',   text: 'Weak'   },
                { pct: '50%',  cls: 'fair',   text: 'Fair'   },
                { pct: '75%',  cls: 'good',   text: 'Good'   },
                { pct: '100%', cls: 'strong', text: 'Strong' },
            ];

            const lvl     = val.length === 0 ? levels[0] : levels[score];
            fill.style.width  = lvl.pct;
            fill.className    = 'bar-fill ' + lvl.cls;
            label.textContent = lvl.text;
            label.className   = 'strength-label ' + lvl.cls;
        });
    }

    // ── Flash message auto-dismiss ──────────────────────────────
    document.querySelectorAll('.flash-message').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            el.style.opacity    = '0';
            el.style.transform  = 'translateY(-10px) translateX(-50%)';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });

    // ── Floating leaf particles ─────────────────────────────────
    const particleContainer = document.getElementById('particles');
    if (particleContainer) {
        for (let i = 0; i < 18; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.cssText = `
                left:               ${Math.random() * 100}vw;
                width:              ${6  + Math.random() * 10}px;
                height:             ${6  + Math.random() * 10}px;
                animation-delay:    ${Math.random() * 12}s;
                animation-duration: ${8  + Math.random() * 10}s;
                opacity:            ${0.15 + Math.random() * 0.35};
            `;
            particleContainer.appendChild(p);
        }
    }

});