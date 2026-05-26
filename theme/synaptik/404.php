<?php
/**
 * Variables disponibles (injectées par core-functions.php) :
 *   $base_url  — URL de base du site (ex: https://monsite.com/)
 *   $home_url  — URL de la page d'accueil
 */

// Sécurité : ne pas appeler directement
if (!defined('INCLUDED') && !function_exists('getBaseUrl')) {
    exit;
}

$base_url = isset($base_url) ? $base_url : getBaseUrl();
$home_url = isset($home_url) ? $home_url : cleanUrl('home');
?>
<style>
/* =====================================================
   404 — Forêt des Pages Perdues
   Scoped sous .page-404 pour ne pas polluer le thème
   ===================================================== */
.page-404 *,
.page-404 *::before,
.page-404 *::after { box-sizing: border-box; }

.page-404 {
  --soil:   #13100a;
  --bark:   #2a1a0e;
  --moss:   #2d4468;
  --leaf:   #365d92;
  --sage:   #5d86b6;
  --mist:   #f4f4e9;
  --glow:   #599bdb;
  --amber:  #28a0c0;
  --rust:   #8a4a1e;
  --sky-top:#1a2812;

  position: relative;
  min-height: 92vh;
  /* background: radial-gradient(ellipse 100% 80% at 50% 100%, #2a1a08 0%, #13100a 45%, #0c0e0a 100%); */
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  /* font-family: 'Georgia', 'Times New Roman', serif; */
  color: var(--mist);
  /* Annule le padding/margin du conteneur thème si besoin */
  margin: -2rem -2rem 0;
  /* padding: 2rem; */
}

/* Couche de brume au sommet */
.p404-mist {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 40%;
  /* background: radial-gradient(ellipse 120% 60% at 50% 0%, rgba(180,220,140,.08) 0%, transparent 70%); */
  pointer-events: none;
  animation: p404-mistFloat 9s ease-in-out infinite alternate;
}
@keyframes p404-mistFloat {
  from { opacity:.5; transform:translateY(0); }
  to   { opacity:1;  transform:translateY(-18px); }
}

