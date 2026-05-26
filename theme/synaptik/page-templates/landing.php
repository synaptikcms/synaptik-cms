<?php
/* Template Name: Landing Page 
   */
/* Template Description: Full-width landing with animated botanical illustration */

/**
 * EDIT GUIDE — tout ce qui est personnalisable est marqué // EDIT
 *
 * 1. Couleurs       → variables CSS au début du <style> (section VARIABLES)
 * 2. Hero           → $hero_tag, $hero_subtitle, $hero_cta* (lignes EDIT ci-dessous)
 * 3. Features cards → tableau $features
 * 4. CTA final      → $cta_title, $cta_sub, $cta_btn*
 * 5. SVG arbre      → groupe <g id="tree-*"> — épaisseur/couleur des feuilles
 * 6. Timings anim   → variables --speed-* dans :root
 */

// ── EDIT: hero & CTA text ─────────────────────────────────────────────────
$hero_tag      = 'Naturopathie &amp; Vitalité';           // EDIT — petit label au-dessus du titre
$hero_subtitle = 'Une approche naturelle et holistique<br>pour retrouver l\'équilibre corps &amp; esprit.';  // EDIT
$hero_cta_url  = getBaseUrl() . 'contact';                // EDIT — URL bouton primaire
$hero_cta_lbl  = 'Prendre rendez-vous';                   // EDIT
$hero_ghost_url = '#landing-content';                     // EDIT — URL bouton secondaire
$hero_ghost_lbl = 'Découvrir ↓';                          // EDIT

$cta_title = 'Prêt à commencer votre parcours ?';        // EDIT
$cta_sub   = 'Premier bilan offert · Sans engagement';    // EDIT
$cta_btn_url = getBaseUrl() . 'contact';                  // EDIT
$cta_btn_lbl = 'Prendre rendez-vous';                     // EDIT

// ── EDIT: feature cards ───────────────────────────────────────────────────
$features = [
    [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z" stroke-linecap="round"/><path d="M12 6C8.69 6 6 8.69 6 12" stroke-linecap="round"/></svg>',
        'title' => 'Approche naturelle',
        'desc'  => 'Des solutions issues du vivant pour soutenir les mécanismes d\'auto-guérison et la vitalité durable.',
    ],
    [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3" stroke-linecap="round"/></svg>',
        'title' => 'Suivi personnalisé',
        'desc'  => 'Un accompagnement sur-mesure adapté à votre terrain, votre rythme et vos objectifs de santé.',
    ],
    [
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/><path d="M8 12h8M12 8v8" stroke-linecap="round"/></svg>',
        'title' => 'Équilibre global',
        'desc'  => 'Corps, esprit et environnement traités en harmonie — une vision holistique de la santé.',
    ],
];
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   LANDING PAGE TEMPLATE — Variables personnalisables            
   ═══════════════════════════════════════════════════════════════ */
.landing-page {
    /* EDIT: couleurs */
    --land-green:       #4fa75c;
    --land-green-light: #61ce70;
    --land-green-dark:  #2d6e38;
    --land-green-muted: rgba(79, 167, 92, 0.12);
    --land-bg:          #191d18;
    --land-bg-dark:     #131612;
    --land-bg-card:     #1c211b;
    --land-text:        #edecea;
    --land-text-muted:  #7a8a76;
    --land-border:      rgba(79, 167, 92, 0.18);

    /* EDIT: timing animations — augmente pour ralentir, diminue pour accélérer */
    --speed-branch:  8s;
    --speed-leaf:    3.5s;
    --speed-particle: 6s;

    font-family: var(--font-main, 'Helvetica', sans-serif);
    color: var(--land-text);
    background: var(--land-bg);
    overflow-x: hidden;
}

