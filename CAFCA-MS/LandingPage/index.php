<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Home</title>
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
    <!-- fontawesome link -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- swiperjs css -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="indexstyle.css">
</head>

<body>

    <header>
        <nav class="navbar section-content">
            <a href="#home" class="nav-logo">
                <h1 class="logo-text">CAFCA</h1>
            </a>
            <ul class="nav-menu">
                <button id="menu-close-button" class="fas fa-times"></button>

                <li class="nav-item">
                    <a href="#home" class="nav-link">Home</a>
                </li>
                <li class="nav-item">
                    <a href="#about" class="nav-link">About</a>
                </li>
                <li class="nav-item">
                    <a href="#gallery" class="nav-link">Officers</a>
                </li>
                <li class="nav-item">
                    <a href="#developers" class="nav-link">Developers</a>
                </li>
                <li class="nav-item">
                    <a href="#members" class="nav-link">Board of Director</a>
                </li>
                <li class="nav-item">
                    <a href="/CAFCA-MS/login/logindex.php" class="nav-link">Log in</a>
                </li>
            </ul>

            <button id="menu-open-button" class="fas fa-bars"></button>
        </nav>
    </header>

    <!-- Main -->
    <main>
        <section class="hero-section" id="home">
            <div class="section-content">
                <div class="hero-details">
                    <h3 class="subtitle1">Welcome to the </h3>
                    <h1 class="title">Calubang Farmer's Cluster Association</h1>
                    <h3 class="subtitle">Management System</h3>
                    <p class="description">a community united by a shared passion for sustainable farming, innovation,
                        and growth. We are committed to empowering local farmers, promoting modern agricultural
                        practices, and building a stronger, self-reliant future for our community.</p>
                </div>
                <div class="hero-image-wrapper">
                    <img src="others/logo.png" class="hero-image">
                </div>
            </div>
        </section>
    </main>

    <!-- About -->
    <section class="about-section" id="about">
        <div class="section-content">
            <div class="about-image-wrapper">
                <img src="/CAFCA-MS/LandingPage/others/aboutimg.jpg" alt="About" class="about-image">
            </div>
            <div class="about-details">
                <h2 class="section-title">ABOUT US</h2>
                <p class="text">The Calubang Farmer's Cluster Association is a collective of dedicated farmers working
                    together to strengthen agricultural development in our community. Through collaboration, education,
                    and support, we aim to uplift the lives of our members, promote sustainable farming practices, and
                    ensure food security for generations to come.</p>
            </div>
        </div>
    </section>

    <!-- gallery -->
    <section class="gallery-section" id="gallery">
        <h2 class="section-title">CAFCA OFFICERS 2025</h2>
        <div class="section-content">
            <ul class="gallery-list">
                <li class="gallery-info">
                    <img src="/CAFCA-MS/LandingPage/others/sampleimage(gallery).jpg" alt="gallery-1" class="farm-image">
                    <h3 class="name">Dandy V. Balinas</h3>
                    <p class="text">CHAIRMAN</p>
                </li>
                <li class="gallery-info">
                    <img src="/CAFCA-MS/LandingPage/others/sampleimage(gallery).jpg" alt="gallery-3" class="farm-image">
                    <h3 class="name">Efren V. Alvior</h3>
                    <p class="text">VICE CHAIRMAN</p>
                </li>
                <li class="gallery-info">
                    <img src="/CAFCA-MS/LandingPage/others/sampleimage(gallery).jpg" alt="gallery-2" class="farm-image">
                    <h3 class="name">Ofelia A. Aguilos</h3>
                    <p class="text">SECRETARY</p>
                </li>
                <li class="gallery-info">
                    <img src="/CAFCA-MS/LandingPage/others/sampleimage(gallery).jpg" alt="gallery-4" class="farm-image">
                    <h3 class="name">Maylen G. Familara</h3>
                    <p class="text">TREASURER</p>
                </li>
            </ul>
        </div>
    </section>

    <!-- developer -->
    <section class="developer-section" id="developers">
        <h2 class="section-title">CAFCA-MS Developers</h2>
        <div class="section-content">
            <div class="slider-container swiper">
                <div class="slider-wrapper">
                    <ul class="developer-list swiper-wrapper">
                        <li class="developer swiper-slide">
                            <img src="/CAFCA-MS/LandingPage/others/dev1.jpg" alt="devs" class="dev-image">
                            <h3 class="name">Jan Laurence Tan</h3>
                            <i class="description">BSIT 3-C STUDENT</i>
                        </li>
                        <li class="developer swiper-slide">
                            <img src="/CAFCA-MS/LandingPage/others/dev2.jpg" alt="devs" class="dev-image">
                            <h3 class="name">Shantal Mae Lee</h3>
                            <i class="description">BSIT 3-C STUDENT</i>
                        </li>
                        <li class="developer swiper-slide">
                            <img src="/CAFCA-MS/LandingPage/others/dev3.jpg" alt="devs" class="dev-image">
                            <h3 class="name">Leahna Mae Antiquin</h3>
                            <i class="description">BSIT 3-C STUDENT</i>
                        </li>
                        <li class="developer swiper-slide">
                            <img src="/CAFCA-MS/LandingPage/others/dev4.jpg" alt="devs" class="dev-image">
                            <h3 class="name">Judjel Delos Reyes</h3>
                            <i class="description">BSIT 3-C STUDENT</i>
                        </li>
                    </ul>

                    <div class="swiper-pagination"></div>
                    <div class="swiper-slide-button swiper-button-prev"></div>
                    <div class="swiper-slide-button swiper-button-next"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- members -->
    <section class="member-section" id="members">
        <h2 class="section-title">BOARD OF DIRECTOR</h2>
        <div class="section-content">
            <div class="slider-container swiper">
                <div class="slider-wrapper">
                    <ul class="member-list swiper-wrapper">
                        <li class="member swiper-slide">
                            <img src="others/aboutimg.jpg" alt="#" class="member-img">
                            <h3 class="name">Ifor V. Alvior</h3>
                        </li>
                        <li class="member swiper-slide">
                            <img src="others/aboutimg.jpg" alt="#" class="member-img">
                            <h3 class="name">Elmar G. Santoyo</h3>
                        </li>
                        <li class="member swiper-slide">
                            <img src="others/aboutimg.jpg" alt="#" class="member-img">
                            <h3 class="name">Viola A. Samiliano</h3>
                        </li>
                        <li class="member swiper-slide">
                            <img src="others/aboutimg.jpg" alt="#" class="member-img">
                            <h3 class="name">Wilfredo T. Gayamo</h3>
                        </li>
                        <li class="member swiper-slide">
                            <img src="others/aboutimg.jpg" alt="#" class="member-img">
                            <h3 class="name">Robert T. Tejero Sr.</h3>
                        </li>
                    </ul>

                    <div class="swiper-pagination"></div>
                    <div class="swiper-slide-button swiper-button-prev"></div>
                    <div class="swiper-slide-button swiper-button-next"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- footer -->
    <footer class="footer-section">
        <div class="section-content">
            <p class="cafca-acronym">Republic of the Philippines</p>
            <p class="cafca-acronym"> • </p>
            <p class="cafca-acronym">Province of Negros Occidental</p>
            <p class="cafca-acronym"> • </p>
            <p class="cafca-acronym">Municipality of Ilog</p>
            <p class="cafca-acronym"> • </p>
            <p class="cafca-acronym">Barangay Calubang</p>
            <p class="cafca-acronym"> • </p>
            <p class="cafca-acronym">CALUBANG FARMER'S CLUSTER ASSOCIATION</p>
        </div>
    </footer>

    <!-- swiper script -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- custom script -->
    <script src="/CAFCA-MS/LandingPage/hpscript.js"></script>
</body>

</html>