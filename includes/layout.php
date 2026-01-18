<?php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

function render_header(string $title): void {
    $lang   = get_lang();
    $isAuth = is_logged_in();

    echo '<!DOCTYPE html><html lang="'.e($lang).'"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.e($title).'</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    echo '<link rel="stylesheet" href="assets/dsfr-lite.css">';

    /* =======================
       CSS GLOBAL + RESPONSIVE
       ======================= */
    echo '<style>';
    echo '*,*::before,*::after{box-sizing:border-box;}';
    echo 'html,body{height:100%;}';
    echo 'body.layout{min-height:100vh;display:flex;flex-direction:column;}';

    /* Contenu central */
    echo 'main.container{flex:1;width:100%;max-width:1100px;margin:0 auto;padding:16px;}';
    echo 'img,video,canvas,svg{max-width:100%;height:auto;}';

    /* Header desktop */
    echo '.topbar{display:flex;align-items:center;justify-content:space-between;padding:8px 16px;}';
    echo '.brand a{text-decoration:none;font-weight:700;font-size:1.1rem;}';
    echo '.nav{display:flex;align-items:center;gap:12px;}';

    /* Footer collé en bas */
    echo '.footer{margin-top:auto;padding:16px;}';

    /* ===== Barre mobile ===== */
    echo '.mbar{display:none;align-items:center;width:100%;}';
    echo '.mbar__left,.mbar__center,.mbar__right{display:flex;align-items:center;}';
    echo '.mbar__center{flex:1;justify-content:center;}';
    echo '.mbar__right{gap:6px;}';
    echo '.mbar__title{text-decoration:none;font-weight:700;}';

    /* Bouton hamburger */
    echo '.menu-btn{background:none;border:0;cursor:pointer;padding:8px;solid rgba(0,0,0,.1);}';
    echo '.burger{width:22px;height:16px;position:relative;}';
    echo '.burger span{position:absolute;left:0;right:0;height:2px;background:currentColor;border-radius:2px;}';
    echo '.burger span:nth-child(1){top:0;}';
    echo '.burger span:nth-child(2){top:7px;}';
    echo '.burger span:nth-child(3){top:14px;}';

    /* Menu mobile déroulant */
    echo '.mnav{display:none;position:fixed;top:56px;left:0;right:0;background:#fff;';
    echo 'border-top:1px solid rgba(0,0,0,.1);box-shadow:0 10px 20px rgba(0,0,0,.1);z-index:1000;}';
    echo '.mnav.open{display:block;}';
    echo '.mnav__inner{max-width:1100px;margin:0 auto;padding:12px 16px;display:flex;flex-direction:column;gap:10px;}';
    echo '.mnav a{text-decoration:none;padding:10px;border-radius:8px;}';
    echo '.mnav a:hover{background:rgba(0,0,0,.05);}';

    /* ===== MOBILE ≤ 900px ===== */
    echo '@media (max-width:900px){';
    echo '  .brand,.nav{display:none;}';
    echo '  .mbar{display:flex;}';
    echo '  main.container{padding:12px;}';
    echo '}';
    echo '</style>';

    echo '</head><body class="layout">';

    /* =======================
       HEADER
       ======================= */
    echo '<header class="topbar">';

    /* Barre mobile : ☰ | titre | langue */
    echo '<div class="mbar">';
    echo '  <div class="mbar__left">';
    echo '    <button class="menu-btn" onclick="MYC_toggleMenu()" aria-label="Menu">';
    echo '      <span class="burger">☰</span>';
    echo '    </button>';
    echo '  </div>';
    echo '  <div class="mbar__center"><a class="mbar__title" href="index.php">MakeYourCount</a></div>';
    echo '  <div class="mbar__right">';
    echo '    <a href="?lang=fr">FR</a> | <a href="?lang=en">EN</a>';
    echo '  </div>';
    echo '</div>';

    /* Header desktop */
    echo '<div class="brand"><a href="index.php">MakeYourCount</a></div>';
    echo '<nav class="nav">';
    echo '  <a href="?lang=fr">FR</a> | <a href="?lang=en">EN</a> · ';
    if ($isAuth) {
        echo '<a href="dashboard.php">'.e(t("dashboard")).'</a>';
        echo '<a href="logout.php">'.e(t("logout")).'</a>';
    } else {
        echo '<a href="login.php">'.e(t("login")).'</a>';
        echo '<a href="register.php">'.e(t("register")).'</a>';
    }
    echo '</nav>';
    echo '</header>';

    /* Menu mobile */
    echo '<div id="mobile-nav" class="mnav">';
    echo '  <div class="mnav__inner">';
    if ($isAuth) {
        echo '<a href="dashboard.php">'.e(t("dashboard")).'</a>';
        echo '<a href="logout.php">'.e(t("logout")).'</a>';
    } else {
        echo '<a href="login.php">'.e(t("login")).'</a>';
        echo '<a href="register.php">'.e(t("register")).'</a>';
    }
    echo '  </div>';
    echo '</div>';

    /* JS menu mobile */
    echo '<script>
        function MYC_toggleMenu(){
            document.getElementById("mobile-nav").classList.toggle("open");
        }
        document.addEventListener("click", function(e){
            const menu = document.getElementById("mobile-nav");
            const btn  = document.querySelector(".menu-btn");
            if(menu.classList.contains("open") && !menu.contains(e.target) && !btn.contains(e.target)){
                menu.classList.remove("open");
            }
        });
    </script>';

    echo '<main class="container">';
}

function render_footer(): void {
    echo '</main>';
    echo '<footer class="footer">© '.date('Y').' MakeYourCount</footer>';
    echo '<script src="assets/password_validation.js" defer></script>';
    echo '</body></html>';
}