/* ─── Animations ─────────────────────────────────────────────── */
@keyframes branch-sway {
    0%, 100% { transform: rotate(-2.5deg); }
    50%       { transform: rotate(2.5deg);  }
}
@keyframes leaf-sway {
    0%, 100% { transform: rotate(-6deg) translateY(0);    }
    50%       { transform: rotate(6deg)  translateY(-4px); }
}
@keyframes leaf-sway-r {
    0%, 100% { transform: rotate(6deg)  translateY(0);    }
    50%       { transform: rotate(-6deg) translateY(-4px); }
}
@keyframes particle-rise {
    0%   { opacity: 0;   transform: translateY(0)      rotate(0deg)   scale(1); }
    15%  { opacity: .75; }
    85%  { opacity: .3;  }
    100% { opacity: 0;   transform: translateY(-130px) rotate(280deg) scale(.4); }
}
@keyframes hero-in {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0);    }
}
@keyframes line-grow {
    from { transform: scaleX(0); }
    to   { transform: scaleX(1); }
}
@keyframes card-in {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes trunk-draw {
    from { stroke-dashoffset: 400; }
    to   { stroke-dashoffset: 0; }
}
@keyframes glow-pulse {
    0%, 100% { opacity: .06; }
    50%       { opacity: .12; }
}

/* ─── HERO ─────────────────────────────────────────────────────── */
header {
   display:none;
}
.landing-hero {
    min-height: 90vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 40px;
    padding: 0px 0 40px;
    position: relative;
}

.landing-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: -20px; right: -20px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--land-green), transparent);
    animation: line-grow 1.2s 1s ease both;
    transform-origin: left;
}

.landing-hero-text {
    animation: hero-in .9s .1s cubic-bezier(.22,1,.36,1) both;
}

.landing-hero-tag {
    display: inline-block;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .18em;
    color: var(--land-green);
    border: 1px solid var(--land-border);
    border-radius: 2px;
    padding: 5px 12px;
    margin-bottom: 22px;
    background: var(--land-green-muted);
}

.landing-hero-title {
    font-family: var(--font-headings, 'Saira Extra Condensed', sans-serif);
    font-size: clamp(2.6rem, 5.5vw, 4.2rem);
    font-weight: 500;
    text-transform: uppercase;
    line-height: 1.05;
    margin: 0 0 22px;
    color: var(--land-text);
    letter-spacing: .02em;
}

.landing-hero-title span {
    color: var(--land-green);
}

.landing-hero-sub {
    font-size: 1rem;
    line-height: 1.75;
    color: var(--land-text-muted);
    margin: 0 0 36px;
    max-width: 400px;
}

.landing-hero-actions {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    align-items: center;
}

/* Buttons */
.land-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 26px;
    border-radius: 3px;
    font-size: .9rem;
    font-weight: 700;
    text-decoration: none;
    letter-spacing: .04em;
    transition: background .2s, color .2s, transform .15s;
    cursor: pointer;
}
.land-btn:active { transform: scale(.97); }

.land-btn-primary {
    background: var(--land-green);
    color: #fff;
    border: 2px solid var(--land-green);
}
.land-btn-primary:hover {
    background: var(--land-green-light);
    border-color: var(--land-green-light);
    color: #fff;
}
.land-btn-ghost {
    background: transparent;
    color: var(--land-green);
    border: 2px solid var(--land-border);
}
.land-btn-ghost:hover {
    border-color: var(--land-green);
    color: var(--land-green-light);
}

/* ─── BOTANICAL ILLUSTRATION ─────────────────────────────────── */
.landing-botanical {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: hero-in 1.1s .35s cubic-bezier(.22,1,.36,1) both;
}

.botanical-svg {
    width: 100%;
    max-width: 420px;
    height: auto;
    filter: drop-shadow(0 0 40px rgba(79,167,92,.12));
}

/* Background glow behind tree */
.tree-glow {
    animation: glow-pulse 4s ease-in-out infinite;
}

/* Branch animation groups — transform-origin set via SVG attribute */
.b1 { animation: branch-sway var(--speed-branch) ease-in-out infinite alternate; }
.b2 { animation: branch-sway var(--speed-branch) ease-in-out infinite alternate-reverse .8s; }
.b3 { animation: branch-sway calc(var(--speed-branch) * .85) ease-in-out infinite alternate 1.4s; }
.b4 { animation: branch-sway calc(var(--speed-branch) * 1.1) ease-in-out infinite alternate-reverse .4s; }
.b5 { animation: branch-sway calc(var(--speed-branch) * .75) ease-in-out infinite alternate .9s; }