/* ── Racines au sol ── */
.p404-roots {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 260px;
  pointer-events: none;
}
.p404-roots path {
  fill: none;
  stroke-linecap: round;
  stroke-dasharray: 600;
  stroke-dashoffset: 600;
  animation: p404-rootGrow 2.4s ease-out forwards;
}
.p404-roots path:nth-child(1){stroke:var(--bark);   stroke-width:7; animation-delay:.1s;}
.p404-roots path:nth-child(2){stroke:var(--bark);   stroke-width:5; animation-delay:.4s;}
.p404-roots path:nth-child(3){stroke:#3d2210;       stroke-width:3; animation-delay:.7s;}
.p404-roots path:nth-child(4){stroke:#3d2210;       stroke-width:2; animation-delay:1s;}
.p404-roots path:nth-child(5){stroke:#4a2a14;       stroke-width:2; animation-delay:1.2s;}
.p404-roots path:nth-child(6){stroke:#4a2a14;       stroke-width:1.5;animation-delay:1.5s;}
@keyframes p404-rootGrow { to { stroke-dashoffset:0; } }

/* ── Champignons ── */
.p404-shrooms {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  pointer-events: none;
}
.p404-shroom {
  position: absolute;
  bottom: 55px;
  transform: scale(0);
  transform-origin: bottom center;
  animation: p404-shroomPop .6s cubic-bezier(.175,.885,.32,1.275) forwards;
  animation-delay: var(--sd);
}
@keyframes p404-shroomPop { to { transform:scale(1); } }

/* ── Lucioles ── */
.p404-ff {
  position: absolute;
  width: 4px; height: 4px;
  border-radius: 50%;
  background: #c2eb72;
  box-shadow: 0 0 8px var(--glow), 0 0 22px rgba(154,219,90,.4);
  pointer-events: none;
  animation: p404-ffMove var(--ffd) ease-in-out infinite alternate;
  animation-delay: var(--ffdel);
}
@keyframes p404-ffMove {
  from { opacity:.1; transform:translate(0,0); }
  50%  { opacity:1; }
  to   { opacity:.2; transform:translate(var(--ffx),var(--ffy)); }
}

/* ── Spores ── */
.p404-spore {
  position: absolute;
  border-radius: 50%;
  background: var(--glow);
  filter: blur(1px);
  box-shadow: 0 0 6px var(--glow);
  opacity: 0;
  animation: p404-sporeFloat var(--sd2) ease-in-out infinite;
  animation-delay: var(--sdel);
}
@keyframes p404-sporeFloat {
  0%   { opacity:0; transform:translateY(0) translateX(0) scale(.5); }
  20%  { opacity:.7; }
  80%  { opacity:.35; }
  100% { opacity:0; transform:translateY(-130px) translateX(var(--sdrift)) scale(1.3); }
}

/* ── Feuilles qui tombent ── */
.p404-leaf {
  position: absolute;
  pointer-events: none;
  opacity: 0;
  animation: p404-leafFall var(--lfd) ease-in-out infinite;
  animation-delay: var(--lfdel);
}
@keyframes p404-leafFall {
  0%   { opacity:0; transform:translateY(-60px) translateX(0) rotate(0deg); }
  10%  { opacity:.8; }
  85%  { opacity:.6; }
  100% { opacity:0; transform:translateY(98vh) translateX(var(--lfx)) rotate(var(--lfr)); }
}

/* ── Papillons ── */
.p404-butterfly {
  position: absolute;
  animation: p404-bfFly var(--bfd) ease-in-out infinite;
  animation-delay: var(--bfdel);
  pointer-events: none;
}
@keyframes p404-bfFly {
  0%   { transform:translate(0,0) rotate(0deg); }
  20%  { transform:translate(var(--bfx1),var(--bfy1)) rotate(8deg); }
  50%  { transform:translate(var(--bfx2),var(--bfy2)) rotate(-5deg); }
  80%  { transform:translate(var(--bfx3),var(--bfy3)) rotate(6deg); }
  100% { transform:translate(0,0) rotate(0deg); }
}
.p404-wing {
  animation: p404-wingFlap .28s ease-in-out infinite alternate;
  transform-origin: 50% 100%;
}
@keyframes p404-wingFlap {
  from { transform:scaleX(1); }
  to   { transform:scaleX(.25); }
}

/* ── Contenu central ── */
.p404-content {
  position: relative;
  z-index: 20;
  text-align: center;
  animation: p404-fadeUp 1.4s ease-out forwards;
}
@keyframes p404-fadeUp {
  from { opacity:0; transform:translateY(22px); }
  to   { opacity:1; transform:translateY(0); }
}

/* Vigne animée sur le 404 */
.p404-code-wrap {
  position: relative;
  display: inline-block;
  line-height: 1;
  margin-bottom: .25rem;
}
.p404-code {
  font-size: clamp(5.5rem, 17vw, 12rem);
  font-weight: 700;
  letter-spacing: -.02em;
  color: transparent;
  -webkit-text-stroke: 2px #2d684a;
  text-stroke: 2px var(--moss);
  filter: drop-shadow(0 0 28px rgba(58,98,40,.45));
  position: relative;
}
.p404-code::before {
  content: '404';
  position: absolute; inset: 0;
  background: linear-gradient(175deg, var(--sage) 0%, var(--moss) 55%, var(--leaf) 100%);
  -webkit-background-clip: text; background-clip: text;
  color: transparent;
  -webkit-text-stroke: 0;
  opacity: .28;
  filter: blur(1px);
}

/* SVG vigne par-dessus le 404 */
.p404-vine-svg {
  position: absolute;
  top: 8%; left: 0; width: 100%; height: 90%;
  pointer-events: none;
}
.p404-vine-path {
  fill: none;
  stroke: #397e3f;
  stroke-width: 1.8;
  stroke-dasharray: 700;
  stroke-dashoffset: 700;
  animation: p404-drawVine 3.2s ease-out .5s forwards;
}
@keyframes p404-drawVine { to { stroke-dashoffset:0; } }

.p404-vineleaf {
  fill: #40d357eb;
  transform: scale(0);
  transform-origin: center;
  animation: p404-leafGrow .35s ease-out forwards;
  animation-delay: var(--vld);
}
@keyframes p404-leafGrow { to { transform:scale(1); } }

/* Textes */
.p404-tag {
  /* display: inline-block; */
  background: rgba(79, 167, 92, 0.432);
  border: 1px solid rgba(82, 136, 160, 0.3);
  border-radius: 5px;
  padding: .2rem .9rem;
  font-size: .78rem;
  letter-spacing: .25em;
  color: #61Ce70;
  font-style: italic;
  margin-bottom: 1rem;
  opacity: 0;
  animation: p404-fadeUp .8s ease-out 1.1s forwards;
}
.p404-title {
  font-size: clamp(1.3rem, 3.5vw, 2rem);
  font-weight: 400;
  color: var(--mist);
  margin: 1.2rem 0 .6rem;
  opacity: 0;
  animation: p404-fadeUp .8s ease-out 1.3s forwards;
}
.p404-sub {
  font-size: 1rem;
  font-style: italic;
  color: rgb(176 206 217 / 60%);
  max-width: 400px;
  margin: 0 auto 2.2rem;
  line-height: 1.7;
  opacity: 0;
  animation: p404-fadeUp .8s ease-out 1.5s forwards;
}

/* Bouton */
.p404-btn {
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  background: transparent;
  border: 1.5px solid rgb(82 160 158 / 55%);
  color: #61a8ef;
  font-family: inherit;
  font-size: .88rem;
  letter-spacing: .22em;
  text-transform: uppercase;
  padding: .7rem 1.9rem;
  text-decoration: none;
  border-radius: 2px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: color .35s, border-color .35s, box-shadow .35s;
  opacity: 0;
  animation: p404-fadeUp .8s ease-out 1.7s forwards;
}
.p404-btn::before {
  content: '';
  position: absolute; inset: 0;
  background: rgba(91, 163, 234, 0.291);
  transform: translateX(-102%);
  transition: transform .35s ease;
  z-index: -1;
}
.button {
  background-color: rgba(79, 167, 92, 0.657);
}
.button:hover {
  background-color: rgba(88, 204, 106, 0.657);
  /* cursor: grab; */
}

.p404-btn:hover { color:rgba(41, 134, 227, 0.281); border-color:rgba(41, 134, 227, 0.452); box-shadow:0 0 20px rgba(111,160,82,.2); }
.p404-btn:hover::before { transform:translateX(0); }
</style>

<div class="page-404">
  <!-- Brume -->
  <!-- <div class="p404-mist"></div> -->

  <!-- Racines -->
  <svg class="p404-roots" viewBox="0 0 1440 260" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" width="100%" height="260" aria-hidden="true">
    <path d="M720,260 Q700,190 630,155 Q555,118 460,140 Q370,162 300,125 Q230,88 155,110"/>
    <path d="M720,260 Q745,185 820,148 Q900,112 990,138 Q1075,164 1145,138 Q1215,112 1310,130 Q1380,145 1440,120"/>
    <path d="M720,260 Q708,210 660,182 Q612,154 568,175 Q524,196 480,175"/>
    <path d="M720,260 Q735,218 778,200 Q820,182 855,200 Q890,218 930,206"/>
    <path d="M720,260 Q714,228 688,218 Q662,208 645,224 Q628,240 612,222"/>
    <path d="M720,260 Q726,232 752,226 Q778,220 792,234"/>
  </svg>

  <!-- Lucioles (générées en JS) -->
  <div id="p404-fireflies" aria-hidden="true"></div>

  <!-- Spores (générées en JS) -->
  <div id="p404-spores" aria-hidden="true"></div>

  <!-- Feuilles qui tombent (générées en JS) -->
  <div id="p404-leaves" aria-hidden="true"></div>

  <!-- Papillons (générés en JS) -->
  <div id="p404-butterflies" aria-hidden="true"></div>

  <!-- Champignons -->
  <div class="p404-shrooms" aria-hidden="true">
    <!-- Champignon rouge gauche -->
    <svg class="p404-shroom" style="left:7%;--sd:1.8s;width:52px;" viewBox="0 0 60 75" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="30" cy="30" rx="28" ry="20" fill="#8b2020"/>
      <ellipse cx="30" cy="29" rx="21" ry="15" fill="#b03030"/>
      <circle cx="20" cy="25" r="4.5" fill="rgba(255,220,220,.35)"/>
      <circle cx="38" cy="22" r="3"   fill="rgba(255,220,220,.35)"/>
      <circle cx="28" cy="18" r="2.5" fill="rgba(255,220,220,.3)"/>
      <rect x="23" y="48" width="14" height="22" rx="4" fill="#c8b89a"/>
      <ellipse cx="30" cy="48" rx="10" ry="3" fill="#b8a888"/>
    </svg>
    <!-- Champignon brun milieu -->
    <svg class="p404-shroom" style="left:12%;--sd:2.2s;width:34px;" viewBox="0 0 50 65" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="25" cy="24" rx="23" ry="16" fill="#5a3318"/>
      <ellipse cx="25" cy="23" rx="17" ry="12" fill="#7a4a28"/>
      <circle cx="18" cy="20" r="3" fill="rgba(200,180,150,.3)"/>
      <rect x="19" y="38" width="12" height="20" rx="3" fill="#c0aa88"/>
    </svg>
    <!-- Champignon orange droite -->
    <svg class="p404-shroom" style="right:8%;--sd:2s;width:44px;" viewBox="0 0 60 75" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="30" cy="30" rx="26" ry="18" fill="#c04a18"/>
      <ellipse cx="30" cy="29" rx="20" ry="14" fill="#e06030"/>
      <circle cx="22" cy="25" r="4"   fill="rgba(255,220,200,.35)"/>
      <circle cx="37" cy="23" r="2.5" fill="rgba(255,220,200,.35)"/>
      <rect x="23" y="46" width="14" height="22" rx="4" fill="#c8b89a"/>
    </svg>
    <!-- Petit champignon droite -->
    <svg class="p404-shroom" style="right:14%;--sd:2.5s;width:28px;" viewBox="0 0 50 65" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="25" cy="24" rx="22" ry="15" fill="#4a2a18"/>
      <ellipse cx="25" cy="23" rx="16" ry="11" fill="#6a3c22"/>
      <rect x="20" y="37" width="10" height="20" rx="3" fill="#b8a880"/>
    </svg>
  </div>

  <!-- Contenu principal -->
  <div class="p404-content">
    <div class="p404-tag">Pagina absentis · var. volatilis</div>

    <div class="p404-code-wrap">
      <div class="p404-code">404</div>
      <!-- Vigne animée -->
      <svg class="p404-vine-svg" viewBox="0 0 420 110" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path class="p404-vine-path" d="M15,75 Q55,35 100,58 Q148,82 192,48 Q238,14 278,46 Q318,78 360,54 Q390,38 410,48"/>
        <ellipse class="p404-vineleaf" cx="58"  cy="46" rx="11" ry="6" transform="rotate(-22,58,46)"  style="--vld:2.6s"/>
        <ellipse class="p404-vineleaf" cx="148" cy="74" rx="13" ry="7" transform="rotate(18,148,74)"  style="--vld:2.9s"/>
        <ellipse class="p404-vineleaf" cx="238" cy="34" rx="10" ry="5" transform="rotate(-12,238,34)" style="--vld:3.2s"/>
        <ellipse class="p404-vineleaf" cx="320" cy="70" rx="12" ry="6" transform="rotate(28,320,70)"  style="--vld:3.5s"/>
      </svg>
    </div>

    <p class="p404-tag" style="letter-spacing:.12em;font-style:normal;margin-top:.8rem;">Erreur Botanique</p>
    <h1 class="p404-title">Cette page a été compostée.</h1>
    <p class="p404-sub">
      Comme toute matière organique,<br>elle a rejoint la terre nourricière.<br>
      <em>Peut-être repoussera-t-elle sous une autre forme.</em>
    </p>

    <a href="<?= htmlspecialchars($home_url) ?>" class="button">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Retour aux sources
    </a>
  </div><!-- /.p404-content -->
</div><!-- /.page-404 -->

<script>
(function(){
  /* ── Lucioles ── */
  // var ffCont = document.getElementById('p404-fireflies');
  // for(var i=0;i<14;i++){
  //   var f=document.createElement('div');
  //   f.className='p404-ff';
  //   f.style.cssText=
  //     'left:'+(5+Math.random()*90)+'%;'+
  //     'top:'+(5+Math.random()*65)+'%;'+
  //     '--ffd:'+(3+Math.random()*4)+'s;'+
  //     '--ffdel:'+(Math.random()*5)+'s;'+
  //     '--ffx:'+((Math.random()-.5)*140)+'px;'+
  //     '--ffy:'+((Math.random()-.5)*90)+'px;';
  //   ffCont.appendChild(f);
  // }

  /* ── Spores ── */
  var spCont=document.getElementById('p404-spores');
  for(var i=0;i<45;i++){
    var s=document.createElement('div');
    s.className='p404-spore';
    var sz=1.5+Math.random()*3.5;
    s.style.cssText=
      'left:'+(Math.random()*100)+'%;'+
      'bottom:'+(Math.random()*35)+'%;'+
      'width:'+sz+'px;height:'+sz+'px;'+
      '--sd2:'+(4+Math.random()*6)+'s;'+
      '--sdel:'+(Math.random()*9)+'s;'+
      '--sdrift:'+((Math.random()-.5)*90)+'px;';
    spCont.appendChild(s);
  }

  /* ── Feuilles ── */
  var lfCont=document.getElementById('p404-leaves');
  var leafColors=['#4a8a3a','#3a6228','#5a9a48','#2d5a22','#6aaa58'];
  for(var i=0;i<10;i++){
    var lf=document.createElement('div');
    lf.className='p404-leaf';
    var col=leafColors[Math.floor(Math.random()*leafColors.length)];
    lf.style.cssText=
      'left:'+(Math.random()*100)+'%;'+
      'top:0;'+
      '--lfd:'+(9+Math.random()*8)+'s;'+
      '--lfdel:'+(Math.random()*12)+'s;'+
      '--lfx:'+((Math.random()-.5)*120)+'px;'+
      '--lfr:'+((Math.random()>0.5?1:-1)*(160+Math.random()*120))+'deg;';
    // SVG feuille inline
    lf.innerHTML='<svg width="18" height="26" viewBox="0 0 18 26" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'+
      '<path d="M9,25 Q5,14 9,2 Q13,14 9,25" fill="'+col+'" opacity=".75"/>'+
      '<path d="M9,25 Q9,14 9,2" fill="none" stroke="'+col+'" stroke-width=".8" opacity=".5"/>'+
      '</svg>';
    lfCont.appendChild(lf);
  }

  /* ── Papillons ── */
  var bfCont=document.getElementById('p404-butterflies');
  var bfDefs=[
    {top:'28%',left:'15%',w1:'#c05a18',w2:'#e08040',size:42,
     bfx1:'70px',bfy1:'-35px',bfx2:'30px',bfy2:'18px',bfx3:'-30px',bfy3:'-12px',bfd:'13s',bfdel:'0s'},
    {top:'42%',right:'18%',left:null,w1:'#8a3a8a',w2:'#b060b0',size:30,
     bfx1:'-50px',bfy1:'-22px',bfx2:'-20px',bfy2:'14px',bfx3:'30px',bfy3:'-8px',bfd:'16s',bfdel:'2s'},
  ];
  bfDefs.forEach(function(d){
    var wrap=document.createElement('div');
    wrap.className='p404-butterfly';
    var pos='top:'+d.top+';';
    pos+=d.left!==null?'left:'+d.left+';':'right:'+d.right+';';
    wrap.style.cssText=pos+
      '--bfd:'+d.bfd+';--bfdel:'+d.bfdel+';'+
      '--bfx1:'+d.bfx1+';--bfy1:'+d.bfy1+';'+
      '--bfx2:'+d.bfx2+';--bfy2:'+d.bfy2+';'+
      '--bfx3:'+d.bfx3+';--bfy3:'+d.bfy3+';';
    var s=d.size;
    wrap.innerHTML='<svg width="'+s+'" height="'+(s*.75)+'" viewBox="0 0 40 30" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'+
      '<g class="p404-wing">'+
        '<path d="M20,15 Q8,4 1,7 Q-1,14 8,20 Q14,23 20,15" fill="'+d.w1+'" opacity=".82"/>'+
        '<path d="M20,15 Q32,4 39,7 Q41,14 32,20 Q26,23 20,15" fill="'+d.w2+'" opacity=".82"/>'+
      '</g>'+
      '<circle cx="20" cy="15" r="2.2" fill="#2a1808"/>'+
      '</svg>';
    bfCont.appendChild(wrap);
  });
})();
</script>
