const menuOpenButton = document.getElementById("menu-open-button");
const menuCloseButton = document.getElementById("menu-close-button");
const backToTopButton = document.getElementById("back-to-top-btn");
const homeSection = document.getElementById("home");

window.addEventListener("scroll", () => {
    const heroBottom = homeSection.offsetHeight;

    if (window.scrollY < heroBottom) {
        backToTopButton.style.display = "none";
    } else {
        backToTopButton.style.display = "block";
    }
});


menuOpenButton.addEventListener("click", () => {
    document.body.classList.toggle("show-mobile-menu");
});

menuCloseButton.addEventListener("click", () => menuOpenButton.click());

document.addEventListener("click", (z) => {
    if (!z.target.closest("nav") && document.body.classList.contains("show-mobile-menu")) {
        document.body.classList.remove("show-mobile-menu");
    }
});

const swiper = new Swiper('.slider-wrapper', {
    loop: true,
    grabCursor: true,
    spaceBetween: 25,

    // If we need pagination
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
        dynamicBullets: true,
    },

    // Navigation arrows
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    },

    breakpoints: {
        0: {
            slidesPerView: 1
        },
        900: {
            slidesPerView: 2
        },
        1024: {
            slidesPerView: 3
        },
    }
});