.lf  { animation: leaf-sway  var(--speed-leaf) ease-in-out infinite alternate; }
.lfr { animation: leaf-sway-r var(--speed-leaf) ease-in-out infinite alternate; }
.lf2 { animation: leaf-sway  calc(var(--speed-leaf) * .8) ease-in-out infinite alternate-reverse .6s; }
.lfr2{ animation: leaf-sway-r calc(var(--speed-leaf) * .9) ease-in-out infinite alternate .4s; }
.lf3 { animation: leaf-sway  calc(var(--speed-leaf) * 1.1) ease-in-out infinite alternate 1s; }

/* Floating particles */
.landing-particles {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.particle {
    position: absolute;
    bottom: 15%;
    width: 5px; height: 5px;
    background: var(--land-green);
    border-radius: 50%;
    opacity: 0;
    animation: particle-rise var(--speed-particle) ease-in-out infinite;
}
.particle:nth-child(odd) {
    width: 3px; height: 8px;
    border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
    background: var(--land-green-light);
}

/* ─── DIVIDER ─────────────────────────────────────────────────── */
.landing-divider {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 48px 0 40px;
    opacity: .5;
}
.landing-divider::before,
.landing-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--land-border);
}
.landing-divider-icon {
    font-size: 1.1rem;
    color: var(--land-green);
}

/* ─── FEATURES ─────────────────────────────────────────────────── */
.landing-features {
    padding: 20px 0 60px;
}

.landing-features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.landing-feature {
    background: var(--land-bg-card);
    border: 1px solid var(--land-border);
    border-top: 2px solid var(--land-green);
    border-radius: 4px;
    padding: 28px 24px;
    transition: transform .25s, box-shadow .25s, border-top-color .25s;
    animation: card-in .7s ease both;
}
.landing-feature:nth-child(2) { animation-delay: .1s; }
.landing-feature:nth-child(3) { animation-delay: .2s; }

.landing-feature:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0,0,0,.35), 0 0 0 1px var(--land-border);
    border-top-color: var(--land-green-light);
}

.landing-feature-icon {
    width: 40px; height: 40px;
    color: var(--land-green);
    margin-bottom: 16px;
}
.landing-feature-icon svg { width: 100%; height: 100%; }

.landing-feature h3 {
    font-family: var(--font-headings, 'Saira Extra Condensed', sans-serif);
    font-size: 1.3rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--land-text);
    margin: 0 0 10px;
}

.landing-feature p {
    font-size: .88rem;
    line-height: 1.7;
    color: var(--land-text-muted);
    margin: 0;
}

/* ─── CONTENT AREA ─────────────────────────────────────────────── */
.landing-content {
    padding: 40px 0 60px;
    border-top: 1px solid var(--land-border);
}

.landing-content-inner {
    max-width: 720px;
}

/* ─── CTA SECTION ─────────────────────────────────────────────── */
.landing-cta-section {
    position: relative;
    background: var(--land-bg-dark);
    border: 1px solid var(--land-border);
    border-radius: 6px;
    padding: 70px 48px;
    margin: 20px 0 40px;
    text-align: center;
    overflow: hidden;
}

/* Animated leaf silhouettes in background */
.cta-leaf {
    position: absolute;
    opacity: .04;
    animation: leaf-sway 6s ease-in-out infinite alternate;
}
.cta-leaf:nth-child(2) { animation: leaf-sway-r 8s ease-in-out infinite alternate 1s; }
.cta-leaf:nth-child(3) { animation: leaf-sway 7s ease-in-out infinite alternate 2s; }

/* Green glow corners */
.landing-cta-section::before {
    content: '';
    position: absolute;
    top: -60px; left: -60px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(79,167,92,.15), transparent 70%);
    pointer-events: none;
}
.landing-cta-section::after {
    content: '';
    position: absolute;
    bottom: -60px; right: -60px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(79,167,92,.12), transparent 70%);
    pointer-events: none;
}

.landing-cta-section h2 {
    font-family: var(--font-headings, 'Saira Extra Condensed', sans-serif);
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--land-text);
    margin: 0 0 12px;
    position: relative;
}

.landing-cta-sub {
    color: var(--land-text-muted);
    font-size: .92rem;
    margin: 0 0 32px;
    position: relative;
}

/* ─── RESPONSIVE ─────────────────────────────────────────────── */
@media (max-width: 900px) {
    .landing-hero {
        grid-template-columns: 1fr;
        min-height: auto;
        padding: 40px 0;
    }
    .landing-botanical { order: -1; max-height: 340px; overflow: hidden; }
    .botanical-svg { max-width: 300px; }
    .landing-features-grid { grid-template-columns: 1fr; gap: 14px; }
    .landing-cta-section { padding: 48px 24px; }
}
@media (max-width: 560px) {
    .landing-hero-title { font-size: 2.2rem; }
    .landing-hero-actions { flex-direction: column; align-items: flex-start; }
    .land-btn { width: 100%; justify-content: center; }
}
</style>

<div class="landing-page">

<!-- ═══════════════════════════════════════════════════════
     HERO
     ═══════════════════════════════════════════════════════ -->
<section class="landing-hero">

    <div class="landing-hero-text">
        <div class="landing-hero-tag"><?php echo $hero_tag; ?></div>

        <h1 class="landing-hero-title">
            <?php
            // The page title — first word colored green, rest normal
            $words = explode(' ', htmlspecialchars($item['title'] ?? 'Landing Page'), 2);
            echo '<span>' . $words[0] . '</span>';
            echo isset($words[1]) ? '&nbsp;' . $words[1] : '';
            ?>
        </h1>

        <p class="landing-hero-sub"><?php echo $hero_subtitle; ?></p>

        <div class="landing-hero-actions">
            <a href="<?php echo htmlspecialchars($hero_cta_url); ?>" class="land-btn land-btn-primary">
                <?php echo htmlspecialchars($hero_cta_lbl); ?>
            </a>
            <a href="<?php echo htmlspecialchars($hero_ghost_url); ?>" class="land-btn land-btn-ghost">
                <?php echo htmlspecialchars($hero_ghost_lbl); ?>
            </a>
        </div>
    </div>

    <!-- ─── BOTANICAL ILLUSTRATION ─── -->
    <!-- EDIT: modifie les ellipses (feuilles) ou les path (branches/tronc)    -->
    <!-- Couleurs disponibles: #4fa75c #61ce70 #3a8a46 #5cc86a #2d6e38        -->
    <!-- Ajoute transform-origin en attribut SVG pour contrôler le pivot       -->
    <div class="landing-botanical">

        <svg class="botanical-svg" viewBox="0 0 380 540"
             xmlns="http://www.w3.org/2000/svg" aria-hidden="true">

            <!-- Lueur de fond -->
            <ellipse class="tree-glow" cx="188" cy="290" rx="130" ry="175"
                     fill="rgba(79,167,92,.06)"/>

            <!-- ══ RACINES — dessinées AVANT le tronc ══ -->
            <path d="M 187,510 Q 148,528 116,520" fill="none" stroke="#3d2b18" stroke-width="9"  stroke-linecap="round"/>
            <path d="M 185,505 Q 152,525 126,534" fill="none" stroke="#3d2b18" stroke-width="6"  stroke-linecap="round"/>
            <path d="M 183,512 Q 160,534 138,542" fill="none" stroke="#3d2b18" stroke-width="5"  stroke-linecap="round"/>
            <path d="M 182,518 Q 166,532 152,538" fill="none" stroke="#3d2b18" stroke-width="3"  stroke-linecap="round"/>
            <path d="M 181,522 Q 170,534 162,540" fill="none" stroke="#3d2b18" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M 193,510 Q 230,528 260,520" fill="none" stroke="#3d2b18" stroke-width="9"  stroke-linecap="round"/>
            <path d="M 195,505 Q 232,525 256,534" fill="none" stroke="#3d2b18" stroke-width="6"  stroke-linecap="round"/>
            <path d="M 197,512 Q 218,534 240,542" fill="none" stroke="#3d2b18" stroke-width="5"  stroke-linecap="round"/>
            <path d="M 198,518 Q 214,530 226,538" fill="none" stroke="#3d2b18" stroke-width="3"  stroke-linecap="round"/>
            <path d="M 199,522 Q 208,532 218,540" fill="none" stroke="#3d2b18" stroke-width="2.5" stroke-linecap="round"/>

            <!-- ══ TRONC ══ -->
            <path d="M 190,514 C 189,474 188,434 188,394
                                C 187,354 189,314 190,274
                                C 191,234 191,212 190,188"
                  fill="none" stroke="#4a3520" stroke-width="12" stroke-linecap="round"/>
            <path d="M 189,455 Q 192,435 189,415" fill="none" stroke="#3d2b18" stroke-width="1.5" opacity="0.3" stroke-linecap="round"/>
            <path d="M 190,368 Q 188,348 190,328" fill="none" stroke="#3d2b18" stroke-width="1.5" opacity="0.3" stroke-linecap="round"/>
            <path d="M 190,288 Q 192,268 190,250" fill="none" stroke="#3d2b18" stroke-width="1"   opacity="0.25" stroke-linecap="round"/>

            <!-- b1 (188,432)→(46,452) sw=8 -->
            <g class="b1" style="transform-origin:188px 432px">
              <path d="M 188,432 Q 118,451 46,452" fill="none" stroke="#4a3520" stroke-width="8" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(47,462) rotate(-129.0)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(47,459) rotate(-120.2)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(47,457) rotate(-111.3)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(46,454) rotate(-102.4)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(46,452) rotate(-93.6)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(46,450) rotate(-84.7)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(45,447) rotate(-75.9)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(45,445) rotate(-67.0)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(97,450) rotate(-119.0)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(96,447) rotate(-108.5)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(96,445) rotate(-98.0)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(96,443) rotate(-87.5)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(95,440) rotate(-77.0)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(134,442) rotate(-112.0)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(134,440) rotate(-98.0)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(134,438) rotate(-84.0)"/>
            </g>

            <!-- b2 (188,350)→(62,312) sw=7 -->
            <g class="b2" style="transform-origin:188px 350px">
              <path d="M 188,350 Q 123,339 62,312" fill="none" stroke="#4a3520" stroke-width="7" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(59,322) rotate(-104.2)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(60,319) rotate(-95.4)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(61,317) rotate(-86.5)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(61,314) rotate(-77.6)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(62,312) rotate(-68.8)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(63,310) rotate(-59.9)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(63,307) rotate(-51.1)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(64,305) rotate(-42.2)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(105,330) rotate(-94.2)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(105,327) rotate(-83.7)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(106,325) rotate(-73.2)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(107,323) rotate(-62.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(107,320) rotate(-52.2)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(139,338) rotate(-87.2)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(140,336) rotate(-73.2)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(141,334) rotate(-59.2)"/>
            </g>

            <!-- b3 (188,272)→(84,228) sw=6 -->
            <g class="b3" style="transform-origin:188px 272px">
              <path d="M 188,272 Q 133,256 84,228" fill="none" stroke="#4a3520" stroke-width="6" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(80,237) rotate(-98.1)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(81,235) rotate(-89.2)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(82,233) rotate(-80.4)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(83,230) rotate(-71.5)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(84,228) rotate(-62.6)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(85,226) rotate(-53.8)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(86,223) rotate(-44.9)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(87,221) rotate(-36.1)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(118,248) rotate(-88.1)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(119,245) rotate(-77.6)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(120,243) rotate(-67.1)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(121,241) rotate(-56.6)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(122,238) rotate(-46.1)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(147,257) rotate(-81.1)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(148,255) rotate(-67.1)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(149,253) rotate(-53.1)"/>
            </g>

            <!-- b3 (188,205)→(108,170) sw=5 -->
            <g class="b3" style="transform-origin:188px 205px">
              <path d="M 188,205 Q 146,192 108,170" fill="none" stroke="#4a3520" stroke-width="5" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(104,179) rotate(-97.4)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(105,177) rotate(-88.5)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(106,175) rotate(-79.7)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(107,172) rotate(-70.8)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(108,170) rotate(-61.9)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(109,168) rotate(-53.1)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(110,165) rotate(-44.2)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(111,163) rotate(-35.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(134,187) rotate(-87.4)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(135,184) rotate(-76.9)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(136,182) rotate(-66.4)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(137,180) rotate(-55.9)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(138,177) rotate(-45.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(157,194) rotate(-80.4)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(158,192) rotate(-66.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(159,190) rotate(-52.4)"/>
            </g>

            <!-- b4 (190,402)→(316,346) sw=8 -->
            <g class="b4" style="transform-origin:190px 402px">
              <path d="M 190,402 Q 250,366 316,346" fill="none" stroke="#4a3520" stroke-width="8" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(312,337) rotate(35.0)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(313,339) rotate(43.9)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(314,341) rotate(52.8)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(315,344) rotate(61.6)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(316,346) rotate(70.5)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(317,348) rotate(79.3)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(318,351) rotate(88.2)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(319,353) rotate(97.0)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(270,361) rotate(45.0)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(271,364) rotate(55.5)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(272,366) rotate(66.0)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(273,368) rotate(76.5)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(274,371) rotate(87.0)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(237,379) rotate(52.0)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(238,381) rotate(66.0)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(239,383) rotate(80.0)"/>
            </g>

            <!-- b5 (190,325)→(338,268) sw=7 -->
            <g class="b5" style="transform-origin:190px 325px">
              <path d="M 190,325 Q 261,288 338,268" fill="none" stroke="#4a3520" stroke-width="7" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(334,259) rotate(37.9)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(335,261) rotate(46.8)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(336,263) rotate(55.7)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(337,266) rotate(64.5)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(338,268) rotate(73.4)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(339,270) rotate(82.2)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(340,273) rotate(91.1)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(341,275) rotate(99.9)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(284,283) rotate(47.9)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(285,286) rotate(58.4)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(286,288) rotate(68.9)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(287,290) rotate(79.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(288,293) rotate(89.9)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(245,301) rotate(54.9)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(246,303) rotate(68.9)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(247,305) rotate(82.9)"/>
            </g>

            <!-- b6 (190,252)→(322,228) sw=6 -->
            <g class="b6" style="transform-origin:190px 252px">
              <path d="M 190,252 Q 255,232 322,228" fill="none" stroke="#4a3520" stroke-width="6" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(320,218) rotate(48.7)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(321,221) rotate(57.6)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(321,223) rotate(66.4)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(322,226) rotate(75.3)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(322,228) rotate(84.1)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(322,230) rotate(93.0)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(323,233) rotate(101.8)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(323,235) rotate(110.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(275,231) rotate(58.7)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(276,234) rotate(69.2)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(276,236) rotate(79.7)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(276,238) rotate(90.2)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(277,241) rotate(100.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(240,241) rotate(65.7)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(240,243) rotate(79.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(240,245) rotate(93.7)"/>
            </g>

            <!-- b4 (190,210)→(270,172) sw=5 -->
            <g class="b4" style="transform-origin:190px 210px">
              <path d="M 190,210 Q 228,186 270,172" fill="none" stroke="#4a3520" stroke-width="5" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(266,163) rotate(33.6)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(267,165) rotate(42.4)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(268,167) rotate(51.3)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(269,170) rotate(60.2)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(270,172) rotate(69.0)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(271,174) rotate(77.9)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(272,177) rotate(86.7)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(273,179) rotate(95.6)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(240,180) rotate(43.6)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(241,183) rotate(54.1)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(242,185) rotate(64.6)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(243,187) rotate(75.1)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(244,190) rotate(85.6)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(219,194) rotate(50.6)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(220,196) rotate(64.6)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(221,198) rotate(78.6)"/>
            </g>

            <!-- b5 (190,190)→(108,158) sw=4 -->
            <g class="b5" style="transform-origin:190px 190px">
              <path d="M 190,190 Q 147,179 108,158" fill="none" stroke="#4a3520" stroke-width="4" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(104,167) rotate(-99.7)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(105,165) rotate(-90.8)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(106,163) rotate(-82.0)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(107,160) rotate(-73.1)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(108,158) rotate(-64.3)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(109,156) rotate(-55.4)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(110,153) rotate(-46.5)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(111,151) rotate(-37.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(135,174) rotate(-89.7)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(136,171) rotate(-79.2)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(137,169) rotate(-68.7)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(138,167) rotate(-58.2)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(139,164) rotate(-47.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(158,180) rotate(-82.7)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(159,178) rotate(-68.7)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(160,176) rotate(-54.7)"/>
            </g>

            <!-- b2 (190,184)→(275,152) sw=4 -->
            <g class="b2" style="transform-origin:190px 184px">
              <path d="M 190,184 Q 231,163 275,152" fill="none" stroke="#4a3520" stroke-width="4" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(271,143) rotate(38.4)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(272,145) rotate(47.2)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(273,147) rotate(56.1)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(274,150) rotate(64.9)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(275,152) rotate(73.8)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(276,154) rotate(82.7)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(277,157) rotate(91.5)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(278,159) rotate(100.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(243,158) rotate(48.4)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(244,161) rotate(58.9)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(245,163) rotate(69.4)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(246,165) rotate(79.9)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(247,168) rotate(90.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(221,170) rotate(55.4)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(222,172) rotate(69.4)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(223,174) rotate(83.4)"/>
            </g>

            <!-- b6 (190,196)→(188,156) sw=4 -->
            <g class="b6" style="transform-origin:190px 196px">
              <path d="M 190,196 Q 187,176 188,156" fill="none" stroke="#4a3520" stroke-width="4" stroke-linecap="round"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(178,156) rotate(-33.9)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#5cc86a" transform="translate(181,156) rotate(-25.0)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#3a8a46" transform="translate(183,156) rotate(-16.1)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#61ce70" transform="translate(186,156) rotate(-7.3)"/>
              <path d="M 0,0 C -6,-10 -6,-20 0,-28 C 6,-20 6,-10 0,0" fill="#2d6e38" transform="translate(188,156) rotate(1.6)"/>
              <path d="M 0,0 C -6,-9 -6,-18 0,-25 C 6,-18 6,-9 0,0" fill="#48965a" transform="translate(190,156) rotate(10.4)"/>
              <path d="M 0,0 C -5,-8 -5,-17 0,-23 C 5,-17 5,-8 0,0" fill="#4a9e58" transform="translate(193,156) rotate(19.3)"/>
              <path d="M 0,0 C -5,-8 -5,-15 0,-21 C 5,-15 5,-8 0,0" fill="#4fa75c" transform="translate(195,156) rotate(28.1)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#3a8a46" transform="translate(184,170) rotate(-23.9)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#61ce70" transform="translate(187,170) rotate(-13.4)"/>
              <path d="M 0,0 C -5,-9 -5,-17 0,-24 C 5,-17 5,-9 0,0" fill="#2d6e38" transform="translate(189,170) rotate(-2.9)"/>
              <path d="M 0,0 C -4,-8 -4,-15 0,-21 C 4,-15 4,-8 0,0" fill="#48965a" transform="translate(191,170) rotate(7.6)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-17 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(194,170) rotate(18.1)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#2d6e38" transform="translate(187,181) rotate(-16.9)"/>
              <path d="M 0,0 C -4,-7 -4,-14 0,-20 C 4,-14 4,-7 0,0" fill="#48965a" transform="translate(189,181) rotate(-2.9)"/>
              <path d="M 0,0 C -4,-6 -4,-12 0,-16 C 4,-12 4,-6 0,0" fill="#4a9e58" transform="translate(191,181) rotate(11.1)"/>
            </g>

        </svg><!-- /botanical-svg -->

        <!-- Particules flottantes (graines/spores) -->
        <!-- EDIT: change le nombre de particules ou left/bottom -->
        <div class="landing-particles" aria-hidden="true">
            <?php
            $positions = [12,22,35,48,58,67,74,82,88,92,30,55];
            $delays    = [0, 0.7, 1.4, 2.1, 2.8, 0.4, 1.1, 1.8, 2.5, 3.2, 0.9, 2.0];
            $durations = [5.5,6.2,4.8,7.1,5.8,6.5,4.5,7.4,5.2,6.8,5.0,6.0];
            foreach ($positions as $i => $left):
            ?>
            <span class="particle" style="
                left:<?php echo $left; ?>%;
                animation-delay:<?php echo $delays[$i]; ?>s;
                animation-duration:<?php echo $durations[$i]; ?>s;
            "></span>
            <?php endforeach; ?>
        </div>

    </div><!-- /landing-botanical -->

</section><!-- /landing-hero -->


<!-- ═══════════════════════════════════════════════════════
     SÉPARATEUR
     ═══════════════════════════════════════════════════════ -->
<div class="landing-divider" aria-hidden="true">
    <span class="landing-divider-icon">✦</span>
</div>


<!-- ═══════════════════════════════════════════════════════
     FEATURES — EDIT: modifier $features en haut du fichier
     ═══════════════════════════════════════════════════════ -->
<section class="landing-features">
    <div class="landing-features-grid">
        <?php foreach ($features as $f): ?>
        <div class="landing-feature">
            <div class="landing-feature-icon"><?php echo $f['icon']; ?></div>
            <h3><?php echo htmlspecialchars($f['title']); ?></h3>
            <p><?php echo htmlspecialchars($f['desc']); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>


<!-- ═══════════════════════════════════════════════════════
     CONTENU DE LA PAGE — éditable dans l'admin
     (apparaît seulement si la page a du contenu)
     ═══════════════════════════════════════════════════════ -->
<?php if (!empty(trim(strip_tags($item['content'] ?? '')))): ?>
<section class="landing-content" id="landing-content">
    <div class="landing-content-inner">
        <?php echo render_content_html($item['content'], $item); ?>
    </div>
</section>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════
     CTA FINAL — EDIT: $cta_* en haut du fichier
     ═══════════════════════════════════════════════════════ -->
<section class="landing-cta-section">

    <!-- Feuilles décoratives fond — EDIT: viewBox et paths pour changer les formes -->
    <svg class="cta-leaf" style="position:absolute;top:-20px;left:-10px;width:180px;opacity:.04"
         viewBox="0 0 200 300" aria-hidden="true">
        <path d="M 100,290 C 100,290 20,200 30,120 C 40,40 100,10 100,10 C 100,10 160,40 170,120 C 180,200 100,290 100,290 Z"
              fill="#4fa75c"/>
    </svg>
    <svg class="cta-leaf" style="position:absolute;bottom:-30px;right:-15px;width:220px;opacity:.04;transform:rotate(160deg)"
         viewBox="0 0 200 300" aria-hidden="true">
        <path d="M 100,290 C 100,290 20,200 30,120 C 40,40 100,10 100,10 C 100,10 160,40 170,120 C 180,200 100,290 100,290 Z"
              fill="#4fa75c"/>
    </svg>
    <svg class="cta-leaf" style="position:absolute;top:50%;left:5%;width:80px;opacity:.03;transform:rotate(40deg) translateY(-50%)"
         viewBox="0 0 200 300" aria-hidden="true">
        <path d="M 100,290 C 60,240 20,180 40,110 C 60,40 100,10 100,10 C 100,10 140,40 160,110 C 180,180 140,240 100,290 Z"
              fill="#61ce70"/>
    </svg>

    <h2><?php echo htmlspecialchars($cta_title); ?></h2>
    <p class="landing-cta-sub"><?php echo htmlspecialchars($cta_sub); ?></p>
    <a href="<?php echo htmlspecialchars($cta_btn_url); ?>" class="land-btn land-btn-primary" style="position:relative">
        <?php echo htmlspecialchars($cta_btn_lbl); ?>
    </a>

</section>

</div><!-- /.landing-page -->