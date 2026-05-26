<?php
/* Template Name: Bilan 
*/

// $item is injected by loadThemeTemplate()
// Detects whether [contact_form] is already embedded in the content.
// If it is, render_content_html() expands it inline — no second form is appended.
?>
<style>

  :root {
    --sage: #7a9e7e;
    --sage-light: #a8c5ab;
    --sage-pale: #dceedd;
    --sage-dark: #4a7050;
    --leaf: #5c8a50;
    --leaf-light: #e8f2e4;
    --moss: #3d6b45;
    --bark: #6b5740;
    --cream: #f7f4ef;
    --warm-white: #fdfbf8;
    --text-primary: #2c3b2e;
    --text-secondary: #5a6b5c;
    --text-muted: #8a9e8c;
    --border-light: rgba(122,158,126,0.2);
    --border-mid: rgba(122,158,126,0.4);
    --gold: #b8956a;
    --gold-light: #f0e4d0;
    --alert-col: #c26a3e;
    --section-bg: rgba(255,255,255,0.7);
    --section-border: rgba(122,158,126,0.25);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: #e8f2e4;
    color: var(--text-primary);
    min-height: 100vh;
    overflow-x: hidden;
  }
  header{
      z-index:999;
    }
  /* ====== ANIMATED NATURE BACKGROUND ====== */
  #nature-bg {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
    background: linear-gradient(160deg, #e2eed9 0%, #d0e8c8 30%, #c8e2bc 60%, #d5ebb0 100%);
  }

  .bg-tree {
    position: absolute;
    bottom: 0;
    opacity: 0.18;
  }

  .bg-leaf {
    position: absolute;
    opacity: 0;
    animation: leafFall linear infinite;
  }

  @keyframes leafFall {
    0%   { transform: translateY(-60px) rotate(0deg) translateX(0); opacity: 0; }
    10%  { opacity: 0.6; }
    90%  { opacity: 0.3; }
    100% { transform: translateY(110vh) rotate(720deg) translateX(80px); opacity: 0; }
  }

  @keyframes sway {
    0%,100% { transform: rotate(-2deg) scaleX(1); }
    50% { transform: rotate(2deg) scaleX(1.01); }
  }

  .tree-sway { animation: sway 6s ease-in-out infinite; transform-origin: bottom center; }
  .tree-sway-slow { animation: sway 9s ease-in-out infinite; transform-origin: bottom center; }

  /* ====== LAYOUT ====== */
  .page-wrapper {
    position: relative;
    /* z-index: 1; */
    max-width: 1200px;
    margin: 0 auto;
    padding: 0rem 1.5rem 4rem;
  }

  /* ====== HEADER ====== */
  .header {
    text-align: center;
    /* padding: 3rem 2rem 2.5rem; */
    margin-bottom: 2.5rem;
    position: relative;
  }

  .header-badge {
    display: inline-block;
    font-family: 'Cormorant Garamond', serif;
    font-size: 0.78rem;
    font-style: italic;
    letter-spacing: 0.18em;
    color: var(--sage-dark);
    text-transform: uppercase;
    margin-bottom: 1rem;
    padding: 0.3rem 1.2rem;
    border: 1px solid var(--border-mid);
    border-radius: 20px;
    background: rgba(255,255,255,0.6);
  }

  .header h1 {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    font-weight: 500;
    color: var(--moss);
    line-height: 1.15;
    letter-spacing: -0.01em;
    margin-bottom: 0.6rem;
  }

  .header-sub {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 300;
    letter-spacing: 0.04em;
  }

  .header-line {
    width: 60px;
    height: 2px;
    background: var(--gold);
    margin: 1.2rem auto 0;
    border-radius: 2px;
  }

  /* ====== IDENTITY CARD ====== */
  .identity-card {
    background: rgba(255,255,255,0.82);
    backdrop-filter: blur(8px);
    border: 1px solid var(--section-border);
    border-radius: 18px;
    padding: 1.8rem 2rem;
    margin-bottom: 1.8rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem 1.5rem;
  }

  .identity-card .field-group label {
    display: block;
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 4px;
  }

  .identity-card .field-group input {
    width: 100%;
    border: none;
    border-bottom: 1.5px solid var(--border-mid);
    background: transparent;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem;
    color: var(--text-primary);
    padding: 4px 0;
    outline: none;
    transition: border-color 0.2s;
  }

  .identity-card .field-group input:focus {
    border-bottom-color: var(--sage);
  }

  /* ====== SECTIONS ====== */
  .section {
    background: rgba(255,255,255,0.78);
    backdrop-filter: blur(6px);
    border: 1px solid var(--section-border);
    border-radius: 18px;
    margin-bottom: 1.4rem;
    overflow: hidden;
    transition: box-shadow 0.3s;
  }

  .section:hover {
    box-shadow: 0 4px 24px rgba(92,138,80,0.08);
  }

  .section-header {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 1.2rem 1.8rem;
    cursor: pointer;
    user-select: none;
    background: rgba(255,255,255,0.5);
    border-bottom: 1px solid transparent;
    transition: all 0.25s;
  }

  .section-header:hover {
    background: rgba(255,255,255,0.75);
  }

  .section-header.open {
    border-bottom-color: var(--border-light);
    background: rgba(255,255,255,0.65);
  }

  .section-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    background: var(--leaf-light);
    color: var(--leaf);
  }

  .section-title-wrap { flex: 1; }

  .section-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--moss);
  }

  .section-subtitle {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 1px;
  }

  .section-counter {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 20px;
    background: var(--leaf-light);
    color: var(--leaf);
    font-weight: 500;
    min-width: 28px;
    text-align: center;
    transition: all 0.2s;
  }

  .section-counter.has-value {
    background: var(--sage);
    color: white;
  }

  .chevron {
    width: 18px; height: 18px;
    color: var(--text-muted);
    transition: transform 0.3s;
    flex-shrink: 0;
  }

  .section-header.open .chevron { transform: rotate(180deg); }

  .section-body {
    display: none;
    padding: 1.4rem 1.8rem 1.8rem;
    animation: slideDown 0.25s ease-out;
  }

  .section-body.open { display: block; }

  @keyframes slideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* ====== SUBSECTIONS ====== */
  .subsection {
    margin-bottom: 1.6rem;
  }

  .subsection:last-child { margin-bottom: 0; }

  .subsection-title {
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--sage-dark);
    margin-bottom: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .subsection-title::before {
    content: '';
    display: inline-block;
    width: 16px; height: 2px;
    background: var(--gold);
    border-radius: 2px;
    flex-shrink: 0;
  }

  /* ====== OPTION GRIDS ====== */
  .option-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .option-grid.cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
  .option-grid.cols-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; }
  .option-grid.cols-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.5rem; }

  /* RADIO = single choice (tick/radio) */
  .radio-option, .check-option {
    position: relative;
  }

  .radio-option input, .check-option input {
    position: absolute;
    opacity: 0;
    width: 0; height: 0;
  }

  .radio-option label, .check-option label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.42rem 0.85rem;
    border: 1.5px solid var(--border-light);
    border-radius: 8px;
    font-size: 0.85rem;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.18s;
    background: rgba(255,255,255,0.5);
    white-space: nowrap;
  }

  .radio-option label::before {
    content: '';
    width: 14px; height: 14px;
    border-radius: 50%;
    border: 1.5px solid var(--border-mid);
    flex-shrink: 0;
    transition: all 0.18s;
    background: white;
  }

  .check-option label::before {
    content: '';
    width: 14px; height: 14px;
    border-radius: 3px;
    border: 1.5px solid var(--border-mid);
    flex-shrink: 0;
    transition: all 0.18s;
    background: white;
  }

  .radio-option input:checked + label {
    border-color: var(--sage);
    background: var(--leaf-light);
    color: var(--moss);
    font-weight: 500;
  }

  .radio-option input:checked + label::before {
    background: var(--sage);
    border-color: var(--sage);
    box-shadow: inset 0 0 0 3px white;
  }

  .check-option input:checked + label {
    border-color: var(--sage);
    background: var(--leaf-light);
    color: var(--moss);
    font-weight: 500;
  }

  .check-option input:checked + label::before {
    background: var(--sage);
    border-color: var(--sage);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 14 14'%3E%3Cpath d='M2.5 7l3 3 6-6' stroke='white' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-size: 10px;
    background-repeat: no-repeat;
    background-position: center;
  }

  /* ====== SLIDERS ====== */
  .slider-group {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.8rem;
  }

  .slider-label {
    font-size: 0.82rem;
    color: var(--text-secondary);
    min-width: 160px;
  }

  .slider-group input[type=range] {
    flex: 1;
    height: 4px;
    -webkit-appearance: none;
    background: linear-gradient(to right, var(--sage) 0%, var(--sage) 50%, var(--border-mid) 50%);
    border-radius: 2px;
    outline: none;
  }

  .slider-group input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: white;
    border: 2px solid var(--sage);
    cursor: pointer;
  }

  .slider-val {
    min-width: 28px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--moss);
    text-align: right;
  }

  /* ====== NOTES TEXTAREA ====== */
  .notes-field {
    width: 100%;
    border: 1.5px solid var(--border-light);
    border-radius: 10px;
    background: rgba(255,255,255,0.6);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    color: var(--text-primary);
    padding: 0.7rem 0.9rem;
    resize: vertical;
    min-height: 70px;
    outline: none;
    transition: border-color 0.2s;
    margin-top: 0.6rem;
  }

  .notes-field:focus { border-color: var(--sage); }

  /* ====== TEMPERAMENT SELECTOR ====== */
  .temp-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .temp-option {
    position: relative;
  }

  .temp-option input {
    position: absolute;
    opacity: 0;
    width: 0; height: 0;
  }

  .temp-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 0.6rem 0.3rem;
    border: 1.5px solid var(--border-light);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.18s;
    background: rgba(255,255,255,0.5);
    text-align: center;
  }

  .temp-option .temp-code {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--sage-dark);
    font-family: 'Cormorant Garamond', serif;
  }

  .temp-option .temp-name {
    font-size: 0.62rem;
    color: var(--text-muted);
    line-height: 1.2;
  }

  .temp-option input:checked + label {
    border-color: var(--sage);
    background: var(--leaf-light);
  }

  .temp-option input:checked + label .temp-code {
    color: var(--moss);
  }

  /* ====== ORGAN TABLE ====== */
  .organ-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
  }

  .organ-table th {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    padding: 0.4rem 0.7rem;
    text-align: left;
    font-weight: 500;
    border-bottom: 1px solid var(--border-light);
  }

  .organ-table td {
    padding: 0.55rem 0.7rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-light);
  }

  .organ-table tr:last-child td { border-bottom: none; }

  .organ-table tr:hover td { background: rgba(168,197,171,0.06); }

  .organ-name {
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 400;
    min-width: 120px;
  }

  .state-options {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
  }

  .state-btn {
    padding: 0.28rem 0.65rem;
    border-radius: 6px;
    border: 1.5px solid var(--border-light);
    background: transparent;
    font-size: 0.75rem;
    cursor: pointer;
    color: var(--text-secondary);
    font-family: 'DM Sans', sans-serif;
    transition: all 0.15s;
  }

  .state-btn:hover { border-color: var(--sage-light); }
  .state-btn.active-strong { background: var(--leaf-light); border-color: var(--leaf); color: var(--leaf); font-weight: 500; }
  .state-btn.active-weak { background: rgba(184,149,106,0.1); border-color: var(--gold); color: var(--bark); font-weight: 500; }
  .state-btn.active-veryWeak { background: rgba(194,106,62,0.1); border-color: var(--alert-col); color: var(--alert-col); font-weight: 500; }
  .state-btn.active-normal { background: rgba(122,158,126,0.1); border-color: var(--sage); color: var(--sage-dark); font-weight: 500; }
  .state-btn.active-surcharge { background: rgba(194,106,62,0.12); border-color: #d4834a; color: #a84e20; font-weight: 500; }

  /* ====== IRIDOLOGIE SECTION ====== */
  .iris-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.2rem;
  }

  /* ====== VITALITE GAUGE ====== */
  .vitality-gauges {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.2rem;
  }

  .gauge-card {
    background: rgba(255,255,255,0.6);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 1rem 1.2rem;
  }

  .gauge-label {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--sage-dark);
    margin-bottom: 0.8rem;
  }

  /* ====== BUTTONS ====== */
  .action-bar {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2.5rem;
    flex-wrap: wrap;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 2rem;
    border-radius: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
  }

  .btn-primary {
    background: var(--moss);
    color: white;
    box-shadow: 0 4px 16px rgba(61,107,69,0.25);
  }

  .btn-primary:hover {
    background: var(--leaf);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(61,107,69,0.3);
  }

  .btn-secondary {
    background: rgba(255,255,255,0.8);
    color: var(--moss);
    border: 1.5px solid var(--border-mid);
  }

  .btn-secondary:hover {
    background: var(--leaf-light);
    border-color: var(--sage);
  }

  /* ====== DIVIDER ====== */
  .divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1.2rem 0;
  }

  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-light);
  }

  .divider span {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  /* ====== SURCHARGES ====== */
  .surcharge-block {
    background: rgba(240,248,237,0.6);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 1rem 1.2rem;
    margin-bottom: 1rem;
  }

  .surcharge-title {
    font-size: 0.78rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--sage-dark);
    margin-bottom: 0.7rem;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .surcharge-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--sage);
    flex-shrink: 0;
  }

  .surcharge-dot.colloidal { background: #7a9e7e; }
  .surcharge-dot.crystal { background: #b8956a; }
  .surcharge-dot.fat { background: #c26a3e; }

  /* ====== INFO TOOLTIP ====== */
  .info-note {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-style: italic;
    margin-top: 0.4rem;
    line-height: 1.5;
  }

  /* ====== PROGRESS BAR ====== */
  .progress-bar-wrap {
    height: 4px;
    background: var(--border-light);
    border-radius: 2px;
    margin-bottom: 2rem;
    overflow: hidden;
  }

  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(to right, var(--sage-light), var(--leaf));
    border-radius: 2px;
    transition: width 0.5s ease;
    width: 0%;
  }

  /* ====== RESPONSIVE ====== */
  @media (max-width: 640px) {
    .temp-grid { grid-template-columns: repeat(4, 1fr); }
    .option-grid.cols-4 { grid-template-columns: 1fr 1fr; }
    .option-grid.cols-3 { grid-template-columns: 1fr 1fr; }
    .vitality-gauges { grid-template-columns: 1fr; }
    .iris-grid { grid-template-columns: 1fr; }
    .slider-label { min-width: 120px; font-size: 0.75rem; }
    .identity-card { grid-template-columns: 1fr 1fr; }
  }


  /* ====== ORGAN OBS BLOCKS (section 6) ====== */
  .organ-obs-block {
    border: 1px solid var(--border-light);
    border-radius: 12px;
    margin-bottom: 0.9rem;
    overflow: hidden;
  }
  .organ-obs-title {
    background: linear-gradient(135deg, rgba(92,138,80,0.1), rgba(122,158,126,0.06));
    padding: 0.6rem 1.2rem;
    font-family: 'Cormorant Garamond', serif;
    font-size: 1rem;
    font-weight: 600;
    color: var(--moss);
    border-bottom: 1px solid var(--border-light);
  }
  .two-col-obs {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
  .two-col-obs > div {
    padding: 0.85rem 1rem;
  }
  .two-col-obs > div:first-child {
    border-right: 1px solid var(--border-light);
  }
  .obs-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
    padding: 0.18rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
  }
  .obs-morpho { color: var(--sage-dark); background: rgba(122,158,126,0.14); }
  .obs-questions { color: var(--bark); background: rgba(184,149,106,0.14); }
  .check-option.strength label { color: var(--sage-dark); }
  .check-option.strength input:checked + label {
    background: rgba(92,138,80,0.15);
    border-color: var(--sage);
    color: var(--moss);
    font-weight: 500;
  }
  .check-option.weakness label { color: #7a4820; }
  .check-option.weakness input:checked + label {
    background: rgba(194,106,62,0.12);
    border-color: #d4834a;
    color: #a84e20;
    font-weight: 500;
  }
  /* ====== VITALITY SIGN BLOCKS (section 8) ====== */
  .vit-category {
    border: 1px solid var(--border-light);
    border-radius: 12px;
    margin-bottom: 0.9rem;
    overflow: hidden;
  }
  .vit-cat-title {
    background: linear-gradient(135deg, rgba(92,138,80,0.12), rgba(184,149,106,0.06));
    padding: 0.6rem 1.2rem;
    font-family: 'Cormorant Garamond', serif;
    font-size: 1rem;
    font-weight: 600;
    color: var(--moss);
    border-bottom: 1px solid var(--border-light);
  }
  @media (max-width: 660px) {
    .two-col-obs { grid-template-columns: 1fr; }
    .two-col-obs > div:first-child { border-right: none; border-bottom: 1px solid var(--border-light); }
  }

  /* PRINT */
  @media print {
    #nature-bg { display: none; }
    body { background: white; }
    .section { break-inside: avoid; }
    .section-body { display: block !important; }
  }
</style>
<section class="page-details">

<!-- ====== NATURE BACKGROUND ====== -->
<div id="nature-bg">
  <!-- SVG trees -->
  <svg class="bg-tree tree-sway" style="left:2%; width:140px; height:320px;" viewBox="0 0 140 320" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="62" y="240" width="16" height="80" fill="#5c8a50"/>
    <ellipse cx="70" cy="190" rx="55" ry="80" fill="#7ab060"/>
    <ellipse cx="70" cy="140" rx="42" ry="65" fill="#8ec068"/>
    <ellipse cx="70" cy="95" rx="30" ry="50" fill="#9fd070"/>
    <ellipse cx="70" cy="60" rx="18" ry="32" fill="#a8d87a"/>
  </svg>

  <svg class="bg-tree tree-sway-slow" style="right:3%; width:160px; height:360px;" viewBox="0 0 160 360" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="72" y="270" width="16" height="90" fill="#4a7050"/>
    <ellipse cx="80" cy="220" rx="65" ry="90" fill="#6a9e58"/>
    <ellipse cx="80" cy="160" rx="50" ry="75" fill="#7ab860"/>
    <ellipse cx="80" cy="105" rx="36" ry="55" fill="#8ec868"/>
    <ellipse cx="80" cy="62" rx="22" ry="38" fill="#9ad870"/>
  </svg>

  <svg class="bg-tree tree-sway" style="left:18%; bottom:0; width:80px; height:200px; opacity:0.12;" viewBox="0 0 80 200" fill="none">
    <rect x="36" y="150" width="10" height="50" fill="#5c8a50"/>
    <ellipse cx="40" cy="120" rx="35" ry="55" fill="#7ab060"/>
    <ellipse cx="40" cy="82" rx="26" ry="42" fill="#8ec068"/>
    <ellipse cx="40" cy="48" rx="18" ry="30" fill="#9fd070"/>
  </svg>

  <svg class="bg-tree tree-sway-slow" style="right:20%; bottom:0; width:100px; height:240px; opacity:0.13;" viewBox="0 0 100 240" fill="none">
    <rect x="44" y="180" width="12" height="60" fill="#4a7050"/>
    <ellipse cx="50" cy="145" rx="42" ry="65" fill="#6a9e58"/>
    <ellipse cx="50" cy="96" rx="30" ry="50" fill="#7ab860"/>
    <ellipse cx="50" cy="55" rx="20" ry="36" fill="#8ec868"/>
  </svg>

  <!-- Fern / ground plants -->
  <svg style="position:absolute; bottom:0; left:8%; width:120px; height:80px; opacity:0.18;" viewBox="0 0 120 80" fill="none">
    <path d="M60 80 Q40 50 10 40 Q40 55 60 80Z" fill="#5c8a50"/>
    <path d="M60 80 Q80 45 110 35 Q80 52 60 80Z" fill="#4a7050"/>
    <path d="M60 80 Q50 55 30 55 Q50 62 60 80Z" fill="#6a9e58"/>
    <path d="M60 80 Q68 50 88 48 Q70 58 60 80Z" fill="#5c8a50"/>
  </svg>

  <svg style="position:absolute; bottom:0; right:10%; width:100px; height:70px; opacity:0.15;" viewBox="0 0 100 70" fill="none">
    <path d="M50 70 Q35 45 8 35 Q35 50 50 70Z" fill="#5c8a50"/>
    <path d="M50 70 Q65 40 92 30 Q65 47 50 70Z" fill="#4a7050"/>
    <path d="M50 70 Q42 48 25 50 Q43 56 50 70Z" fill="#7ab060"/>
  </svg>
</div>

<!-- ====== FALLING LEAVES ====== -->
<div id="leaves-container" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;overflow:hidden;"></div>

<!-- ====== MAIN CONTENT ====== -->
<div class="page-wrapper">

  <!-- HEADER -->
  <div class="header">
    <div class="header-badge">Dorian Fichot</div>
    <h1>Bilan de Vitalité<br>Naturopathique</h1>
    <p class="header-sub">Le bilan et les recommandations générés sur cette page sont à titre indicatif : le praticien doit dresser un bilan et des recommandations en fonction de son questionnement et de ses observations.</p>
    <div class="header-sub" style="margin-top:15px; color: red;">VERSION BÊTA : l'algorithme est opérationnel, mais continuera d'être amélioré en fonction des retours et tests. La section iridologie n'est pas encore prise en compte.</div>
  </div>

  <!-- PROGRESS -->
  <div class="progress-bar-wrap">
    <div class="progress-bar-fill" id="progressBar"></div>
  </div>

  <!-- IDENTITY -->
  <div class="identity-card">
    <div class="field-group"><label>Nom</label><input type="text" id="nom" placeholder="—"></div>
    <div class="field-group"><label>Prénom</label><input type="text" id="prenom" placeholder="—"></div>
    <div class="field-group"><label>Âge</label><input type="text" id="age" placeholder="—"></div>
    <div class="field-group"><label>Taille</label><input type="text" id="taille" placeholder="cm"></div>
    <div class="field-group"><label>Poids</label><input type="text" id="poids" placeholder="kg"></div>
    <div class="field-group"><label>Date du bilan</label><input type="date" id="dateB"></div>
  </div>

  <!-- ===== SECTION 1 : MORPHOTYPE GÉNÉRAL ===== -->
  <div class="section" id="sec1">
    <div class="section-header" onclick="toggleSection('sec1')">
      <div class="section-icon">⬡</div>
      <div class="section-title-wrap">
        <div class="section-title">1 · Morphotype général & Temperament</div>
        <div class="section-subtitle">Silhouette, rapport tronc/membres, axe dilatation/rétraction</div>
      </div>
      <span class="section-counter" id="cnt-sec1">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body open" id="body-sec1">

      <div class="subsection">
        <div class="divider" style="margin:0.6rem 0;"><span>Test bréviligne/longiligne</span></div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="main_test" id="mt1" value="pli_dessus_coccyx"><label for="mt1">Pli poignet au-dessus du coccyx — bréviligne</label></div>
          <div class="radio-option"><input type="radio" name="main_test" id="mt2" value="pli_au_pli_fessier"><label for="mt2">Pli poignet au niveau du pli fessier — normoligne</label></div>
          <div class="radio-option"><input type="radio" name="main_test" id="mt3" value="pli_sous_pli_fessier"><label for="mt3">Pli poignet sous le pli fessier — longiligne</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Axe dilatation / rétraction</div>
        <div style="padding:0.7rem 1rem;background:rgba(122,158,126,0.08);border:1px dashed var(--border-mid);border-radius:10px;">
          <p style="font-size:0.82rem;color:var(--text-secondary);margin:0;line-height:1.5;">
            🤖 <strong>Déterminé automatiquement</strong> selon la silhouette, la peau, la tonicité, l'axe corporel, etc.<br>
            <span style="font-size:0.78rem;color:var(--text-muted);">Résultat visible dans le bilan généré.</span>
          </p>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Tempérament naturopathique dominant (Marchesseau)</div>
        <div style="padding:0.7rem 1rem;background:rgba(122,158,126,0.08);border:1px dashed var(--border-mid);border-radius:10px;">
          <p style="font-size:0.82rem;color:var(--text-secondary);margin:0;line-height:1.5;">
            🤖 <strong>Déterminé automatiquement</strong> par l'algorithme en fonction des données morphologiques renseignées.<br>
            <span style="font-size:0.78rem;color:var(--text-muted);">Résultat visible dans le bilan généré.</span>
          </p>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Rapport tronc / membres</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="tronc" id="tr1" value="tronc_domine"><label for="tr1">Tronc domine — viscères forts</label></div>
          <div class="radio-option"><input type="radio" name="tronc" id="tr2" value="equilibre"><label for="tr2">Équilibré</label></div>
          <div class="radio-option"><input type="radio" name="tronc" id="tr3" value="membres_dominent"><label for="tr3">Membres dominent — dépense d'énergie</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Rapport thorax / abdomen</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="thorax_abd" id="ta1" value="thorax_fort"><label for="ta1">Thorax dominant — fort système respiratoire</label></div>
          <div class="radio-option"><input type="radio" name="thorax_abd" id="ta2" value="equilibre"><label for="ta2">Équilibré</label></div>
          <div class="radio-option"><input type="radio" name="thorax_abd" id="ta3" value="abdomen_fort"><label for="ta3">Abdomen dominant — fort système digestif</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Angle de la cage thoracique</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="cage" id="cg1" value="grand_ouvert"><label for="cg1">Grand ouvert (sanguin)</label></div>
          <div class="radio-option"><input type="radio" name="cage" id="cg2" value="moyen"><label for="cg2">Moyen (musculaire)</label></div>
          <div class="radio-option"><input type="radio" name="cage" id="cg3" value="ferme"><label for="cg3">Angle fermé (respiratoire)</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Posture générale</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="posture" id="p1" value="droite"><label for="p1">Droite — bonne vitalité</label></div>
          <div class="radio-option"><input type="radio" name="posture" id="p2" value="moyenne"><label for="p2">Moyenne</label></div>
          <div class="radio-option"><input type="radio" name="posture" id="p3" value="avachie"><label for="p3">Avachie — vitalité faible</label></div>
          <div class="radio-option"><input type="radio" name="posture" id="p4" value="rigide"><label for="p4">Rigide / scoliotique</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Tonicité musculaire générale</div>
        <div class="option-grid cols-2">
          <div class="radio-option"><input type="radio" name="tonicite" id="to1" value="bonne"><label for="to1">Bonne — fermeté, résistance à la pression (M · S · R)</label></div>
          <div class="radio-option"><input type="radio" name="tonicite" id="to2" value="reduite"><label for="to2">Réduite — mollesse partielle (D)</label></div>
          <div class="radio-option"><input type="radio" name="tonicite" id="to3" value="nulle"><label for="to3">Nulle / infiltrée — tissu sans résistance, eau dans les tissus (O)</label></div>
          <div class="radio-option"><input type="radio" name="tonicite" id="to4" value="filiforme"><label for="to4">Filiforme — muscles absents, tendons saillants, hypertonie sèche (N)</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Rapport épaules / hanches &amp; abdomen</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="epaules" id="ep1" value="larges"><label for="ep1">Épaules nettement plus larges — thorax dominant (R)</label></div>
          <div class="radio-option"><input type="radio" name="epaules" id="ep2" value="equilibrees"><label for="ep2">Proportions équilibrées (M)</label></div>
          <div class="radio-option"><input type="radio" name="epaules" id="ep3" value="ventre_domine"><label for="ep3">Ventre / hanches / abdomen plus large que les épaules (D · O)</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Signes morphologiques complémentaires</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="sg1" name="signes_gen" value="dissymetrie"><label for="sg1">Dissymétrie corporelle notable (un côté différent de l'autre) — <em>N</em></label></div>
          <div class="check-option"><input type="checkbox" id="sg2" name="signes_gen" value="hyperlaxite"><label for="sg2">Hyperlaxité articulaire (articulations très mobiles, laxes, déformables) — <em>O</em></label></div>
          <div class="check-option"><input type="checkbox" id="sg3" name="signes_gen" value="taille_marquee"><label for="sg3">Taille nettement marquée (resserrement buste/hanches visible) — <em>M · R</em></label></div>
          <div class="check-option"><input type="checkbox" id="sg4" name="signes_gen" value="ventre_creux"><label for="sg4">Ventre creux / abdomen rétracté, rentré (pas de ventre) — <em>N</em></label></div>
          <div class="check-option"><input type="checkbox" id="sg5" name="signes_gen" value="mouvements_saccades"><label for="sg5">Mouvements saccadés et secs (pas de fluidité) — <em>C · N</em></label></div>
          <div class="check-option"><input type="checkbox" id="sg6" name="signes_gen" value="buste_dense"><label for="sg6">Buste épais et massif, membres courts et puissants — <em>S</em></label></div>
          <div class="check-option"><input type="checkbox" id="sg7" name="signes_gen" value="raideurs_articulaires"><label for="sg7">Raideurs articulaires (noueuses, peu mobiles) — <em>C</em></label></div>
        </div>
      </div>


        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="att1" name="attitude" value="ouverte"><label for="att1">Ouverte</label></div>
          <div class="check-option"><input type="checkbox" id="att2" name="attitude" value="reservee"><label for="att2">Réservée</label></div>
          <div class="check-option"><input type="checkbox" id="att3" name="attitude" value="fuyante"><label for="att3">Fuyante</label></div>
          <div class="check-option"><input type="checkbox" id="att4" name="attitude" value="fermee"><label for="att4">Fermée</label></div>
          <div class="check-option"><input type="checkbox" id="att5" name="attitude" value="agitee"><label for="att5">Agitée</label></div>
          <div class="check-option"><input type="checkbox" id="att6" name="attitude" value="lente"><label for="att6">Lente / apathique</label></div>
        </div>
      </div>

    </div>
  

  <!-- ===== SECTION 2 : VISAGE ===== -->
  <div class="section" id="sec2">
    <div class="section-header" onclick="toggleSection('sec2')">
      <div class="section-icon">◉</div>
      <div class="section-title-wrap">
        <div class="section-title">2 · Visage — Grand cadre & Petit cadre</div>
        <div class="section-subtitle">Étages, ouvertures, géométrie</div>
      </div>
      <span class="section-counter" id="cnt-sec2">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec2">

      <div class="subsection">
        <div class="subsection-title">Forme générale du visage</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv1" value="carre"><label for="fv1">Carré — bilieux, sanguin</label></div>
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv2" value="rectangulaire"><label for="fv2">Rectangulaire</label></div>
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv3" value="triangulaire_base_haute"><label for="fv3">Triangle base haute</label></div>
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv4" value="triangulaire_base_basse"><label for="fv4">Triangle base basse (poire)</label></div>
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv5" value="ovale"><label for="fv5">Ovale — équilibré</label></div>
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv6" value="rond"><label for="fv6">Rond / circulaire</label></div>
          <div class="radio-option"><input type="radio" name="forme_visage" id="fv7" value="allonge"><label for="fv7">Allongé / fin</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Grand cadre (structure osseuse)</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="grand_cadre" id="gc1" value="grand"><label for="gc1">Grand — forte réserve énergétique</label></div>
          <div class="radio-option"><input type="radio" name="grand_cadre" id="gc2" value="moyen"><label for="gc2">Moyen</label></div>
          <div class="radio-option"><input type="radio" name="grand_cadre" id="gc3" value="petit"><label for="gc3">Petit — peu de réserve</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Petit cadre (ouvertures)</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="petit_cadre" id="pc1" value="grand"><label for="pc1">Grandes — échanges intenses</label></div>
          <div class="radio-option"><input type="radio" name="petit_cadre" id="pc2" value="moyen"><label for="pc2">Moyennes</label></div>
          <div class="radio-option"><input type="radio" name="petit_cadre" id="pc3" value="petit"><label for="pc3">Petites — vie intérieure</label></div>
        </div>
      </div>

      <div class="divider"><span>Étage supérieur — système nerveux / intelligence</span></div>

      <div class="subsection">
        <div class="subsection-title">Front (étage supérieur)</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="front" id="fr1" value="large_haut"><label for="fr1">Large et haut — fort système nerveux</label></div>
          <div class="radio-option"><input type="radio" name="front" id="fr2" value="equilibre"><label for="fr2">Équilibré</label></div>
          <div class="radio-option"><input type="radio" name="front" id="fr3" value="etroit_bas"><label for="fr3">Étroit / bas — SN faible</label></div>
          <div class="radio-option"><input type="radio" name="front" id="fr4" value="dominant"><label for="fr4">Dominant — cérébral / nerveux</label></div>
          <div class="radio-option"><input type="radio" name="front" id="fr5" value="reduit"><label for="fr5">Réduit — dilaté</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Yeux</div>
        <div class="option-grid cols-2">
          <div class="radio-option"><input type="radio" name="yeux_taille" id="ye1" value="grands"><label for="ye1">Grands — capte l'information</label></div>
          <div class="radio-option"><input type="radio" name="yeux_taille" id="ye2" value="normaux"><label for="ye2">Normaux</label></div>
          <div class="radio-option"><input type="radio" name="yeux_taille" id="ye3" value="petits"><label for="ye3">Petits et ternes — mental fatigué</label></div>
          <div class="radio-option"><input type="radio" name="yeux_taille" id="ye4" value="vifs"><label for="ye4">Vifs et expressifs — bonne vitalité</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Position</span></div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="yeux_pos" id="yp1" value="projetes"><label for="yp1">Projetés — capte vite, rejette vite</label></div>
          <div class="radio-option"><input type="radio" name="yeux_pos" id="yp2" value="plan"><label for="yp2">Dans le plan du visage</label></div>
          <div class="radio-option"><input type="radio" name="yeux_pos" id="yp3" value="rentres"><label for="yp3">Rentrés — thésaurise l'info</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Paupières</span></div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="paupieres" id="pau1" value="tonus_bon"><label for="pau1">Bon tonus</label></div>
          <div class="radio-option"><input type="radio" name="paupieres" id="pau2" value="tombantes"><label for="pau2">Tombantes — baisse énergie</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Oreilles</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="oreilles" id="or1" value="grandes_toniques"><label for="or1">Grandes, en relief, dures — forte vitalité</label></div>
          <div class="radio-option"><input type="radio" name="oreilles" id="or2" value="moyennes"><label for="or2">Moyennes — normale</label></div>
          <div class="radio-option"><input type="radio" name="oreilles" id="or3" value="petites_molles"><label for="or3">Petites, molles — vitalité réduite</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Hélix</span></div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="helix" id="hx1" value="bien_enroule"><label for="hx1">Bien enroulé — bonne colonne vertébrale</label></div>
          <div class="radio-option"><input type="radio" name="helix" id="hx2" value="plat_pointu"><label for="hx2">Plat et pointu — rachitisme, SN faible</label></div>
        </div>
      </div>

      <div class="divider"><span>Étage moyen — système respiratoire / émotion</span></div>

      <div class="subsection">
        <div class="subsection-title">Nez</div>
        <div class="option-grid cols-2">
          <div class="radio-option"><input type="radio" name="nez_profil" id="nz1" value="droit"><label for="nz1">Droit vu de profil — bonne extension thoracique</label></div>
          <div class="radio-option"><input type="radio" name="nez_profil" id="nz2" value="releve"><label for="nz2">Qui relève — superficialité</label></div>
          <div class="radio-option"><input type="radio" name="nez_profil" id="nz3" value="plonge"><label for="nz3">Qui plonge — distance, profondeur</label></div>
          <div class="radio-option"><input type="radio" name="nez_profil" id="nz4" value="devie"><label for="nz4">Dévié / tordu — scoliose possible</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Narines</span></div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="narines" id="na1" value="musclees_ouvertes"><label for="na1">Musclées, bien ouvertes — bonne prise d'EV</label></div>
          <div class="radio-option"><input type="radio" name="narines" id="na2" value="moyennes"><label for="na2">Moyennes</label></div>
          <div class="radio-option"><input type="radio" name="narines" id="na3" value="pincees"><label for="na3">Pincées / fermées — faible prise EV</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Pommettes</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="pommettes" id="pm1" value="larges"><label for="pm1">Larges — stocke l'énergie vitale</label></div>
          <div class="radio-option"><input type="radio" name="pommettes" id="pm2" value="moyennes"><label for="pm2">Moyennes</label></div>
          <div class="radio-option"><input type="radio" name="pommettes" id="pm3" value="absentes"><label for="pm3">Absentes / peu marquées</label></div>
        </div>
      </div>

      <div class="divider"><span>Étage inférieur — système glandulaire / digestif</span></div>

      <div class="subsection">
        <div class="subsection-title">Bouche</div>
        <div class="option-grid cols-2">
          <div class="radio-option"><input type="radio" name="bouche" id="bo1" value="charnue_large"><label for="bo1">Charnue et large — bonne capacité digestive</label></div>
          <div class="radio-option"><input type="radio" name="bouche" id="bo2" value="moyenne"><label for="bo2">Moyenne</label></div>
          <div class="radio-option"><input type="radio" name="bouche" id="bo3" value="fine_petite"><label for="bo3">Fine / petite — faible capacité digestive</label></div>
          <div class="radio-option"><input type="radio" name="bouche" id="bo4" value="tombe"><label for="bo4">Commissures tombantes — manque tonus digestif</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Lèvres</span></div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="lv1" name="levres"><label for="lv1">Lèvre sup. faible (estomac/duodénum faible)</label></div>
          <div class="check-option"><input type="checkbox" id="lv2" name="levres"><label for="lv2">Lèvre inf. faible (intestin faible)</label></div>
          <div class="check-option"><input type="checkbox" id="lv3" name="levres"><label for="lv3">Lèvres gercées toute l'année (foie faible)</label></div>
          <div class="check-option"><input type="checkbox" id="lv4" name="levres"><label for="lv4">Commissures tombant à droite (foie/vésicule)</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Mâchoire</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="machoire" id="mj1" value="puissante"><label for="mj1">Puissante — forte capacité digestive</label></div>
          <div class="radio-option"><input type="radio" name="machoire" id="mj2" value="moyenne"><label for="mj2">Moyenne</label></div>
          <div class="radio-option"><input type="radio" name="machoire" id="mj3" value="fine"><label for="mj3">Fine / rétractée — digestif faible</label></div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== SECTION 3 : PEAU, PHANÈRES & SIGNES CUTANÉS ===== -->
  <div class="section" id="sec3">
    <div class="section-header" onclick="toggleSection('sec3')">
      <div class="section-icon">✦</div>
      <div class="section-title-wrap">
        <div class="section-title">3 · Peau, Phanères & Signes cutanés</div>
        <div class="section-subtitle">Couleur, texture, pilosité, ongles, lunules</div>
      </div>
      <span class="section-counter" id="cnt-sec3">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec3">

      <div class="subsection">
        <div class="subsection-title">Couleur de la peau</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp1" value="rose_normale"><label for="cp1">Rose normale — bonne vitalité</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp2" value="rouge"><label for="cp2">Rouge — pléthore, sanguin</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp3" value="blanche"><label for="cp3">Blanche / pâle</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp4" value="gris_plombe"><label for="cp4">Gris plombé — reins faibles</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp5" value="jaunatre"><label for="cp5">Jaunâtre — foie, cérébral</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp6" value="verte"><label for="cp6">Verdâtre — nerveux</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp7" value="bleutee"><label for="cp7">Bleutée — digestif, circulation</label></div>
          <div class="radio-option"><input type="radio" name="couleur_peau" id="cp8" value="laiteuse"><label for="cp8">Laiteuse / spongieuse — obèse</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Texture / Humidité / Température</div>
        <div class="option-grid cols-3">
          <div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.4rem;">Texture</div>
            <div class="option-grid" style="flex-direction:column;gap:0.35rem;">
              <div class="radio-option"><input type="radio" name="texture" id="tx1" value="souple"><label for="tx1">Souple</label></div>
              <div class="radio-option"><input type="radio" name="texture" id="tx2" value="epaisse"><label for="tx2">Épaisse</label></div>
              <div class="radio-option"><input type="radio" name="texture" id="tx3" value="fine"><label for="tx3">Fine</label></div>
              <div class="radio-option"><input type="radio" name="texture" id="tx4" value="granuleuse"><label for="tx4">Granuleuse</label></div>
              <div class="radio-option"><input type="radio" name="texture" id="tx5" value="spongieuse"><label for="tx5">Spongieuse</label></div>
            </div>
          </div>
          <div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.4rem;">Humidité</div>
            <div class="option-grid" style="flex-direction:column;gap:0.35rem;">
              <div class="radio-option"><input type="radio" name="humidite" id="hm1" value="normale"><label for="hm1">Normale</label></div>
              <div class="radio-option"><input type="radio" name="humidite" id="hm2" value="seche"><label for="hm2">Sèche</label></div>
              <div class="radio-option"><input type="radio" name="humidite" id="hm3" value="humide_moite"><label for="hm3">Humide / moite</label></div>
            </div>
          </div>
          <div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.4rem;">Température</div>
            <div class="option-grid" style="flex-direction:column;gap:0.35rem;">
              <div class="radio-option"><input type="radio" name="temp_peau" id="tp1" value="chaude"><label for="tp1">Chaude</label></div>
              <div class="radio-option"><input type="radio" name="temp_peau" id="tp2" value="normale"><label for="tp2">Normale</label></div>
              <div class="radio-option"><input type="radio" name="temp_peau" id="tp3" value="froide"><label for="tp3">Froide</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Signes cutanés observés</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="sc1" name="signes_cutanes"><label for="sc1">Acné / boutons visage</label></div>
          <div class="check-option"><input type="checkbox" id="sc2" name="signes_cutanes"><label for="sc2">Acné dos / épaules</label></div>
          <div class="check-option"><input type="checkbox" id="sc3" name="signes_cutanes"><label for="sc3">Eczéma suintant (colloïdal)</label></div>
          <div class="check-option"><input type="checkbox" id="sc4" name="signes_cutanes"><label for="sc4">Eczéma sec (cristalloïdal)</label></div>
          <div class="check-option"><input type="checkbox" id="sc5" name="signes_cutanes"><label for="sc5">Psoriasis</label></div>
          <div class="check-option"><input type="checkbox" id="sc6" name="signes_cutanes"><label for="sc6">Furoncles / kystes</label></div>
          <div class="check-option"><input type="checkbox" id="sc7" name="signes_cutanes"><label for="sc7">Lipomes</label></div>
          <div class="check-option"><input type="checkbox" id="sc8" name="signes_cutanes"><label for="sc8">Verrues séborrhéiques</label></div>
          <div class="check-option"><input type="checkbox" id="sc9" name="signes_cutanes"><label for="sc9">Couperose</label></div>
          <div class="check-option"><input type="checkbox" id="sc10" name="signes_cutanes"><label for="sc10">Pellicules grasses</label></div>
          <div class="check-option"><input type="checkbox" id="sc11" name="signes_cutanes"><label for="sc11">Pellicules sèches</label></div>
          <div class="check-option"><input type="checkbox" id="sc12" name="signes_cutanes"><label for="sc12">Transpiration excessive / odorante</label></div>
          <div class="check-option"><input type="checkbox" id="sc13" name="signes_cutanes"><label for="sc13">Peau sèche au tibia (surcharges acides)</label></div>
          <div class="check-option"><input type="checkbox" id="sc14" name="signes_cutanes"><label for="sc14">Mycoses (pieds, ongles)</label></div>
          <div class="check-option"><input type="checkbox" id="sc15" name="signes_cutanes"><label for="sc15">Cernes violacées (foie, surcharges colloïdales)</label></div>
          <div class="check-option"><input type="checkbox" id="sc16" name="signes_cutanes"><label for="sc16">Cellulite / amas graisseux</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Localisation masses graisseuses</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="mg1" name="masse_graisseuse"><label for="mg1">Buste / haut du corps</label></div>
          <div class="check-option"><input type="checkbox" id="mg2" name="masse_graisseuse"><label for="mg2">Ventre / abdomen</label></div>
          <div class="check-option"><input type="checkbox" id="mg3" name="masse_graisseuse"><label for="mg3">Hanches / culotte de cheval</label></div>
          <div class="check-option"><input type="checkbox" id="mg4" name="masse_graisseuse"><label for="mg4">Cuisses</label></div>
          <div class="check-option"><input type="checkbox" id="mg5" name="masse_graisseuse"><label for="mg5">Autour des yeux (millium)</label></div>
          <div class="check-option"><input type="checkbox" id="mg6" name="masse_graisseuse"><label for="mg6">Double menton</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Pilosité</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="pilosite" id="pil1" value="developpe"><label for="pil1">Développée — surrénales actives</label></div>
          <div class="radio-option"><input type="radio" name="pilosite" id="pil2" value="moyenne"><label for="pil2">Moyenne</label></div>
          <div class="radio-option"><input type="radio" name="pilosite" id="pil3" value="reduite"><label for="pil3">Réduite — hormonal faible</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Cheveux</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="ch1" name="cheveux"><label for="ch1">Gras — surcharge colloïdale</label></div>
          <div class="check-option"><input type="checkbox" id="ch2" name="cheveux"><label for="ch2">Secs / fourchus</label></div>
          <div class="check-option"><input type="checkbox" id="ch3" name="cheveux"><label for="ch3">Clairsemés / chute (thyroïde)</label></div>
          <div class="check-option"><input type="checkbox" id="ch4" name="cheveux"><label for="ch4">Fins, frisent à l'humidité (poumons)</label></div>
          <div class="check-option"><input type="checkbox" id="ch5" name="cheveux"><label for="ch5">Épais et brillants</label></div>
        </div>
      </div>

      <div class="divider"><span>Ongles et Lunules</span></div>

      <div class="subsection">
        <div class="subsection-title">Forme des ongles</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="ongles_forme" id="on1" value="plats"><label for="on1">Plats — EV glandulaire faible</label></div>
          <div class="radio-option"><input type="radio" name="ongles_forme" id="on2" value="convexes"><label for="on2">Convexes — fragilité respiratoire</label></div>
          <div class="radio-option"><input type="radio" name="ongles_forme" id="on3" value="tres_convexes"><label for="on3">Très convexes (en verre de montre) — cardio</label></div>
          <div class="radio-option"><input type="radio" name="ongles_forme" id="on4" value="concaves"><label for="on4">Concaves — baisse d'énergie</label></div>
          <div class="radio-option"><input type="radio" name="ongles_forme" id="on5" value="normaux"><label for="on5">Normaux</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Signes sur ongles</span></div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="os1" name="ongles_signes"><label for="os1">Stries latérales — stress / choc émotionnel (thyroïdien)</label></div>
          <div class="check-option"><input type="checkbox" id="os2" name="ongles_signes"><label for="os2">Stries longitudinales — acidose / arthritisme</label></div>
          <div class="check-option"><input type="checkbox" id="os3" name="ongles_signes"><label for="os3">Taches blanches — déf. minéralisation</label></div>
          <div class="check-option"><input type="checkbox" id="os4" name="ongles_signes"><label for="os4">Bords rouges (intérieur) — inflammation digestive</label></div>
          <div class="check-option"><input type="checkbox" id="os5" name="ongles_signes"><label for="os5">Trait brun vertical — terrain infectieux</label></div>
          <div class="check-option"><input type="checkbox" id="os6" name="ongles_signes"><label for="os6">Brillants (hyperthyroïdie)</label></div>
          <div class="check-option"><input type="checkbox" id="os7" name="ongles_signes"><label for="os7">Rongés — orthosympathique actif / stress</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Lunules (énergie vitale circulante)</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="lunules_nb" id="lu1" value="0-2"><label for="lu1">0–2 / 10 — vitalité très faible</label></div>
          <div class="radio-option"><input type="radio" name="lunules_nb" id="lu2" value="3-4"><label for="lu2">3–4 / 10 — vitalité faible</label></div>
          <div class="radio-option"><input type="radio" name="lunules_nb" id="lu3" value="5-6"><label for="lu3">5–6 / 10 — normale</label></div>
          <div class="radio-option"><input type="radio" name="lunules_nb" id="lu4" value="7-8"><label for="lu4">7–8 / 10 — bonne</label></div>
          <div class="radio-option"><input type="radio" name="lunules_nb" id="lu5" value="9-10"><label for="lu5">9–10 / 10 — très bonne</label></div>
        </div>
        <div class="divider" style="margin:0.6rem 0;"><span>Qualité</span></div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="lunules_qual" id="lq1" value="bien_dessinées"><label for="lq1">Bien dessinées</label></div>
          <div class="radio-option"><input type="radio" name="lunules_qual" id="lq2" value="moyennes"><label for="lq2">Moyennes</label></div>
          <div class="radio-option"><input type="radio" name="lunules_qual" id="lq3" value="faibles"><label for="lq3">Faibles / absentes</label></div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== SECTION 4 : MAINS & OSSATURE ===== -->
  <div class="section" id="sec4">
    <div class="section-header" onclick="toggleSection('sec4')">
      <div class="section-icon">☍</div>
      <div class="section-title-wrap">
        <div class="section-title">4 · Mains, Ossature & Réserves</div>
        <div class="section-subtitle">Mains, doigts, appendice xiphoïde, réserve minérale/protéique</div>
      </div>
      <span class="section-counter" id="cnt-sec4">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec4">

      <div class="subsection">
        <div class="subsection-title">Forme des mains</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="mains_forme" id="mf1" value="brevilignes_epaisses"><label for="mf1">Brévilignes — courtes, doigts forts, paume épaisse (dilaté)</label></div>
          <div class="radio-option"><input type="radio" name="mains_forme" id="mf2" value="normales"><label for="mf2">Normales — équilibrées</label></div>
          <div class="radio-option"><input type="radio" name="mains_forme" id="mf3" value="longilignes_fines"><label for="mf3">Longilignes — élancées, doigts longs, paume creuse (rétracté)</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Consistance de la main</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="main_consist" id="mc1" value="ferme"><label for="mc1">Ferme — bonne vitalité (thénar / hypothénar développés)</label></div>
          <div class="radio-option"><input type="radio" name="main_consist" id="mc2" value="creuse_molle"><label for="mc2">Creuse / molle — capacité métabolique réduite</label></div>
          <div class="radio-option"><input type="radio" name="main_consist" id="mc3" value="plate_mince"><label for="mc3">Plate et mince — rétracté, faible</label></div>
          <div class="radio-option"><input type="radio" name="main_consist" id="mc4" value="epaisse_charnue"><label for="mc4">Épaisse et charnue — dilaté, sanguin</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Doigts</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="doigts" id="dg1" value="carres_ronds"><label for="dg1">Carrés / ronds — puissance, rigueur mentale</label></div>
          <div class="radio-option"><input type="radio" name="doigts" id="dg2" value="spatules"><label for="dg2">Spatulés — énergie forte, réalisateur, bilieux</label></div>
          <div class="radio-option"><input type="radio" name="doigts" id="dg3" value="coniques"><label for="dg3">Coniques — laissent fuir l'énergie, artiste, instable</label></div>
          <div class="radio-option"><input type="radio" name="doigts" id="dg4" value="lisses_pointus"><label for="dg4">Lisses et pointus — énergie difficile à garder</label></div>
          <div class="radio-option"><input type="radio" name="doigts" id="dg5" value="noueux"><label for="dg5">Noueux — blocage énergie articulaire, spéculatif</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Poignée de main</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="poignee" id="pg1" value="ferme"><label for="pg1">Ferme — bonne vitalité nerveuse</label></div>
          <div class="radio-option"><input type="radio" name="poignee" id="pg1" value="ferme"><label for="pg1">Normale</label></div>
          <div class="radio-option"><input type="radio" name="poignee" id="pg2" value="molle"><label for="pg2">Molle — vitalité faible</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Appendice xiphoïde</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="xiphoide" id="xi1" value="long_developpe"><label for="xi1">Long et développé — grande énergie</label></div>
          <div class="radio-option"><input type="radio" name="xiphoide" id="xi2" value="moyen"><label for="xi2">Moyen</label></div>
          <div class="radio-option"><input type="radio" name="xiphoide" id="xi3" value="court_absent"><label for="xi3">Court / absent — énergie faible</label></div>
          <div class="radio-option"><input type="radio" name="xiphoide" id="xi4" value="douloureux"><label for="xi4">Douloureux au toucher — énergie très faible</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Réserve minérale</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="res_mineral" id="rm1" value="bonne"><label for="rm1">Bonne — poignet large, ongles durs, dents saines</label></div>
          <div class="radio-option"><input type="radio" name="res_mineral" id="rm2" value="moyenne"><label for="rm2">Moyenne</label></div>
          <div class="radio-option"><input type="radio" name="res_mineral" id="rm3" value="faible"><label for="rm3">Faible — poignet fin, ongles striés, caries</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Réserve protéinique (musculature)</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="res_protein" id="rp1" value="bonne"><label for="rp1">Bonne — mollets/cuisses fermes</label></div>
          <div class="radio-option"><input type="radio" name="res_protein" id="rp2" value="moyenne"><label for="rp2">Moyenne</label></div>
          <div class="radio-option"><input type="radio" name="res_protein" id="rp3" value="faible"><label for="rp3">Faible — muscle filiforme, atrophie</label></div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== SECTION 5 : CRANIUM & TYPE GLANDULAIRE ===== -->
  <div class="section" id="sec5">
    <div class="section-header" onclick="toggleSection('sec5')">
      <div class="section-icon">◈</div>
      <div class="section-title-wrap">
        <div class="section-title">5 · Crâne & Type glandulaire</div>
        <div class="section-subtitle">Volumes crâniens, dominance glandulaire</div>
      </div>
      <span class="section-counter" id="cnt-sec5">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec5">

      <div class="subsection">
        <div class="subsection-title">Forme du crâne</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="crane_forme" id="cf1" value="large"><label for="cf1">Large — pragmatisme, action</label></div>
          <div class="radio-option"><input type="radio" name="crane_forme" id="cf2" value="allonge"><label for="cf2">Allongé — spéculatif, construit des théories</label></div>
          <div class="radio-option"><input type="radio" name="crane_forme" id="cf3" value="equilibre"><label for="cf3">Équilibré</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Volume développé au toucher</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="crane_volume" id="cv1" value="front_frontal"><label for="cv1">Frontal développé — Hypophysaire (mémoire, calcul)</label></div>
          <div class="radio-option"><input type="radio" name="crane_volume" id="cv2" value="haut_superieur"><label for="cv2">Supérieur élevé — Thyroïdien (émotif, artiste, intuitif)</label></div>
          <div class="radio-option"><input type="radio" name="crane_volume" id="cv3" value="arriere_haut"><label for="cv3">Arrière et haut — Pinéalien (philosophe, spirituel)</label></div>
          <div class="radio-option"><input type="radio" name="crane_volume" id="cv4" value="posterieur"><label for="cv4">Postérieur — Surrénalien (action, technique, pragmatique)</label></div>
          <div class="radio-option"><input type="radio" name="crane_volume" id="cv5" value="harmonieux"><label for="cv5">Harmonieux — Génital (harmonie, s'adapte)</label></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== SECTION 6 : ORGANES & ÉMONCTOIRES ===== -->
  <div class="section" id="sec6">
    <div class="section-header" onclick="toggleSection('sec6')">
      <div class="section-icon">⌘</div>
      <div class="section-title-wrap">
        <div class="section-title">6 · Observations par organe & Émonctoires</div>
        <div class="section-subtitle">Signes observés + questions confirmées — l'algorithme déterminera l'état de chaque organe</div>
      </div>
      <span class="section-counter" id="cnt-sec6">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec6">

      <p class="info-note" style="margin-bottom:1rem;">Cochez <strong>uniquement ce que vous observez</strong> ou ce que confirme le patient. <span style="color:var(--sage-dark);">✓ vert = force</span> · <span style="color:#a84e20;">⚠ orange = faiblesse / surcharge</span>. Aucun critère n'est obligatoire.</p>

      <div class="organ-obs-block" data-organ="estomac">
        <div class="organ-obs-title">🔵 Estomac · Duodénum</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="es_m1" data-organ="estomac" data-dir="pos"><label for="es_m1">✓ Lèvre supérieure charnue (bonne absorption)</label></div>
              <div class="check-option strength"><input type="checkbox" id="es_m2" data-organ="estomac" data-dir="pos"><label for="es_m2">✓ Angle sternal large (potentiel digestif fort)</label></div>
              <div class="check-option strength"><input type="checkbox" id="es_m3" data-organ="estomac" data-dir="pos"><label for="es_m3">✓ Mâchoire carrée et puissante</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_m4" data-organ="estomac" data-dir="neg"><label for="es_m4">⚠ Bord interne des ongles rouge (inflammation digestive)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_m5" data-organ="estomac" data-dir="neg"><label for="es_m5">⚠ Fente médiane de la langue (perturbation digestive)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_m6" data-organ="estomac" data-dir="neg"><label for="es_m6">⚠ Lèvre supérieure fine / absente (faible capacité)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_m7" data-organ="estomac" data-dir="neg"><label for="es_m7">⚠ Mâchoire fine et rétractée</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="es_q1" data-organ="estomac" data-dir="pos"><label for="es_q1">✓ Bon appétit, digère bien sans lourdeur</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_q2" data-organ="estomac" data-dir="neg"><label for="es_q2">⚠ Brûlures / reflux gastro-œsophagiens fréquents</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_q3" data-organ="estomac" data-dir="neg"><label for="es_q3">⚠ Digestion lente, lourdeur post-prandiale</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_q4" data-organ="estomac" data-dir="neg"><label for="es_q4">⚠ Nausées fréquentes</label></div>
              <div class="check-option weakness"><input type="checkbox" id="es_q5" data-organ="estomac" data-dir="neg"><label for="es_q5">⚠ Peu d'appétit chronique</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="intestin_grele">
        <div class="organ-obs-title">🔵 Intestin grêle</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="ig_m1" data-organ="intestin_grele" data-dir="pos"><label for="ig_m1">✓ Lèvre inférieure charnue (bonne élimination intestinale)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ig_m2" data-organ="intestin_grele" data-dir="neg"><label for="ig_m2">⚠ Lèvre inférieure mince / fine (intestin faible)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="ig_q1" data-organ="intestin_grele" data-dir="neg"><label for="ig_q1">⚠ Ballonnements fréquents</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ig_q2" data-organ="intestin_grele" data-dir="neg"><label for="ig_q2">⚠ Diarrhées / transit très accéléré</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ig_q3" data-organ="intestin_grele" data-dir="neg"><label for="ig_q3">⚠ Intolérances alimentaires (gluten, lactose…)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ig_q4" data-organ="intestin_grele" data-dir="neg"><label for="ig_q4">⚠ Douleurs abdominales diffuses sans cause</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="colons">
        <div class="organ-obs-title">🔵 Côlons (ascendant · transverse · descendant · sigmoïde)</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="co_m1" data-organ="colons" data-dir="neg"><label for="co_m1">⚠ Plis nasogéniens très marqués (côlons engorgés)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="co_m2" data-organ="colons" data-dir="neg"><label for="co_m2">⚠ Sons différents au tapotement (zones de rétention)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="co_m3" data-organ="colons" data-dir="neg"><label for="co_m3">⚠ Ventre ballonné / dur à la palpation</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="co_q1" data-organ="colons" data-dir="pos"><label for="co_q1">✓ Transit régulier, selles bien formées (1/jour)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="co_q2" data-organ="colons" data-dir="neg"><label for="co_q2">⚠ Constipation régulière (moins de 1 selle/jour)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="co_q3" data-organ="colons" data-dir="neg"><label for="co_q3">⚠ Selles en mouton / trop dures</label></div>
              <div class="check-option weakness"><input type="checkbox" id="co_q4" data-organ="colons" data-dir="neg"><label for="co_q4">⚠ Gaz / flatulences abondants</label></div>
              <div class="check-option weakness"><input type="checkbox" id="co_q5" data-organ="colons" data-dir="neg"><label for="co_q5">⚠ Mucus dans les selles</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="foie">
        <div class="organ-obs-title">🔵 Foie · Vésicule biliaire</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="fo_m1" data-organ="foie" data-dir="neg"><label for="fo_m1">⚠ Cernes violacées sous les yeux</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_m2" data-organ="foie" data-dir="neg"><label for="fo_m2">⚠ Commissures des lèvres tombant à droite</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_m3" data-organ="foie" data-dir="neg"><label for="fo_m3">⚠ Teint jaunâtre / sclérotique jaune</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_m4" data-organ="foie" data-dir="neg"><label for="fo_m4">⚠ Lèvres gercées toute l'année (foie chroniquement faible)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="fo_q1" data-organ="foie" data-dir="neg"><label for="fo_q1">⚠ Hémorroïdes (« l'anus est l'œil du foie »)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_q2" data-organ="foie" data-dir="neg"><label for="fo_q2">⚠ Céphalées / migraines du matin</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_q3" data-organ="foie" data-dir="neg"><label for="fo_q3">⚠ Nausées / lourdeur après repas gras</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_q4" data-organ="foie" data-dir="neg"><label for="fo_q4">⚠ Intolérance aux corps gras, à l'alcool</label></div>
              <div class="check-option weakness"><input type="checkbox" id="fo_q5" data-organ="foie" data-dir="neg"><label for="fo_q5">⚠ Calculs biliaires dans les antécédents</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="pancreas">
        <div class="organ-obs-title">🔵 Pancréas</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="pa_m1" data-organ="pancreas" data-dir="neg"><label for="pa_m1">⚠ Enduit jaunâtre / marronâtre fond de langue (faiblesse pancréatique)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pa_m2" data-organ="pancreas" data-dir="neg"><label for="pa_m2">⚠ Marques / dépôts côté gauche de la langue</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="pa_q1" data-organ="pancreas" data-dir="neg"><label for="pa_q1">⚠ Soif importante après les repas</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pa_q2" data-organ="pancreas" data-dir="neg"><label for="pa_q2">⚠ Soif nocturne fréquente (possible pré-diabète)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pa_q3" data-organ="pancreas" data-dir="neg"><label for="pa_q3">⚠ Fringales / hypoglycémies réactionnelles</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pa_q4" data-organ="pancreas" data-dir="neg"><label for="pa_q4">⚠ Glycémie élevée ou diabète de type 2 connu</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="reins">
        <div class="organ-obs-title">🔵 Reins · Vessie · Urinaire</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="re_m1" data-organ="reins" data-dir="neg"><label for="re_m1">⚠ Poches sous les yeux (œdème sous-palpébral)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="re_m2" data-organ="reins" data-dir="neg"><label for="re_m2">⚠ Teint gris plombé (reins sous-fonctionnels)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="re_q1" data-organ="reins" data-dir="neg"><label for="re_q1">⚠ Mictions très fréquentes / urgentes</label></div>
              <div class="check-option weakness"><input type="checkbox" id="re_q2" data-organ="reins" data-dir="neg"><label for="re_q2">⚠ Douleurs lombaires basses chroniques</label></div>
              <div class="check-option weakness"><input type="checkbox" id="re_q3" data-organ="reins" data-dir="neg"><label for="re_q3">⚠ Infections urinaires récurrentes</label></div>
              <div class="check-option weakness"><input type="checkbox" id="re_q4" data-organ="reins" data-dir="neg"><label for="re_q4">⚠ Calculs rénaux dans les antécédents</label></div>
              <div class="check-option weakness"><input type="checkbox" id="re_q5" data-organ="reins" data-dir="neg"><label for="re_q5">⚠ Homme : difficultés de miction / retours fréquents (prostate)</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="poumons">
        <div class="organ-obs-title">🔵 Poumons · Zone ORL · Voies respiratoires</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="po_m1" data-organ="poumons" data-dir="pos"><label for="po_m1">✓ Narines musclées, bien ouvertes (bonne prise d'EV)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_m2" data-organ="poumons" data-dir="neg"><label for="po_m2">⚠ Narines pincées / fermées (faible prise d'EV)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_m3" data-organ="poumons" data-dir="neg"><label for="po_m3">⚠ Ongles convexes / bombés (fragilité pulmonaire)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_m4" data-organ="poumons" data-dir="neg"><label for="po_m4">⚠ Ongles très convexes en verre de montre (fragilité cardio-pulmonaire)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_m5" data-organ="poumons" data-dir="neg"><label for="po_m5">⚠ Cheveux fins qui frisent à l'humidité (terrain pulmonaire)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="po_q1" data-organ="poumons" data-dir="neg"><label for="po_q1">⚠ Toux chronique (sèche ou grasse)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_q2" data-organ="poumons" data-dir="neg"><label for="po_q2">⚠ Essoufflement à l'effort disproportionné</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_q3" data-organ="poumons" data-dir="neg"><label for="po_q3">⚠ Asthme / bronchites récurrentes</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_q4" data-organ="poumons" data-dir="neg"><label for="po_q4">⚠ Rhumes / rhinites / sinusites fréquents</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_q5" data-organ="poumons" data-dir="neg"><label for="po_q5">⚠ Respiration habituelle par la bouche</label></div>
              <div class="check-option weakness"><input type="checkbox" id="po_q6" data-organ="poumons" data-dir="neg"><label for="po_q6">⚠ Mucosités chroniques (nez qui coule souvent clair)</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="coeur">
        <div class="organ-obs-title">🔵 Cœur · Circulation sanguine</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="cc_m1" data-organ="coeur" data-dir="neg"><label for="cc_m1">⚠ Fossette au bout du nez (signe cardiaque)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_m2" data-organ="coeur" data-dir="neg"><label for="cc_m2">⚠ Lobe d'oreille rainuré (signe cardio-vasculaire)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_m3" data-organ="coeur" data-dir="neg"><label for="cc_m3">⚠ Extrémités froides / pieds froids</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_m4" data-organ="coeur" data-dir="neg"><label for="cc_m4">⚠ Varices visibles</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_m5" data-organ="coeur" data-dir="neg"><label for="cc_m5">⚠ Couperose (circulation / progestérone)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_m6" data-organ="coeur" data-dir="neg"><label for="cc_m6">⚠ Test recoloration cutanée lente (appui cheville)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_m7" data-organ="coeur" data-dir="neg"><label for="cc_m7">⚠ Impatiences dans les jambes le soir</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="cc_q1" data-organ="coeur" data-dir="neg"><label for="cc_q1">⚠ Palpitations / tachycardie</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_q2" data-organ="coeur" data-dir="neg"><label for="cc_q2">⚠ Essoufflement à l'effort</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_q3" data-organ="coeur" data-dir="neg"><label for="cc_q3">⚠ Hémorroïdes (signe circulatoire)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_q4" data-organ="coeur" data-dir="neg"><label for="cc_q4">⚠ Hypertension artérielle connue</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cc_q5" data-organ="coeur" data-dir="neg"><label for="cc_q5">⚠ Jambes / pieds qui gonflent en fin de journée</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="lymphe">
        <div class="organ-obs-title">🔵 Système lymphatique</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="ly_m1" data-organ="lymphe" data-dir="neg"><label for="ly_m1">⚠ Creux poplité (derrière le genou) gonflé</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ly_m2" data-organ="lymphe" data-dir="neg"><label for="ly_m2">⚠ Chevilles gonflées en fin de journée</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ly_m3" data-organ="lymphe" data-dir="neg"><label for="ly_m3">⚠ Marque des chaussettes persistante</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ly_m4" data-organ="lymphe" data-dir="neg"><label for="ly_m4">⚠ Ganglions palpables</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="ly_q1" data-organ="lymphe" data-dir="neg"><label for="ly_q1">⚠ Infections / rhumes fréquents (immunité basse)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ly_q2" data-organ="lymphe" data-dir="neg"><label for="ly_q2">⚠ Gonflements diffus après effort ou chaleur</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ly_q3" data-organ="lymphe" data-dir="neg"><label for="ly_q3">⚠ Sensation de jambes lourdes chronique</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="thyroide">
        <div class="organ-obs-title">🔵 Glande thyroïde</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="th_m1" data-organ="thyroide" data-dir="neg"><label for="th_m1">⚠ Globes oculaires projetés / exorbités (hyperthyroïdie)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_m2" data-organ="thyroide" data-dir="neg"><label for="th_m2">⚠ Ongles brillants (hyper) ou ternes (hypo)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_m3" data-organ="thyroide" data-dir="neg"><label for="th_m3">⚠ Goitre / cou visiblement gonflé</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_m4" data-organ="thyroide" data-dir="neg"><label for="th_m4">⚠ Cheveux clairsemés / chute de cheveux (hypothyroïdie)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="th_q1" data-organ="thyroide" data-dir="neg" data-thyro="hypo"><label for="th_q1">⚠ Toujours froid(e), frileuse (hypothyroïdie)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_q2" data-organ="thyroide" data-dir="neg" data-thyro="hyper"><label for="th_q2">⚠ Toujours chaud(e), transpire facilement (hyperthyroïdie)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_q3" data-organ="thyroide" data-dir="neg" data-thyro="hyper"><label for="th_q3">⚠ Palpitations / anxiété / agitation (hyper)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_q4" data-organ="thyroide" data-dir="neg" data-thyro="hypo"><label for="th_q4">⚠ Fatigue profonde / ralentissement mental (hypo)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="th_q5" data-organ="thyroide" data-dir="neg"><label for="th_q5">⚠ Prise ou perte de poids inexpliquée</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="surrenales">
        <div class="organ-obs-title">🔵 Glandes surrénales</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="su_m1" data-organ="surrenales" data-dir="pos"><label for="su_m1">✓ Bas du corps solide, forte ossature et musculature</label></div>
              <div class="check-option strength"><input type="checkbox" id="su_m2" data-organ="surrenales" data-dir="pos"><label for="su_m2">✓ Pilosité bien développée (système hormonal actif)</label></div>
              <div class="check-option strength"><input type="checkbox" id="su_m3" data-organ="surrenales" data-dir="pos"><label for="su_m3">✓ Zone occipitale (arrière crâne) plate</label></div>
              <div class="check-option weakness"><input type="checkbox" id="su_m4" data-organ="surrenales" data-dir="neg"><label for="su_m4">⚠ Teint pâle / pilosité réduite (surrénales hypo)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="su_q1" data-organ="surrenales" data-dir="pos"><label for="su_q1">✓ En forme dès le matin, sans réveil difficile</label></div>
              <div class="check-option strength"><input type="checkbox" id="su_q2" data-organ="surrenales" data-dir="pos"><label for="su_q2">✓ Jamais ou rarement froid(e)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="su_q3" data-organ="surrenales" data-dir="neg"><label for="su_q3">⚠ Perméabilité au stress / s'épuise rapidement</label></div>
              <div class="check-option weakness"><input type="checkbox" id="su_q4" data-organ="surrenales" data-dir="neg"><label for="su_q4">⚠ Fatigue surrénalienne (prend sur lui/elle sans cesse)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="su_q5" data-organ="surrenales" data-dir="neg"><label for="su_q5">⚠ Frileux(se) permanent(e), toujours besoin de chaleur</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="peau_emonct">
        <div class="organ-obs-title">🔵 Peau — émonctoire cutané</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="pe_m1" data-organ="peau_emonct" data-dir="pos"><label for="pe_m1">✓ Peau souple, douce, bonne élasticité</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pe_m2" data-organ="peau_emonct" data-dir="neg"><label for="pe_m2">⚠ Peau très sèche, granuleuse (émonctoire fermé)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pe_m3" data-organ="peau_emonct" data-dir="neg"><label for="pe_m3">⚠ Éruptions cutanées actives (acné, eczéma, psoriasis)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pe_m4" data-organ="peau_emonct" data-dir="neg"><label for="pe_m4">⚠ Odeurs corporelles fortes / transpiration très odorante</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="pe_q1" data-organ="peau_emonct" data-dir="pos"><label for="pe_q1">✓ Transpire de partout à l'effort (émonctoire ouvert)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pe_q2" data-organ="peau_emonct" data-dir="neg"><label for="pe_q2">⚠ Peu ou pas de transpiration même à l'effort (émonctoire fermé)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="pe_q3" data-organ="peau_emonct" data-dir="neg"><label for="pe_q3">⚠ Transpiration localisée uniquement (aisselles seules)</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="genitaux">
        <div class="organ-obs-title">🔵 Organes génitaux · Hormonologie</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="ge_m1" data-organ="genitaux" data-dir="pos"><label for="ge_m1">✓ Pilosité harmonieuse (hormonologie équilibrée)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ge_m2" data-organ="genitaux" data-dir="neg"><label for="ge_m2">⚠ Pilosité anormale (hirsutisme femme / raréfaction homme)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="ge_q1" data-organ="genitaux" data-dir="pos"><label for="ge_q1">✓ Femme : cycles réguliers sans douleur</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ge_q2" data-organ="genitaux" data-dir="neg"><label for="ge_q2">⚠ Femme : cycles irréguliers / douloureux / SPM marqué</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ge_q3" data-organ="genitaux" data-dir="neg"><label for="ge_q3">⚠ Homme : difficultés de miction / retours fréquents (prostate gonflée)</label></div>
              <div class="check-option strength"><input type="checkbox" id="ge_q4" data-organ="genitaux" data-dir="pos"><label for="ge_q4">✓ Libido normale et équilibrée</label></div>
              <div class="check-option weakness"><input type="checkbox" id="ge_q5" data-organ="genitaux" data-dir="neg"><label for="ge_q5">⚠ Baisse de libido (femme = hormonal · homme = stress)</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="organ-obs-block" data-organ="colonne">
        <div class="organ-obs-title">🔵 Colonne vertébrale · Appareil ostéo-musculaire</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">👁 Signes morphologiques</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="cv_m1" data-organ="colonne" data-dir="pos"><label for="cv_m1">✓ Bonne mobilité des 3 étages (thoracique, diaphragmatique, abdominal)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cv_m2" data-organ="colonne" data-dir="neg"><label for="cv_m2">⚠ Déviation nez / hélix / alignement des doigts (scoliose possible)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cv_m3" data-organ="colonne" data-dir="neg"><label for="cv_m3">⚠ Posture affaissée / cyphose / lordose marquée</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cv_m4" data-organ="colonne" data-dir="neg"><label for="cv_m4">⚠ Déformations / gonflements bouts des doigts (arthritisme)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">❓ Questions patient</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="cv_q1" data-organ="colonne" data-dir="neg"><label for="cv_q1">⚠ Douleurs articulaires chroniques</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cv_q2" data-organ="colonne" data-dir="neg"><label for="cv_q2">⚠ Raideurs matinales (arthritisme acide)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cv_q3" data-organ="colonne" data-dir="neg"><label for="cv_q3">⚠ Tendinites récurrentes</label></div>
              <div class="check-option weakness"><input type="checkbox" id="cv_q4" data-organ="colonne" data-dir="neg"><label for="cv_q4">⚠ Lombalgie chronique / hernie discale</label></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>


  <!-- ===== SECTION 7 : SURCHARGES ===== -->
  <div class="section" id="sec7">
    <div class="section-header" onclick="toggleSection('sec7')">
      <div class="section-icon">◎</div>
      <div class="section-title-wrap">
        <div class="section-title">7 · Surcharges & Type de toxémie (Khune)</div>
        <div class="section-subtitle">Colloïdales, cristalloïdales, graisseuses</div>
      </div>
      <span class="section-counter" id="cnt-sec7">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec7">

      <div class="surcharge-block">
        <div class="surcharge-title"><span class="surcharge-dot colloidal"></span>Surcharges colloïdales (mucus, graisses douces — terrain dilaté)</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="sc_c1" name="surcharge_colloidal"><label for="sc_c1">Mucosités / catarrhes fréquents</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c2" name="surcharge_colloidal"><label for="sc_c2">Écoulements / sécrétions abondantes</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c3" name="surcharge_colloidal"><label for="sc_c3">Eczéma suintant / boutons qui suppurent</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c4" name="surcharge_colloidal"><label for="sc_c4">Pellicules grasses / cheveux gras</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c5" name="surcharge_colloidal"><label for="sc_c5">Cernes violacées / lipomes</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c6" name="surcharge_colloidal"><label for="sc_c6">Langue chargée / odeurs corporelles</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c7" name="surcharge_colloidal"><label for="sc_c7">Embonpoint / tissu conjonctif infiltré</label></div>
          <div class="check-option"><input type="checkbox" id="sc_c8" name="surcharge_colloidal"><label for="sc_c8">Acrocordons / verrues séborrhéiques</label></div>
        </div>
      </div>

      <div class="surcharge-block">
        <div class="surcharge-title"><span class="surcharge-dot crystal"></span>Surcharges cristalloïdales / acides (terrain rétracté — acide)</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="sc_k1" name="surcharge_crystal"><label for="sc_k1">Eczéma sec / dermatoses sèches</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k2" name="surcharge_crystal"><label for="sc_k2">Pellicules sèches / peau sèche tibia</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k3" name="surcharge_crystal"><label for="sc_k3">Ongles striés longitudinalement</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k4" name="surcharge_crystal"><label for="sc_k4">Douleurs articulaires / arthritisme</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k5" name="surcharge_crystal"><label for="sc_k5">Spasmophilie / fourmillements</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k6" name="surcharge_crystal"><label for="sc_k6">Mycoses (pieds, ongles) / parodontie</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k7" name="surcharge_crystal"><label for="sc_k7">Calculs rénaux / lithiases dans antécédents</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k8" name="surcharge_crystal"><label for="sc_k8">Yeux larmoyants / nez coule souvent clair</label></div>
          <div class="check-option"><input type="checkbox" id="sc_k9" name="surcharge_crystal"><label for="sc_k9">Tendinites / raideurs</label></div>
        </div>
      </div>

      <div class="surcharge-block">
        <div class="surcharge-title"><span class="surcharge-dot fat"></span>Surcharges graisseuses / cholestérol</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="sc_g1" name="surcharge_fat"><label for="sc_g1">Millium autour des yeux (amas graisseux)</label></div>
          <div class="check-option"><input type="checkbox" id="sc_g2" name="surcharge_fat"><label for="sc_g2">Embonpoint ventre / bourrelets abdominaux</label></div>
          <div class="check-option"><input type="checkbox" id="sc_g3" name="surcharge_fat"><label for="sc_g3">Surcharges de Khune visibles (plis graisseux)</label></div>
          <div class="check-option"><input type="checkbox" id="sc_g4" name="surcharge_fat"><label for="sc_g4">Furoncles / inflammations graisseuses</label></div>
          <div class="check-option"><input type="checkbox" id="sc_g5" name="surcharge_fat"><label for="sc_g5">Cellulite inflammatoire</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Niveau global de surcharge</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="niveau_surcharge" id="ns1" value="faible"><label for="ns1">Faible — terrain peu encrassé</label></div>
          <div class="radio-option"><input type="radio" name="niveau_surcharge" id="ns2" value="moyen"><label for="ns2">Moyen</label></div>
          <div class="radio-option"><input type="radio" name="niveau_surcharge" id="ns3" value="fort"><label for="ns3">Fort — encrassement important</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Niveau inflammatoire</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="niveau_inflam" id="ni1" value="faible"><label for="ni1">Faible</label></div>
          <div class="radio-option"><input type="radio" name="niveau_inflam" id="ni2" value="moyen"><label for="ni2">Moyen</label></div>
          <div class="radio-option"><input type="radio" name="niveau_inflam" id="ni3" value="fort"><label for="ni3">Fort — chaleur, rougeur, douleur</label></div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== SECTION 8 : VITALITÉ ===== -->
  <div class="section" id="sec8">
    <div class="section-header" onclick="toggleSection('sec8')">
      <div class="section-icon">⚡</div>
      <div class="section-title-wrap">
        <div class="section-title">8 · Vitalité & Énergie — Signes observés</div>
        <div class="section-subtitle">Cochez les signes observés — l'algorithme calculera les 3 notes sur 10</div>
      </div>
      <span class="section-counter" id="cnt-sec8">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec8">

      <p class="info-note">✓ vert = facteurs qui <strong>majorent</strong> la vitalité · ⚠ orange = facteurs qui <strong>minorent</strong>. Cochez tout ce que vous observez ou confirmez.</p>

      <div class="vit-category" data-vitcat="evMetab">
        <div class="vit-cat-title">⚡ Énergie vitale métabolique &amp; digestive</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">✓ Signes de force — majorants</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="vm_p1" data-vitcat="evMetab" data-dir="pos"><label for="vm_p1">✓ Ceinture scapulaire épaisse et développée</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p2" data-vitcat="evMetab" data-dir="pos"><label for="vm_p2">✓ Puissance du gros orteil (résistance à la pression)</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p3" data-vitcat="evMetab" data-dir="pos"><label for="vm_p3">✓ Épaisseur du thénar / hypothénar bien développée</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p4" data-vitcat="evMetab" data-dir="pos"><label for="vm_p4">✓ Appendice xiphoïde long et développé</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p5" data-vitcat="evMetab" data-dir="pos"><label for="vm_p5">✓ Grande bouche (fort potentiel digestif)</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p6" data-vitcat="evMetab" data-dir="pos"><label for="vm_p6">✓ Grand cadre osseux général</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p7" data-vitcat="evMetab" data-dir="pos"><label for="vm_p7">✓ Mâchoire carrée et puissante</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p8" data-vitcat="evMetab" data-dir="pos"><label for="vm_p8">✓ Bon appétit, digestion sans difficulté</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p9" data-vitcat="evMetab" data-dir="pos"><label for="vm_p9">✓ Muscles fermes — mollets / quadriceps denses</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p10" data-vitcat="evMetab" data-dir="pos"><label for="vm_p10">✓ Réserve minérale bonne (poignet large, ongles durs, dents saines)</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p11" data-vitcat="evMetab" data-dir="pos"><label for="vm_p11">✓ En forme dès le matin au réveil</label></div>
              <div class="check-option strength"><input type="checkbox" id="vm_p12" data-vitcat="evMetab" data-dir="pos"><label for="vm_p12">✓ Jamais ou rarement froid(e)</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">⚠ Signes de faiblesse — minorants</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="vm_n1" data-vitcat="evMetab" data-dir="neg"><label for="vm_n1">⚠ Appendice xiphoïde court, absent ou douloureux</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n2" data-vitcat="evMetab" data-dir="neg"><label for="vm_n2">⚠ Bouche petite ou fine (faible capacité digestive)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n3" data-vitcat="evMetab" data-dir="neg"><label for="vm_n3">⚠ Mâchoire fine et rétractée</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n4" data-vitcat="evMetab" data-dir="neg"><label for="vm_n4">⚠ Musculature atrophiée / effacée</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n5" data-vitcat="evMetab" data-dir="neg"><label for="vm_n5">⚠ Fatigue chronique persistante</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n6" data-vitcat="evMetab" data-dir="neg"><label for="vm_n6">⚠ Peu ou pas d'appétit (chronique)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n7" data-vitcat="evMetab" data-dir="neg"><label for="vm_n7">⚠ Réserve minérale faible (poignet fin, ongles striés, caries)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n8" data-vitcat="evMetab" data-dir="neg"><label for="vm_n8">⚠ Se sent épuisé(e) dès le matin au réveil</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vm_n9" data-vitcat="evMetab" data-dir="neg"><label for="vm_n9">⚠ Frileux(se) permanent(e) (surrénales faibles)</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="vit-category" data-vitcat="evNerv">
        <div class="vit-cat-title">🧠 Énergie vitale nerveuse (système nerveux)</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">✓ Signes de force — majorants</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="vn_p1" data-vitcat="evNerv" data-dir="pos"><label for="vn_p1">✓ Lunules bien visibles — 7 à 10 ongles</label></div>
              <div class="check-option strength"><input type="checkbox" id="vn_p2" data-vitcat="evNerv" data-dir="pos"><label for="vn_p2">✓ Regard tonique, vif et présent</label></div>
              <div class="check-option strength"><input type="checkbox" id="vn_p3" data-vitcat="evNerv" data-dir="pos"><label for="vn_p3">✓ Étage supérieur du visage bien développé</label></div>
              <div class="check-option strength"><input type="checkbox" id="vn_p4" data-vitcat="evNerv" data-dir="pos"><label for="vn_p4">✓ Poignée de main ferme et assurée</label></div>
              <div class="check-option strength"><input type="checkbox" id="vn_p5" data-vitcat="evNerv" data-dir="pos"><label for="vn_p5">✓ Posture droite et assurée</label></div>
              <div class="check-option strength"><input type="checkbox" id="vn_p6" data-vitcat="evNerv" data-dir="pos"><label for="vn_p6">✓ Attitude globale dynamique et présente</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">⚠ Signes de faiblesse — minorants</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="vn_n1" data-vitcat="evNerv" data-dir="neg"><label for="vn_n1">⚠ Lunules absentes ou 0–3 visibles</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vn_n2" data-vitcat="evNerv" data-dir="neg"><label for="vn_n2">⚠ Regard terne, fuyant ou absent</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vn_n3" data-vitcat="evNerv" data-dir="neg"><label for="vn_n3">⚠ Mains noueuses (blocage énergie articulaire)</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vn_n4" data-vitcat="evNerv" data-dir="neg"><label for="vn_n4">⚠ Posture avachie / affaissée</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vn_n5" data-vitcat="evNerv" data-dir="neg"><label for="vn_n5">⚠ Poignée de main molle / inerte</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vn_n6" data-vitcat="evNerv" data-dir="neg"><label for="vn_n6">⚠ Hypersensibilité au bruit, lumière, odeurs</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vn_n7" data-vitcat="evNerv" data-dir="neg"><label for="vn_n7">⚠ Fatigue nerveuse / épuisement du SN</label></div>
            </div>
          </div>
        </div>
        <div style="padding:0.8rem 1rem; border-top:1px solid var(--border-light);">
          <div class="subsection-title" style="margin-bottom:0.5rem;">Profil du système nerveux observé</div>
          <div class="option-grid">
            <div class="check-option weakness"><input type="checkbox" id="sn1" name="sys_nerveux" data-vitcat="evNerv" data-dir="neg"><label for="sn1">⚠ Hyperactif — jambes agitées, gestuelle abondante, débit de parole rapide (orthosympathique sur-activé)</label></div>
            <div class="check-option weakness"><input type="checkbox" id="sn2" name="sys_nerveux" data-vitcat="evNerv" data-dir="neg"><label for="sn2">⚠ Anxieux / angoissé chronique</label></div>
            <div class="check-option weakness"><input type="checkbox" id="sn3" name="sys_nerveux" data-vitcat="evNerv" data-dir="neg"><label for="sn3">⚠ Insomnie / sommeil très léger</label></div>
            <div class="check-option weakness"><input type="checkbox" id="sn4" name="sys_nerveux" data-vitcat="evNerv" data-dir="neg"><label for="sn4">⚠ Somnolence diurne excessive</label></div>
            <div class="check-option weakness"><input type="checkbox" id="sn5" name="sys_nerveux" data-vitcat="evNerv" data-dir="neg"><label for="sn5">⚠ Ongles rongés (orthosympathique sur-activé)</label></div>
            <div class="check-option strength"><input type="checkbox" id="sn6" name="sys_nerveux" data-vitcat="evNerv" data-dir="pos"><label for="sn6">✓ Bon équilibre nerveux global</label></div>
          </div>
        </div>
      </div>

      <div class="vit-category" data-vitcat="evPsy">
        <div class="vit-cat-title">💫 Énergie psychique &amp; émotionnelle</div>
        <div class="two-col-obs">
          <div>
            <div class="obs-label obs-morpho">✓ Signes de force — majorants</div>
            <div class="option-grid">
              <div class="check-option strength"><input type="checkbox" id="vp_p1" data-vitcat="evPsy" data-dir="pos"><label for="vp_p1">✓ Moral stable et positif</label></div>
              <div class="check-option strength"><input type="checkbox" id="vp_p2" data-vitcat="evPsy" data-dir="pos"><label for="vp_p2">✓ Bonne résistance au stress</label></div>
              <div class="check-option strength"><input type="checkbox" id="vp_p3" data-vitcat="evPsy" data-dir="pos"><label for="vp_p3">✓ Enthousiasme et motivation générale</label></div>
              <div class="check-option strength"><input type="checkbox" id="vp_p4" data-vitcat="evPsy" data-dir="pos"><label for="vp_p4">✓ Sommeil réparateur et profond</label></div>
              <div class="check-option strength"><input type="checkbox" id="vp_p5" data-vitcat="evPsy" data-dir="pos"><label for="vp_p5">✓ Relations sociales épanouissantes</label></div>
              <div class="check-option strength"><input type="checkbox" id="vp_p6" data-vitcat="evPsy" data-dir="pos"><label for="vp_p6">✓ Bonne concentration, clarté mentale</label></div>
              <div class="check-option strength"><input type="checkbox" id="vp_p7" data-vitcat="evPsy" data-dir="pos"><label for="vp_p7">✓ Résilience émotionnelle bonne</label></div>
            </div>
          </div>
          <div>
            <div class="obs-label obs-questions">⚠ Signes de faiblesse — minorants</div>
            <div class="option-grid">
              <div class="check-option weakness"><input type="checkbox" id="vp_n1" data-vitcat="evPsy" data-dir="neg"><label for="vp_n1">⚠ Anxiété chronique / attaques de panique</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vp_n2" data-vitcat="evPsy" data-dir="neg"><label for="vp_n2">⚠ Dépression / moral chroniquement bas</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vp_n3" data-vitcat="evPsy" data-dir="neg"><label for="vp_n3">⚠ Rumination mentale / stress chronique</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vp_n4" data-vitcat="evPsy" data-dir="neg"><label for="vp_n4">⚠ Insomnie ou sommeil non réparateur</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vp_n5" data-vitcat="evPsy" data-dir="neg"><label for="vp_n5">⚠ Isolement social / repli sur soi</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vp_n6" data-vitcat="evPsy" data-dir="neg"><label for="vp_n6">⚠ Difficultés de concentration chroniques</label></div>
              <div class="check-option weakness"><input type="checkbox" id="vp_n7" data-vitcat="evPsy" data-dir="neg"><label for="vp_n7">⚠ Hypersensibilité émotionnelle / irritabilité chronique</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="subsection" style="margin-top:0.8rem;">
        <div class="subsection-title">Qualité du sommeil (précision)</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="sommeil" id="so1" value="tres_bon"><label for="so1">Très bon — récupération rapide et profonde</label></div>
          <div class="radio-option"><input type="radio" name="sommeil" id="so2" value="bon"><label for="so2">Bon</label></div>
          <div class="radio-option"><input type="radio" name="sommeil" id="so3" value="moyen"><label for="so3">Moyen — réveils nocturnes</label></div>
          <div class="radio-option"><input type="radio" name="sommeil" id="so4" value="mauvais"><label for="so4">Mauvais / insomnie chronique</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Appétit &amp; digestion subjective</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="appetit" id="ap1" value="gros_appetit"><label for="ap1">Gros appétit, digère bien</label></div>
          <div class="radio-option"><input type="radio" name="appetit" id="ap2" value="normal"><label for="ap2">Appétit normal</label></div>
          <div class="radio-option"><input type="radio" name="appetit" id="ap3" value="faible"><label for="ap3">Peu d'appétit</label></div>
          <div class="radio-option"><input type="radio" name="appetit" id="ap4" value="variable"><label for="ap4">Variable / irrégulier</label></div>
        </div>
      </div>

    </div>
  </div>


  <!-- ===== SECTION 9 : IRIDOLOGIE ===== -->
  <div class="section" id="sec9">
    <div class="section-header" onclick="toggleSection('sec9')">
      <div class="section-icon">◉</div>
      <div class="section-title-wrap">
        <div class="section-title">9 · Iridologie</div>
        <div class="section-subtitle">Couleur iris, trame, pupille, zones organiques</div>
      </div>
      <span class="section-counter" id="cnt-sec9">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec9">

      <div class="iris-grid">
        <div>
          <div class="subsection">
            <div class="subsection-title">Couleur de l'iris</div>
            <div class="option-grid" style="flex-direction:column;gap:0.35rem;">
              <div class="radio-option"><input type="radio" name="couleur_iris" id="ci1" value="bleu_gris"><label for="ci1">Bleu / gris (B)</label></div>
              <div class="radio-option"><input type="radio" name="couleur_iris" id="ci2" value="mixte"><label for="ci2">Mixte / noisette (M)</label></div>
              <div class="radio-option"><input type="radio" name="couleur_iris" id="ci3" value="brun"><label for="ci3">Brun / foncé (H)</label></div>
            </div>
          </div>

          <div class="subsection">
            <div class="subsection-title">Trame irienne (densité des fibres)</div>
            <div class="option-grid" style="flex-direction:column;gap:0.3rem;">
              <div class="radio-option"><input type="radio" name="trame" id="tr_1" value="1"><label for="tr_1">1 — très dense / serrée (forte vitalité)</label></div>
              <div class="radio-option"><input type="radio" name="trame" id="tr_2" value="2"><label for="tr_2">2 — dense</label></div>
              <div class="radio-option"><input type="radio" name="trame" id="tr_3" value="3"><label for="tr_3">3 — moyenne</label></div>
              <div class="radio-option"><input type="radio" name="trame" id="tr_4" value="4"><label for="tr_4">4 — lâche</label></div>
              <div class="radio-option"><input type="radio" name="trame" id="tr_5" value="5"><label for="tr_5">5 — très lâche</label></div>
              <div class="radio-option"><input type="radio" name="trame" id="tr_6" value="6"><label for="tr_6">6 — effacée (vitalité très faible)</label></div>
            </div>
          </div>
        </div>

        <div>
          <div class="subsection">
            <div class="subsection-title">Pupille</div>
            <div class="option-grid" style="flex-direction:column;gap:0.35rem;">
              <div class="radio-option"><input type="radio" name="pupille" id="pu1" value="normale_centree"><label for="pu1">Normale, centrée</label></div>
              <div class="radio-option"><input type="radio" name="pupille" id="pu2" value="myosis"><label for="pu2">Myosis (petite) — parasympathique</label></div>
              <div class="radio-option"><input type="radio" name="pupille" id="pu3" value="mydriase"><label for="pu3">Mydriase (large) — adrénaline, stress</label></div>
              <div class="radio-option"><input type="radio" name="pupille" id="pu4" value="instable"><label for="pu4">Instable / hippus — SN fragile</label></div>
              <div class="radio-option"><input type="radio" name="pupille" id="pu5" value="decentree"><label for="pu5">Décentrée</label></div>
            </div>
          </div>

          <div class="subsection">
            <div class="subsection-title">Ourlet pupillaire</div>
            <div class="option-grid" style="flex-direction:column;gap:0.35rem;">
              <div class="radio-option"><input type="radio" name="ourlet" id="ou1" value="bon"><label for="ou1">Bon — tube digestif</label></div>
              <div class="radio-option"><input type="radio" name="ourlet" id="ou2" value="moyen"><label for="ou2">Moyen</label></div>
              <div class="radio-option"><input type="radio" name="ourlet" id="ou3" value="faible"><label for="ou3">Faible — tonus digestif bas</label></div>
              <div class="radio-option"><input type="radio" name="ourlet" id="ou4" value="spasmee"><label for="ou4">Spasmée</label></div>
              <div class="radio-option"><input type="radio" name="ourlet" id="ou5" value="distendue"><label for="ou5">Distendue</label></div>
            </div>
          </div>
        </div>
      </div>

      <div class="divider"><span>Angle de Fuchs (profil morphologique)</span></div>
      <div class="subsection">
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="fuchs" id="fu1" value="aplati"><label for="fu1">Aplati — rétracté / maigre</label></div>
          <div class="radio-option"><input type="radio" name="fuchs" id="fu2" value="arrondi"><label for="fu2">Arrondi — dilaté</label></div>
          <div class="radio-option"><input type="radio" name="fuchs" id="fu3" value="normal"><label for="fu3">Normal</label></div>
          <div class="radio-option"><input type="radio" name="fuchs" id="fu4" value="exacerbe"><label for="fu4">Exacerbé</label></div>
        </div>
      </div>

      <div class="divider"><span>Arcs et signes périphériques</span></div>
      <div class="subsection">
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="arc1" name="arcs_iris"><label for="arc1">Arc sénile (blanc en haut) — cholestérol, circulation cérébrale</label></div>
          <div class="check-option"><input type="checkbox" id="arc2" name="arcs_iris"><label for="arc2">Arc lipidique — surcharge graisseuse</label></div>
          <div class="check-option"><input type="checkbox" id="arc3" name="arcs_iris"><label for="arc3">Arc sodique — rétention sel, reins</label></div>
          <div class="check-option"><input type="checkbox" id="arc4" name="arcs_iris"><label for="arc4">Anneau nerveux — SN sous tension</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Zone cérébrale</div>
        <textarea class="notes-field" id="zone_cerveau" rows="2" placeholder="Observations iris zone cérébrale..."></textarea>
      </div>

      <div class="subsection">
        <div class="subsection-title">Zone digestive (côlons)</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="zi1" name="iris_digestif"><label for="zi1">Côlon ascendant — signes de surcharge</label></div>
          <div class="check-option"><input type="checkbox" id="zi2" name="iris_digestif"><label for="zi2">Côlon transverse</label></div>
          <div class="check-option"><input type="checkbox" id="zi3" name="iris_digestif"><label for="zi3">Côlon descendant</label></div>
          <div class="check-option"><input type="checkbox" id="zi4" name="iris_digestif"><label for="zi4">Sigmoïde</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Zones cardio-pulmonaires</div>
        <textarea class="notes-field" id="zone_cardio" rows="2" placeholder="Poumon D / G, cœur..."></textarea>
      </div>

      <div class="subsection">
        <div class="subsection-title">Zone hépatique / pancréatique</div>
        <textarea class="notes-field" id="zone_foie" rows="2" placeholder="Foie, vésicule, duodénum, pancréas..."></textarea>
      </div>

      <div class="subsection">
        <div class="subsection-title">Zone rénale / surrénale</div>
        <textarea class="notes-field" id="zone_rein" rows="2" placeholder="Reins D/G, surrénales, vessie..."></textarea>
      </div>

    </div>
  </div>

  <!-- ===== SECTION 10 : NOTES ===== -->
  <div class="section" id="sec10">
    <div class="section-header" onclick="toggleSection('sec10')">
      <div class="section-icon">✎</div>
      <div class="section-title-wrap">
        <div class="section-title">10 · Notes & Observations libres</div>
        <div class="section-subtitle">Commentaires, antécédents, questionnaire complémentaire</div>
      </div>
      <span class="section-counter" id="cnt-sec10">—</span>
      <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="section-body" id="body-sec10">

      <div class="subsection">
        <div class="subsection-title">Antécédents pathologiques significatifs</div>
        <textarea class="notes-field" rows="3" id="antecedents" placeholder="Pathologies chroniques, chirurgies, traitements en cours..."></textarea>
      </div>

      <div class="subsection">
        <div class="subsection-title">Alimentation habituelle</div>
        <div class="option-grid">
          <div class="check-option"><input type="checkbox" id="alim1" name="alimentation"><label for="alim1">Omnivore</label></div>
          <div class="check-option"><input type="checkbox" id="alim2" name="alimentation"><label for="alim2">Végétarien</label></div>
          <div class="check-option"><input type="checkbox" id="alim3" name="alimentation"><label for="alim3">Végétalien / vegan</label></div>
          <div class="check-option"><input type="checkbox" id="alim4" name="alimentation"><label for="alim4">Macrobiotique</label></div>
          <div class="check-option"><input type="checkbox" id="alim5" name="alimentation"><label for="alim5">Crudivore</label></div>
          <div class="check-option"><input type="checkbox" id="alim6" name="alimentation"><label for="alim6">Alimentation industrielle prédominante</label></div>
          <div class="check-option"><input type="checkbox" id="alim7" name="alimentation"><label for="alim7">Excès de sucres / amidons</label></div>
          <div class="check-option"><input type="checkbox" id="alim8" name="alimentation"><label for="alim8">Excès de viandes / graisses animales</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Activité physique</div>
        <div class="option-grid">
          <div class="radio-option"><input type="radio" name="activite_physique" id="actph1" value="intense"><label for="actph1">Intense et régulière</label></div>
          <div class="radio-option"><input type="radio" name="activite_physique" id="actph2" value="moderee"><label for="actph2">Modérée</label></div>
          <div class="radio-option"><input type="radio" name="activite_physique" id="actph3" value="legere"><label for="actph3">Légère / occasionnelle</label></div>
          <div class="radio-option"><input type="radio" name="activite_physique" id="actph4" value="sedentaire"><label for="actph4">Sédentaire</label></div>
        </div>
      </div>

      <div class="subsection">
        <div class="subsection-title">Observations libres du naturopathe</div>
        <textarea class="notes-field" rows="4" id="notes_libres" placeholder="Impressions générales, éléments non-dits, gestuelle, débit de parole, attitude en consultation..."></textarea>
      </div>

    </div>
  </div>

  <!-- ====== ACTION BAR ====== -->
  <div class="action-bar">
    <button class="btn btn-secondary" onclick="resetForm()">
      ↺ Réinitialiser
    </button>
    <button class="btn btn-secondary" onclick="printBilan()">
      ⎙ Imprimer la fiche
    </button>
    <button class="btn btn-primary" id="bilanBtn" onclick="lancerBilan()">
      ✦ Générer le bilan naturopathique
    </button>
  </div>

  <!-- ====== PLACEHOLDER BILAN ====== -->
  <div id="bilanResult" style="display:none; margin-top:2rem; background:rgba(255,255,255,0.85); backdrop-filter:blur(8px); border:1px solid var(--border-mid); border-radius:18px; padding:2rem;">
    <div style="text-align:center; margin-bottom:1.5rem;">
      <div style="font-family:'Cormorant Garamond',serif; font-size:1.8rem; color:var(--moss); margin-bottom:0.3rem;">Bilan naturopathique</div>
      <div style="font-size:0.8rem; color:var(--text-muted);">Généré automatiquement selon les critères remplis — à compléter, interpréter et moduler par le praticien via le questionnement et l'anamnèse.</div>
    </div>
    <div id="bilanContent"></div>
  </div>

</div>
</div><!-- end page-wrapper -->

<script>
// ====== ORGAN OBSERVATIONS (section 6 — checkboxes, no table) ======
// State is determined by the algorithm based on data-dir="pos"/"neg" on checkboxes

// ====== LEAVES ANIMATION ======
function createLeaf() {
  const container = document.getElementById('leaves-container');
  const leaf = document.createElement('div');
  leaf.className = 'bg-leaf';
  const size = 8 + Math.random() * 14;
  const colors = ['#7ab060', '#5c8a50', '#9fd070', '#8ec068', '#a8d87a', '#6a9e58'];
  const color = colors[Math.floor(Math.random() * colors.length)];
  leaf.style.cssText = `
    left: ${Math.random() * 100}%;
    width: ${size}px;
    height: ${size * 0.65}px;
    background: ${color};
    border-radius: 50% 0 50% 0;
    animation-duration: ${8 + Math.random() * 14}s;
    animation-delay: ${Math.random() * 8}s;
  `;
  container.appendChild(leaf);
  setTimeout(() => leaf.remove(), 25000);
}

setInterval(createLeaf, 1800);
for (let i = 0; i < 5; i++) setTimeout(createLeaf, i * 600);

// ====== SECTION TOGGLE ======
function toggleSection(id) {
  const body = document.getElementById('body-' + id);
  const header = body.previousElementSibling;
  const isOpen = body.classList.contains('open');
  if (isOpen) {
    body.classList.remove('open');
    body.style.display = 'none';
    header.classList.remove('open');
  } else {
    body.classList.add('open');
    body.style.display = 'block';
    header.classList.add('open');
  }
}

// ====== SLIDER UPDATE ======
function updateSlider(input, displayId) {
  const val = input.value;
  const display = document.getElementById(displayId);
  display.textContent = val;
  const pct = ((val - 1) / 9) * 100;
  input.style.background = `linear-gradient(to right, var(--sage) 0%, var(--sage) ${pct}%, var(--border-mid) ${pct}%)`;
}

// ====== PROGRESS ======
function updateProgress() {
  const allInputs = document.querySelectorAll('input[type=radio]:checked, input[type=checkbox]:checked');
  const sliders = document.querySelectorAll('input[type=range]');
  const total = allInputs.length;
  const maxExpected = 80;
  const pct = Math.min(100, Math.round((total / maxExpected) * 100));
  document.getElementById('progressBar').style.width = pct + '%';

  // Update counters per section
  ['sec1','sec2','sec3','sec4','sec5','sec6','sec7','sec8','sec9','sec10'].forEach(sid => {
    const body = document.getElementById('body-' + sid);
    const cnt = document.getElementById('cnt-' + sid);
    if (!body || !cnt) return;
    const checked = body.querySelectorAll('input[type=radio]:checked, input[type=checkbox]:checked').length;
    const n = checked;
    if (n > 0) {
      cnt.textContent = n;
      cnt.classList.add('has-value');
    } else {
      cnt.textContent = '—';
      cnt.classList.remove('has-value');
    }
  });
}

// Listen to all changes
document.addEventListener('change', () => { updateProgress(); saveState(); });

// ====== PERSISTENCE (localStorage) ======
const STORAGE_KEY = 'bilanVitalite_v8_state';

function saveState() {
  const state = {};

  // Radios → name: value
  const radioNames = new Set();
  document.querySelectorAll('input[type=radio]').forEach(i => radioNames.add(i.name));
  radioNames.forEach(name => {
    const checked = document.querySelector(`input[name="${name}"]:checked`);
    state['radio_' + name] = checked ? checked.value : null;
  });

  // Checkboxes → id: boolean
  document.querySelectorAll('input[type=checkbox]').forEach(i => {
    if (i.id) state['check_' + i.id] = i.checked;
  });

  // Text / date inputs → id: value
  document.querySelectorAll('input[type=text], input[type=date]').forEach(i => {
    if (i.id) state['text_' + i.id] = i.value;
  });

  // Textareas → id: value
  document.querySelectorAll('textarea').forEach(i => {
    if (i.id) state['area_' + i.id] = i.value;
  });

  // Ranges → id: value
  document.querySelectorAll('input[type=range]').forEach(i => {
    if (i.id) state['range_' + i.id] = i.value;
  });

  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch(e) {}
}

function restoreState() {
  let raw;
  try { raw = localStorage.getItem(STORAGE_KEY); } catch(e) { return; }
  if (!raw) return;

  let state;
  try { state = JSON.parse(raw); } catch(e) { return; }

  // Radios
  Object.keys(state).forEach(key => {
    if (!key.startsWith('radio_')) return;
    const name = key.slice(6);
    const val  = state[key];
    if (val) {
      const el = document.querySelector(`input[name="${name}"][value="${CSS.escape(val)}"]`)
              || document.querySelector(`input[name="${name}"][value="${val}"]`);
      if (el) el.checked = true;
    }
  });

  // Checkboxes
  Object.keys(state).forEach(key => {
    if (!key.startsWith('check_')) return;
    const el = document.getElementById(key.slice(6));
    if (el) el.checked = !!state[key];
  });

  // Text / date
  Object.keys(state).forEach(key => {
    if (!key.startsWith('text_')) return;
    const el = document.getElementById(key.slice(5));
    if (el) el.value = state[key] || '';
  });

  // Textareas
  Object.keys(state).forEach(key => {
    if (!key.startsWith('area_')) return;
    const el = document.getElementById(key.slice(5));
    if (el) el.value = state[key] || '';
  });

  // Ranges
  Object.keys(state).forEach(key => {
    if (!key.startsWith('range_')) return;
    const id = key.slice(6);
    const el = document.getElementById(id);
    if (el) { el.value = state[key]; updateSlider(el, id + 'Val'); }
  });
}

// Debounced save for text / textarea input events
let _saveTimer = null;
function debouncedSave() {
  clearTimeout(_saveTimer);
  _saveTimer = setTimeout(saveState, 400);
}
document.addEventListener('input', e => {
  const t = e.target;
  if (t.tagName === 'TEXTAREA' || t.type === 'text' || t.type === 'date' || t.type === 'range') {
    debouncedSave();
  }
});

// ====== RESET ======
function resetForm() {
  if (!confirm('Réinitialiser toute la fiche ?')) return;
  document.querySelectorAll('input[type=radio], input[type=checkbox]').forEach(i => i.checked = false);
  document.querySelectorAll('input[type=text], input[type=date], textarea').forEach(i => i.value = '');
  document.querySelectorAll('input[type=range]').forEach(i => {
    i.value = 5;
    updateSlider(i, i.id + 'Val');
  });

  // Vider le localStorage
  try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}

  document.getElementById('bilanResult').style.display = 'none';
  updateProgress();
}

// ====== ALGORITHME BILAN NATUROPATHIQUE ======
function lancerBilan() {
  const bilanResult = document.getElementById('bilanResult');
  const bilanContent = document.getElementById('bilanContent');

  // ─── Helpers ──────────────────────────────────────────────────────────────
  const r    = name => document.querySelector(`input[name="${name}"]:checked`)?.value || null;
  const cbV  = name => [...document.querySelectorAll(`input[name="${name}"]:checked`)].map(i => i.value);
  const isOk = id   => document.getElementById(id)?.checked || false;
  const cnt  = name => document.querySelectorAll(`input[name="${name}"]:checked`).length;

  // ─── Collecte des données morphologiques ──────────────────────────────────
  const mainTest    = r('main_test');
  const tronc       = r('tronc');
  const thoraxAbd   = r('thorax_abd');
  const cage        = r('cage');
  const posture     = r('posture');
  const formeVisage = r('forme_visage');
  const grandCadre  = r('grand_cadre');
  const front       = r('front');
  const machoire    = r('machoire');
  const bouche      = r('bouche');
  const narines     = r('narines');
  const couleurPeau = r('couleur_peau');
  const texture     = r('texture');
  const humidite    = r('humidite');
  const tempPeau    = r('temp_peau');
  const pilosite    = r('pilosite');
  const mainsForm   = r('mains_forme');
  const doigts      = r('doigts');
  const craneVol    = r('crane_volume');
  const craneForme  = r('crane_forme');
  const lunulesNb   = r('lunules_nb');
  const lunulesQual = r('lunules_qual');
  const resMineral  = r('res_mineral');
  const resProtein  = r('res_protein');
  const xiphoide    = r('xiphoide');
  const poignee     = r('poignee');
  const oreilles    = r('oreilles');
  const sommeil     = r('sommeil');
  const appetit     = r('appetit');
  const attitude    = cbV('attitude');
  const pupille     = r('pupille');
  const trame       = r('trame');
  const fuchs       = r('fuchs');
  // Nouveaux critères section 1
  const tonicite      = r('tonicite');          // bonne / reduite / nulle / filiforme
  const epaules       = r('epaules');           // larges / equilibrees / ventre_domine
  const dissymetrie   = isOk('sg1');            // dissymétrie corporelle
  const hyperlaxite   = isOk('sg2');            // hyperlaxité articulaire
  const tailleMarquee = isOk('sg3');            // taille marquée
  const ventreCreux   = isOk('sg4');            // ventre creux
  const mouvSaccades  = isOk('sg5');            // mouvements saccadés
  const busteDense    = isOk('sg6');            // buste épais massif
  const raideursArt   = isOk('sg7');            // raideurs articulaires

  const nom     = document.getElementById('prenom')?.value?.trim() || 'Patient';
  const now     = new Date();
  const dateStr = now.toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' });

  // ─── 1a. AXE DILATATION / RÉTRACTION (auto-scoring) ──────────────────────
  let axeScore = 0;

  if (mainTest === 'pli_dessus_coccyx')    axeScore += 4;
  if (mainTest === 'pli_sous_pli_fessier') axeScore -= 4;

  if (couleurPeau === 'rouge')             axeScore += 3;
  if (couleurPeau === 'laiteuse')          axeScore += 3;
  if (couleurPeau === 'verte')             axeScore -= 3;
  if (couleurPeau === 'blanche')           axeScore += 1;
  if (couleurPeau === 'bleutee')           axeScore += 1;
  if (couleurPeau === 'gris_plombe')       axeScore -= 1;

  if (texture === 'spongieuse')            axeScore += 3;
  if (texture === 'epaisse')              axeScore += 2;
  if (texture === 'fine')                 axeScore -= 2;
  if (texture === 'granuleuse')           axeScore -= 1;

  if (humidite === 'humide_moite')        axeScore += 2;
  if (humidite === 'seche')              axeScore -= 2;

  if (tempPeau === 'chaude')             axeScore += 2;
  if (tempPeau === 'froide')             axeScore -= 2;

  if (cage === 'grand_ouvert')           axeScore += 2;
  if (cage === 'ferme')                  axeScore -= 1;

  if (tronc === 'tronc_domine')          axeScore += 2;
  if (tronc === 'membres_dominent')      axeScore -= 2;

  if (thoraxAbd === 'abdomen_fort')      axeScore += 1;
  if (thoraxAbd === 'thorax_fort')       axeScore -= 1;

  if (epaules === 'larges')              axeScore -= 1;
  if (epaules === 'ventre_domine')       axeScore += 1;

  if (grandCadre === 'grand')            axeScore += 1;
  if (grandCadre === 'petit')            axeScore -= 1;

  if (pilosite === 'developpe')          axeScore += 1;
  if (pilosite === 'reduite')            axeScore -= 1;

  if (front === 'dominant')              axeScore -= 2;
  if (front === 'reduit')                axeScore += 1;

  if (narines === 'pincees')             axeScore -= 1;

  if (fuchs === 'arrondi')               axeScore += 2;
  if (fuchs === 'aplati')                axeScore -= 2;

  // Nouveaux critères
  if (tonicite === 'nulle')              axeScore += 2;
  if (tonicite === 'filiforme')          axeScore -= 2;
  if (hyperlaxite)                       axeScore += 2;
  if (dissymetrie)                       axeScore -= 2;
  if (busteDense)                        axeScore += 2;

  let effectiveAxe;
  if      (axeScore >= 3)  effectiveAxe = 'dilate';
  else if (axeScore <= -3) effectiveAxe = 'retracte';
  else                     effectiveAxe = 'equilibre';

  const axeLabel = {
    'dilate':    'Dilaté — axe sanguino-pléthorique',
    'equilibre': 'Équilibré / Musculaire',
    'retracte':  'Rétracté — axe neuro-arthritique'
  }[effectiveAxe];

  // ─── 1b. TEMPÉRAMENT NATUROPATHIQUE (Marchesseau) — algorithme revu v2 ────
  //  Références (doc Morpho-phys.) : M=50/50 · S=35/45 · D=25/35 · O=10/10
  //                                  R=45/35 · C=35/25 · N=25/15
  //  Principe : M est la référence (la plus fréquente), O et N sont des cas extrêmes
  //             nécessitant plusieurs marqueurs cliniques spécifiques pour être retenus.

  const natS = { O:0, D:0, S:0, M:0, R:0, C:0, N:0 };

  // ── MUSCULAIRE — tempérament idéal, rare (SN 50% / GE 50%) ──
  // Pas de score de base : M ne doit ressortir que si les critères positifs sont
  // clairement réunis. La plupart des patients dégénèrent vers S (dilaté) ou R (rétracté).
  if (mainTest === 'pli_au_pli_fessier')   natS.M += 5;
  if (cage === 'moyen')                    natS.M += 3;
  if (posture === 'droite')                natS.M += 3;
  if (couleurPeau === 'rose_normale')      natS.M += 2;
  if (poignee === 'ferme')                 natS.M += 2;
  if (isOk('vm_p9'))                       natS.M += 2; // muscles fermes
  if (tonicite === 'bonne')                natS.M += 3;
  if (epaules === 'equilibrees')           natS.M += 2;
  if (tailleMarquee)                       natS.M += 2;
  if (formeVisage === 'carre' || formeVisage === 'rectangulaire' || formeVisage === 'ovale') natS.M += 1;
  if (machoire === 'puissante')            natS.M += 1;
  if (attitude.includes('ouverte'))        natS.M += 1;
  if (isOk('vm_p10'))                      natS.M += 1; // bonne réserve minérale
  if (isOk('vm_p11'))                      natS.M += 1; // en forme le matin
  if (effectiveAxe === 'equilibre')        natS.M += 1;

  // ── SANGUIN (SN 35% / GE 45% — dilatation, buste épais, sang chargé) ──
  if (couleurPeau === 'rouge')             natS.S += 5; // signe cardinal
  if (busteDense)                          natS.S += 4; // buste épais massif
  if (mainTest === 'pli_dessus_coccyx')    natS.S += 3;
  if (cage === 'grand_ouvert')             natS.S += 3;
  if (tempPeau === 'chaude')               natS.S += 2;
  if (humidite === 'humide_moite')         natS.S += 2;
  if (effectiveAxe === 'dilate')           natS.S += 2;
  if (tronc === 'tronc_domine')            natS.S += 2;
  if (pilosite === 'developpe')            natS.S += 2;
  if (epaules === 'equilibrees')           natS.S += 1;
  if (tonicite === 'bonne')                natS.S += 1;
  if (isOk('vm_p12'))                      natS.S += 1; // jamais froid
  if (mainsForm === 'brevilignes_epaisses') natS.S += 2;
  if (attitude.includes('ouverte'))        natS.S += 1;

  // ── DIGESTIF (SN 25% / GE 35% — dilatation, abdomen, mollesse) ──
  if (thoraxAbd === 'abdomen_fort')        natS.D += 4;
  if (epaules === 'ventre_domine')         natS.D += 3;
  if (tonicite === 'reduite')              natS.D += 3;
  if (texture === 'spongieuse' || texture === 'epaisse') natS.D += 2;
  if (couleurPeau === 'blanche' || couleurPeau === 'bleutee') natS.D += 2;
  if (mainTest === 'pli_dessus_coccyx')    natS.D += 2;
  if (tronc === 'tronc_domine')            natS.D += 2;
  if (attitude.includes('lente'))          natS.D += 2;
  if (formeVisage === 'rond')              natS.D += 1;
  if (isOk('sc_g2'))                       natS.D += 1; // ventre/abdomen

  // ── OBÈSE (SN ~10% / GE ~10% — extrême, cas clinique avancé) ──
  // Marqueurs SPÉCIFIQUES requis — sans eux, O ne peut pas dominer
  let obMarkers = 0;
  if (texture === 'spongieuse')            { natS.O += 3; obMarkers++; }
  if (couleurPeau === 'laiteuse')          { natS.O += 3; obMarkers++; }
  if (hyperlaxite)                         { natS.O += 5; obMarkers += 2; } // signe cardinal
  if (tonicite === 'nulle')                { natS.O += 4; obMarkers += 2; } // signe cardinal
  if (formeVisage === 'triangulaire_base_basse') { natS.O += 2; obMarkers++; } // visage poire
  if (epaules === 'ventre_domine')         natS.O += 2;
  if (thoraxAbd === 'abdomen_fort')        natS.O += 2;
  if (mainTest === 'pli_dessus_coccyx')    natS.O += 1;
  if (isOk('sc_g2') || isOk('sc_g3'))     natS.O += 1;
  if (attitude.includes('lente'))          natS.O += 1;
  // GATE : sans ≥2 marqueurs extrêmes, O est plafonné
  if (obMarkers < 2) {
    const maxDM = Math.max(natS.D, natS.M);
    natS.O = Math.min(natS.O, maxDM - 2);
  }

  // ── RESPIRATOIRE (SN 45% / GE 35% — rétraction douce, athlétique) ──
  if (thoraxAbd === 'thorax_fort')         natS.R += 5;
  if (epaules === 'larges')                natS.R += 4;
  if (narines === 'musclees_ouvertes')     natS.R += 3;
  if (isOk('po_m1'))                       natS.R += 2;
  if (tonicite === 'bonne')                natS.R += 2;
  if (posture === 'droite')                natS.R += 2;
  if (tailleMarquee)                       natS.R += 2;
  if (cage === 'ferme')                    natS.R += 1;
  if (couleurPeau === 'rose_normale')      natS.R += 1;
  if (mainTest === 'pli_au_pli_fessier' || mainTest === 'pli_sous_pli_fessier') natS.R += 1;
  if (isOk('vm_p9'))                       natS.R += 1;
  if (formeVisage === 'ovale' || formeVisage === 'allonge') natS.R += 1;
  if (attitude.includes('ouverte'))        natS.R += 1;

  // ── CÉRÉBRAL (SN 35% / GE 25% — rétraction, front dominant, peau jaune) ──
  if (front === 'dominant')                natS.C += 5; // signe cardinal
  if (couleurPeau === 'jaunatre')          natS.C += 4; // peau jaune
  if (texture === 'fine' || texture === 'granuleuse') natS.C += 3;
  if (craneVol === 'front_frontal')        natS.C += 3;
  if (humidite === 'seche')                natS.C += 2;
  if (raideursArt)                         natS.C += 2; // raideurs articulaires
  if (mainTest === 'pli_sous_pli_fessier') natS.C += 2;
  if (effectiveAxe === 'retracte')         natS.C += 1;
  if (formeVisage === 'allonge')           natS.C += 1;
  if (mainsForm === 'longilignes_fines')   natS.C += 2;
  if (mouvSaccades)                        natS.C += 1;
  if (isOk('vn_p2'))                       natS.C += 1; // regard vif et présent

  // ── NERVEUX (SN 25% / GE 15% — extrême, cas clinique avancé) ──
  // Marqueurs SPÉCIFIQUES requis — sans eux, N ne peut pas dominer
  let nervMarkers = 0;
  if (couleurPeau === 'verte')             { natS.N += 4; nervMarkers += 2; } // signe fort
  if (couleurPeau === 'gris_plombe')       { natS.N += 2; nervMarkers++; }    // variante
  if (dissymetrie)                         { natS.N += 4; nervMarkers += 2; } // signe cardinal
  if (tonicite === 'filiforme')            { natS.N += 4; nervMarkers += 2; } // signe cardinal
  if (ventreCreux)                         { natS.N += 3; nervMarkers++; }
  // Critères secondaires (ne suffisent pas seuls)
  if (effectiveAxe === 'retracte')         natS.N += 2;
  if (mainTest === 'pli_sous_pli_fessier') natS.N += 2;
  if (texture === 'fine')                  natS.N += 1;
  if (mainsForm === 'longilignes_fines')   natS.N += 2;
  if (mouvSaccades)                        natS.N += 1;
  if (isOk('sn1') && isOk('sn2'))         natS.N += 1; // hyperactivité + anxiété cumulées
  if (isOk('arc4'))                        natS.N += 1;
  if (attitude.includes('fuyante'))        natS.N += 1;
  // GATE : sans ≥2 marqueurs extrêmes, N est plafonné
  if (nervMarkers < 2) {
    const maxCM = Math.max(natS.C, natS.M);
    natS.N = Math.min(natS.N, maxCM - 2);
  }

  // ── Résultat final : trier, dominant + secondaire ──
  const natSorted  = Object.entries(natS).filter(([,v]) => v > 0).sort((a,b) => b[1]-a[1]);
  const natDominant = natSorted.length > 0 ? natSorted[0][0] : null;
  // Secondaire : score ≥ 60% du dominant (seuil relevé vs 55% précédent)
  const natSecond   = (natSorted.length > 1 && natSorted[1][1] >= natSorted[0][1] * 0.60) ? natSorted[1][0] : null;

  const tempLabels = {
    O:'Obèse', D:'Digestif', S:'Sanguin',
    M:'Musculaire', R:'Respiratoire', C:'Cérébral', N:'Nerveux'
  };
  const tempDesc = {
    O:'Terrain dilaté adipeux extrême — effondrement glandulaire, tissu infiltré d\'eau, surcharges colloïdales massives, tonicité nulle.',
    D:'Appareil digestif dominant — abdomen volumineux, foie et intestins sollicités, tendance aux surcharges hépatiques et colloïdales.',
    S:'Pléthore sanguine — buste épais, énergie circulatoire forte, chaleur, sang chargé. Risques cardiovasculaires à surveiller.',
    M:'Équilibre musculo-osseux — tempérament de référence, grande réserve d\'énergie potentielle, récupération rapide.',
    R:'Thorax dominant — corps athlétique, bonne vitalité, sensibilité pulmonaire et ORL. Faiblesse digestive secondaire.',
    C:'Étage supérieur actif — front dominant, peau jaune, dépense nerveuse cérébrale intense. Surcharges cristalloïdales fréquentes.',
    N:'Rétraction nerveuse extrême — chétif, dissymétrique, GE épuisées, SN à bout. Hyper-réactivité et fragilité totale.'
  };

  const silLabel  = { 'pli_dessus_coccyx':'Bréviligne', 'pli_au_pli_fessier':'Normoligne', 'pli_sous_pli_fessier':'Longiligne' }[mainTest] || null;
  const glandType = { 'front_frontal':'Hypophysaire','haut_superieur':'Thyroïdien','arriere_haut':'Pinéalien','posterieur':'Surrénalien','harmonieux':'Génital' }[craneVol] || null;

  // ─── 2. TEMPÉRAMENT HIPPOCRATIQUE ────────────────────────────────────────
  const hipS = { sanguin:0, bilieux:0, nerveux:0, lymphatique:0 };

  if (effectiveAxe === 'dilate')            hipS.sanguin += 3;
  if (couleurPeau === 'rouge')              hipS.sanguin += 2;
  if (tempPeau === 'chaude')                hipS.sanguin += 2;
  if (cage === 'grand_ouvert')              hipS.sanguin += 2;
  if (natS.S >= 4)                          hipS.sanguin += 3;
  if (thoraxAbd === 'thorax_fort')          hipS.sanguin += 1;
  if (mainTest === 'pli_dessus_coccyx')     hipS.sanguin += 1;
  if (pilosite === 'developpe')             hipS.sanguin += 1;
  if (humidite === 'humide_moite')          hipS.sanguin += 1;
  if (busteDense)                           hipS.sanguin += 1;
  if (mainsForm === 'brevilignes_epaisses') hipS.sanguin += 1;

  if (natS.M >= 4)                          hipS.bilieux += 3;
  if (natS.D >= 3)                          hipS.bilieux += 1;
  if (machoire === 'puissante')             hipS.bilieux += 2;
  if (couleurPeau === 'jaunatre')           hipS.bilieux += 2;
  if (formeVisage === 'carre')              hipS.bilieux += 2;
  if (doigts === 'spatules')                hipS.bilieux += 2;
  if (attitude.includes('agitee'))          hipS.bilieux += 1;
  if (pilosite === 'developpe')             hipS.bilieux += 1;
  if (cage === 'moyen')                     hipS.bilieux += 1;
  if (craneForme === 'large')               hipS.bilieux += 1;
  if (thoraxAbd === 'abdomen_fort')         hipS.bilieux += 1;
  if (formeVisage === 'rectangulaire')      hipS.bilieux += 1;

  if (natS.N >= 4)                          hipS.nerveux += 3;
  if (natS.C >= 4)                          hipS.nerveux += 2;
  if (effectiveAxe === 'retracte')          hipS.nerveux += 2;
  if (front === 'dominant')                 hipS.nerveux += 2;
  if (mainTest === 'pli_sous_pli_fessier')  hipS.nerveux += 2;
  if (couleurPeau === 'verte')              hipS.nerveux += 2;
  if (pilosite === 'reduite')               hipS.nerveux += 1;
  if (texture === 'fine')                   hipS.nerveux += 1;
  if (mainsForm === 'longilignes_fines')    hipS.nerveux += 1;
  if (craneForme === 'allonge')             hipS.nerveux += 1;
  if (isOk('sn2'))                          hipS.nerveux += 1;
  if (isOk('os7'))                          hipS.nerveux += 1;
  if (formeVisage === 'allonge')            hipS.nerveux += 1;
  if (humidite === 'seche')                 hipS.nerveux += 1;

  if (natS.O >= 4)                          hipS.lymphatique += 3;
  if (effectiveAxe === 'dilate')            hipS.lymphatique += 1;
  if (couleurPeau === 'blanche' || couleurPeau === 'laiteuse') hipS.lymphatique += 2;
  if (mainTest === 'pli_dessus_coccyx')     hipS.lymphatique += 2;
  if (texture === 'spongieuse')             hipS.lymphatique += 2;
  if (humidite === 'humide_moite')          hipS.lymphatique += 1;
  if (tronc === 'tronc_domine')             hipS.lymphatique += 1;
  if (cnt('surcharge_colloidal') >= 4)      hipS.lymphatique += 1;
  if (grandCadre === 'grand')               hipS.lymphatique += 1;
  if (mainsForm === 'brevilignes_epaisses') hipS.lymphatique += 1;

  const hipSorted = Object.entries(hipS).filter(([,v]) => v > 0).sort((a,b) => b[1]-a[1]);
  const hipLabels = { sanguin:'Sanguin', bilieux:'Bilieux', nerveux:'Nerveux', lymphatique:'Lymphatique' };
  const hipDescr  = {
    sanguin:    'Circulation abondante, pléthorique, chaleur, vigueur physique. Risques : hypertension, congestion, AVC.',
    bilieux:    'Dynamisme, réactivité biliaire, volonté forte. Risques : surcharge hépatique, cholestérol, colère.',
    nerveux:    'Sensibilité exacerbée, maigreur, excitabilité. Risques : épuisement nerveux, arthritisme, terrain acide.',
    lymphatique:'Stagnation lymphatique, surcharges colloïdales, œdème. Risques : immunité basse, tumeurs bénignes, obésité.'
  };
  const hipDominant = hipSorted.length > 0 ? hipSorted[0][0] : null;
  const hipSecond   = (hipSorted.length > 1 && hipSorted[1][1] >= hipSorted[0][1] * 0.55) ? hipSorted[1][0] : null;

  // ─── 3. SURCHARGES ────────────────────────────────────────────────────────
  // Base depuis section 7
  let collC = cnt('surcharge_colloidal');
  let crystC = cnt('surcharge_crystal');
  let fatC  = cnt('surcharge_fat');

  // Renforcement depuis sections 3/4/9 — UNIQUEMENT les signes sans équivalent en section 7
  if (isOk('sc5'))  crystC++;  // Psoriasis — absent de la liste sec7
  if (isOk('ch2'))  crystC++;  // Cheveux secs/fourchus — absent de la liste sec7
  if (isOk('arc2')) fatC++;    // Arc lipidique iris — iridologie uniquement
  if (isOk('arc3')) crystC++;  // Arc sodique iris — iridologie uniquement

  const surchLevel = n => {
    if (n === 0) return null;
    if (n <= 2)  return { label:'Faible',           col:'#7a9e7e', pct:20 };
    if (n <= 5)  return { label:'Modérée',           col:'#b8956a', pct:50 };
    if (n <= 9)  return { label:'Importante',        col:'#c26a3e', pct:78 };
    return             { label:'Très importante',    col:'#8B2000', pct:100 };
  };

  const collLvl  = surchLevel(collC);
  const crystLvl = surchLevel(crystC);
  const fatLvl   = surchLevel(fatC);
  const hasSurch = collC > 0 || crystC > 0 || fatC > 0;

  // Dominance
  const maxS = Math.max(collC, crystC, fatC);
  let dominantSurch = null;
  if (maxS > 0) {
    if (collC === maxS && collC > crystC && collC > fatC) dominantSurch = 'colloïdale';
    else if (crystC === maxS && crystC > collC && crystC > fatC) dominantSurch = 'cristalloïdale';
    else if (fatC === maxS && fatC > collC && fatC > crystC) dominantSurch = 'graisseuse';
    else dominantSurch = 'mixte';
  }

  // ─── 4. VITALITÉ ──────────────────────────────────────────────────────────
  const vitCats = [
    { key:'evMetab', label:'Énergie vitale métabolique', icon:'⚡', maxPos:12, maxNeg:9 },
    { key:'evNerv',  label:'Énergie vitale nerveuse',    icon:'🧠', maxPos:6,  maxNeg:7 },
    { key:'evPsy',   label:'Énergie psychique & émotionnelle', icon:'💫', maxPos:7, maxNeg:7 }
  ];

  const vitScores = {};
  let vitTotal = 0, vitCount = 0;

  vitCats.forEach(cat => {
    const posC = document.querySelectorAll(`[data-vitcat="${cat.key}"][data-dir="pos"]:checked`).length;
    const negC = document.querySelectorAll(`[data-vitcat="${cat.key}"][data-dir="neg"]:checked`).length;
    if (posC + negC === 0) { vitScores[cat.key] = null; return; }

    let sc = 5 + (posC / cat.maxPos * 4.5) - (negC / cat.maxNeg * 4.5);

    // Modifieurs spécifiques
    if (cat.key === 'evMetab') {
      if (xiphoide === 'long_developpe')                sc += 0.6;
      if (xiphoide === 'court_absent')                  sc -= 0.5;
      if (xiphoide === 'douloureux')                    sc -= 0.8;
      if (resMineral === 'bonne')                       sc += 0.4;
      if (resMineral === 'faible')                      sc -= 0.5;
      if (resProtein === 'bonne')                       sc += 0.4;
      if (resProtein === 'faible')                      sc -= 0.5;
      if (appetit === 'gros_appetit')                   sc += 0.3;
      if (appetit === 'faible')                         sc -= 0.4;
      if (posture === 'avachie')                        sc -= 0.3;
    }

    if (cat.key === 'evNerv') {
      if (lunulesNb === '0-2')                          sc -= 1.2;
      else if (lunulesNb === '3-4')                     sc -= 0.6;
      else if (lunulesNb === '7-8')                     sc += 0.5;
      else if (lunulesNb === '9-10')                    sc += 1.0;
      if (lunulesQual === 'faibles')                    sc -= 0.3;
      if (posture === 'droite')                         sc += 0.3;
      if (posture === 'avachie')                        sc -= 0.6;
      if (oreilles === 'grandes_toniques')              sc += 0.3;
      if (oreilles === 'petites_molles')                sc -= 0.4;
      if (poignee === 'ferme')                          sc += 0.3;
      if (poignee === 'molle')                          sc -= 0.6;
      if (pupille === 'mydriase')                       sc -= 0.4;
      if (pupille === 'instable')                       sc -= 0.6;
      if (isOk('arc4'))                                 sc -= 0.4; // anneau nerveux iris
    }

    if (cat.key === 'evPsy') {
      if (sommeil === 'tres_bon')                       sc += 0.6;
      else if (sommeil === 'bon')                       sc += 0.2;
      else if (sommeil === 'moyen')                     sc -= 0.4;
      else if (sommeil === 'mauvais')                   sc -= 0.9;
    }

    sc = Math.max(1, Math.min(10, sc));
    sc = Math.round(sc * 2) / 2; // arrondi au 0.5 près
    vitScores[cat.key] = sc;
    vitTotal += sc;
    vitCount++;
  });

  const globalVit = vitCount > 0 ? Math.round((vitTotal / vitCount) * 2) / 2 : null;

  // Couleurs et labels selon score
  const scCol = s => s >= 7 ? '#4a7050' : s >= 4.5 ? '#b8956a' : '#c26a3e';
  const scLbl = s => s >= 8 ? 'Excellente' : s >= 6.5 ? 'Bonne' : s >= 5 ? 'Satisfaisante' : s >= 3.5 ? 'Faible' : 'Très faible';
  const scPct = s => Math.round((s - 1) / 9 * 100);

  // ─── 5. BILAN ÉMONCTORIEL ─────────────────────────────────────────────────
  const organNames = {
    estomac:'Estomac · Duodénum', intestin_grele:'Intestin grêle', colons:'Côlons',
    foie:'Foie · Vésicule biliaire', pancreas:'Pancréas', reins:'Reins · Urinaire',
    poumons:'Poumons · ORL', coeur:'Cœur · Circulation', lymphe:'Système lymphatique',
    thyroide:'Thyroïde', surrenales:'Surrénales', peau_emonct:'Peau (émonctoire)',
    genitaux:'Génitaux · Hormones', colonne:'Colonne · Ostéo-musc.'
  };

  const emonctBilan = {};
  Object.keys(organNames).forEach(org => {
    const pos = document.querySelectorAll(`[data-organ="${org}"][data-dir="pos"]:checked`).length;
    const neg = document.querySelectorAll(`[data-organ="${org}"][data-dir="neg"]:checked`).length;
    const tot = pos + neg;
    if (tot === 0) return;
    const ratio = pos / tot;
    let status;
    if (ratio >= 0.75)     status = 'fort';
    else if (ratio >= 0.5) status = 'normal';
    else if (ratio >= 0.2) status = 'soutenir';
    else                   status = 'difficulte';
    emonctBilan[org] = { status, pos, neg };
  });

  const grp = s => Object.entries(emonctBilan).filter(([,v])=>v.status===s).map(([k])=>organNames[k]);
  const forts     = grp('fort');
  const normaux   = grp('normal');
  const asoutenir = grp('soutenir');
  const endiff    = grp('difficulte');
  const aucunOrg  = Object.keys(emonctBilan).length === 0;

  // ─── Générer la sortie HTML ────────────────────────────────────────────────
  const section = (icon, titre, content) => `
    <div style="background:rgba(255,255,255,0.82);border-radius:14px;padding:1.4rem 1.6rem;margin-bottom:1.1rem;border:1px solid var(--border-light);box-shadow:0 2px 12px rgba(60,100,60,0.06);">
      <div style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--moss);font-weight:600;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:1px solid var(--border-light);">
        ${icon}&ensp;${titre}
      </div>
      ${content}
    </div>`;

  const pill = (txt, bg, col='#fff') =>
    `<span style="display:inline-block;background:${bg};color:${col};font-size:0.78rem;font-weight:500;padding:0.2em 0.75em;border-radius:20px;margin:0.2rem 0.2rem 0.2rem 0;">${txt}</span>`;

  const bar = (pct, col) =>
    `<div style="background:rgba(0,0,0,0.08);border-radius:4px;height:8px;width:100%;margin-top:0.3rem;overflow:hidden;"><div style="height:100%;width:${pct}%;background:${col};border-radius:4px;transition:width 0.8s ease;"></div></div>`;

  // ── Section 1 : Tempérament ──
  let s1 = '';

  s1 += `<div style="margin-bottom:1rem;">`;
  s1 += `<div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:var(--sage-dark);margin-bottom:0.35rem;">Tempérament naturopathique (Marchesseau) — déterminé par algorithme</div>`;
  if (natDominant) {
    s1 += pill('◆ ' + tempLabels[natDominant] + ' — dominant', 'var(--moss)', '#fff');
    if (natSecond) s1 += pill('◇ ' + tempLabels[natSecond] + ' — secondaire', 'var(--sage)', '#fff');
    if (silLabel)  s1 += pill(silLabel, 'var(--leaf-light)', 'var(--moss)');
    s1 += pill(axeLabel, 'rgba(122,158,126,0.18)', 'var(--moss)');
    s1 += `<div style="font-size:0.82rem;color:var(--text-secondary);margin-top:0.5rem;line-height:1.5;">${[natDominant, natSecond].filter(Boolean).map(t=>tempDesc[t]).join(' | ')}</div>`;
  } else {
    s1 += `<em style="font-size:0.85rem;color:var(--text-muted);">Critères insuffisants — renseigner sections 1–3 (silhouette, peau, visage).</em>`;
    if (silLabel) s1 += pill(silLabel, 'var(--leaf-light)', 'var(--moss)');
    s1 += pill(axeLabel, 'rgba(122,158,126,0.18)', 'var(--moss)');
  }
  s1 += `</div>`;

  // Hippocratique
  if (hipDominant) {
    s1 += `<div style="margin-bottom:0.8rem;">`;
    s1 += `<div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:var(--sage-dark);margin-bottom:0.35rem;">Tempérament hippocratique (déterminé par algorithme)</div>`;
    s1 += pill('◆ ' + hipLabels[hipDominant] + ' — dominant', '#4a7050', '#fff');
    if (hipSecond) s1 += pill('◇ ' + hipLabels[hipSecond] + ' — secondaire', 'var(--sage)', '#fff');
    s1 += `<div style="font-size:0.82rem;color:var(--text-secondary);margin-top:0.5rem;line-height:1.5;">${hipDescr[hipDominant]}${hipSecond ? '<br>'+hipDescr[hipSecond] : ''}</div>`;
    s1 += `</div>`;
  } else {
    s1 += `<p style="font-size:0.82rem;color:var(--text-muted);"><em>Critères insuffisants pour déterminer le tempérament hippocratique.</em></p>`;
  }

  // Type glandulaire
  if (glandType) {
    s1 += `<div><div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:var(--sage-dark);margin-bottom:0.35rem;">Type glandulaire dominant</div>`;
    s1 += pill('◆ ' + glandType, 'var(--gold)', '#fff');
    s1 += `</div>`;
  }

  // ── Section 2 : Surcharges ──
  let s2 = '';
  if (!hasSurch) {
    s2 = `<p style="font-size:0.85rem;color:var(--text-muted);"><em>Aucune surcharge relevée — terrain peu ou non encrassé, ou section 7 non remplie.</em></p>`;
  } else {
    if (dominantSurch) {
      const domCol = dominantSurch==='colloïdale'?'var(--bark)':dominantSurch==='cristalloïdale'?'#5a7d9a':dominantSurch==='graisseuse'?'#8B6914':'#7a6e5a';
      s2 += `<div style="margin-bottom:0.9rem;font-size:0.85rem;color:var(--text-secondary);">Type <strong style="color:${domCol};">${dominantSurch}</strong> dominant — `;
      if (dominantSurch==='colloïdale') s2 += 'terrain dilaté, mucus, surcharges douces. Émonctoires : foie, intestins, lymphe, peau.';
      else if (dominantSurch==='cristalloïdale') s2 += 'terrain rétracté, acidose. Émonctoires : reins, peau (sudation), poumons.';
      else if (dominantSurch==='graisseuse') s2 += 'excès lipidique, cholestérol. Émonctoires : foie, vésicule, peau, intestins.';
      else s2 += 'plusieurs types coexistent — stratégie mixte à adapter à la vitalité.';
      s2 += `</div>`;
    }

    const surchargeRow = (label, count, lvl, col) => {
      if (!lvl) return '';
      return `<div style="margin-bottom:0.7rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:0.82rem;font-weight:500;color:var(--text-primary);">${label}</span>
          <span style="font-size:0.78rem;color:${col};font-weight:600;">${count} signe${count>1?'s':''} · ${lvl.label}</span>
        </div>
        ${bar(lvl.pct, col)}
      </div>`;
    };

    s2 += surchargeRow('Surcharges colloïdales (mucus, colle)', collC, collLvl, '#8B5e3c');
    s2 += surchargeRow('Surcharges cristalloïdales / acides', crystC, crystLvl, '#5a7d9a');
    s2 += surchargeRow('Surcharges graisseuses / cholestérol', fatC, fatLvl, '#8B6914');
  }

  // ── Section 3 : Vitalité ──
  let s3 = '';
  if (vitCount === 0) {
    s3 = `<p style="font-size:0.85rem;color:var(--text-muted);"><em>Section 8 non remplie — cocher des signes de vitalité pour obtenir les scores.</em></p>`;
  } else {
    // Global
    if (globalVit !== null) {
      const gc = scCol(globalVit);
      s3 += `<div style="text-align:center;margin-bottom:1.2rem;padding:1rem;background:rgba(255,255,255,0.6);border-radius:10px;border:1px solid var(--border-light);">
        <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin-bottom:0.3rem;">Vitalité globale</div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:3rem;font-weight:600;color:${gc};line-height:1;">${globalVit}<span style="font-size:1.2rem;color:var(--text-muted);">/10</span></div>
        <div style="font-size:0.85rem;color:${gc};font-weight:500;margin-top:0.2rem;">${scLbl(globalVit)}</div>
        ${bar(scPct(globalVit), gc)}
      </div>`;
    }

    // Sous-scores
    s3 += `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.8rem;">`;
    vitCats.forEach(cat => {
      const sc = vitScores[cat.key];
      if (sc === null) return;
      const cc = scCol(sc);
      s3 += `<div style="background:rgba(255,255,255,0.5);border-radius:10px;padding:0.9rem;border:1px solid var(--border-light);">
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.3rem;">${cat.icon} ${cat.label}</div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:1.9rem;font-weight:600;color:${cc};line-height:1.1;">${sc}<span style="font-size:0.9rem;color:var(--text-muted);">/10</span></div>
        <div style="font-size:0.75rem;color:${cc};margin-bottom:0.3rem;">${scLbl(sc)}</div>
        ${bar(scPct(sc), cc)}
      </div>`;
    });
    s3 += `</div>`;
  }

  // ── Section 4 : Émonctoires ──
  let s4 = '';
  if (aucunOrg) {
    s4 = `<p style="font-size:0.85rem;color:var(--text-muted);"><em>Section 6 non remplie — cocher des observations par organe pour obtenir le bilan émonctoriel.</em></p>`;
  } else {
    const orgGroup = (list, icon, titre, bg, border) => {
      if (list.length === 0) return '';
      return `<div style="margin-bottom:0.7rem;padding:0.8rem 1rem;background:${bg};border-radius:10px;border-left:3px solid ${border};">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.45rem;color:${border};">${icon} ${titre}</div>
        <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">
          ${list.map(n=>`<span style="background:rgba(255,255,255,0.65);padding:0.2em 0.65em;border-radius:20px;font-size:0.8rem;color:var(--text-primary);">${n}</span>`).join('')}
        </div>
      </div>`;
    };

    s4 += orgGroup(forts,     '✅', 'Organes forts / puissants',   'rgba(74,112,80,0.07)',  '#4a7050');
    s4 += orgGroup(normaux,   '⚖',  'Organes corrects / normaux',  'rgba(122,158,126,0.07)','var(--sage)');
    s4 += orgGroup(asoutenir, '⚠',  'Organes à ouvrir / soutenir', 'rgba(184,149,106,0.1)', 'var(--gold)');
    s4 += orgGroup(endiff,    '❌', 'Organes en difficulté',        'rgba(194,106,62,0.08)', 'var(--alert-col)');

    if (endiff.length > 0 || asoutenir.length > 0) {
      s4 += `<div style="margin-top:0.6rem;font-size:0.8rem;color:var(--text-secondary);line-height:1.55;padding:0.7rem 1rem;background:rgba(184,149,106,0.07);border-radius:8px;">
        <strong>Priorités de drainage :</strong> Ouvrir et soutenir en priorité — ${[...endiff,...asoutenir].join(', ')}.
      </div>`;
    }
  }

  // ─── 6. RECOMMANDATIONS DE CURE ───────────────────────────────────────────

  // ── 6a. Type de cure et séquençage selon vitalité ──
  let cureType = null, cureSeq = '', cureAlert = '', cureDuree = '', cureSaison = '';

  if (globalVit !== null) {
    if (globalVit <= 3.5) {
      cureType    = 'revitalisation';
      cureSeq     = '① Revitalisation — OBLIGATOIRE EN PREMIER (terrain anergique). Pas de désintoxication avant recharge.';
      cureAlert   = '⚠ Contre-indication formelle à toute cure de désintoxication tant que la vitalité est inférieure à 4/10 — ouvrir les émonctoires sur un terrain anergique épuise encore davantage.';
      cureDuree   = '2 à 3 mois minimum, puis réévaluation.';
      cureSaison  = 'Toutes saisons — priorité absolue à la recharge.';
    } else if (globalVit <= 5.0) {
      cureType    = 'revit_puis_detox';
      cureSeq     = '① Revitalisation légère (4–6 semaines) · ② Puis désintoxication douce si vitalité remontée.';
      cureAlert   = '⚠ Vitalité insuffisante pour une désintoxication intense — débuter par la revitalisation, réévaluer après 4 semaines.';
      cureDuree   = 'Revit : 4–6 semaines · Détox douce : 3 semaines.';
      cureSaison  = 'Printemps (toujours indiqué) ou Automne si l\'énergie remonte.';
    } else if (globalVit <= 7.0) {
      cureType    = 'detox_puis_revit';
      cureSeq     = '① Désintoxication (3 à 6 semaines) · ② Revitalisation (le double de la durée détox) · ③ Stabilisation.';
      cureAlert   = '';
      cureDuree   = 'Détox : 3–6 semaines · Revit : 6–12 semaines.';
      cureSaison  = 'Printemps (prioritaire) · Automne si EV > 6.';
    } else {
      cureType    = 'detox_complete';
      cureSeq     = '① Désintoxication complète possible (jusqu\'à 2 mois) · ② Revitalisation · ③ Stabilisation à long terme.';
      cureAlert   = '';
      cureDuree   = 'Détox : 4–8 semaines · Revit : 2–3× la durée détox.';
      cureSaison  = 'Automne (grand nettoyage) ou Printemps.';
    }
  } else {
    cureType = 'indetermine';
    cureSeq  = 'Vitalité non évaluée — remplir la section 8 pour obtenir une recommandation de cure.';
  }

  // ── 6b. Bromatologie selon surcharge ──
  const alimPriv = [], alimEviter = [];

  if (dominantSurch === 'colloïdale' || collC > crystC && collC > 0) {
    alimPriv.push('Légumes verts cuits et crus (choux, poireaux, endives, fenouil)');
    alimPriv.push('Fruits frais de saison (hors banane, raisin en excès)');
    alimPriv.push('Épices et amers drainants (curcuma, gingembre, artichaut, chicorée)');
    alimPriv.push('Huiles vierges de première pression à froid (olive, lin, colza)');
    alimPriv.push('Céréales complètes sans gluten si sensibilité (riz, quinoa, millet)');
    alimEviter.push('Produits laitiers (source principale de colloïdes)');
    alimEviter.push('Amidons raffinés (pain blanc, pâtes blanches, viennoiseries)');
    alimEviter.push('Sucres industriels et édulcorants');
    alimEviter.push('Aliments fermentés en excès (surcharge de colles)');
    alimEviter.push('Graisses cuites, fritures, plats industriels');
  }
  if (dominantSurch === 'cristalloïdale' || crystC > collC && crystC > 0) {
    alimPriv.push('Aliments alcalinisants : légumes verts, fruits frais, germes, algues');
    alimPriv.push('Eaux peu minéralisées (Évian, Volvic) ou eau filtrée');
    alimPriv.push('Graines germées (reminéralisantes et alcalinisantes)');
    alimPriv.push('Huiles riches en oméga-3 (lin, cameline, poissons des mers froides)');
    alimPriv.push('Plantes reminéralisantes en infusion (ortie, prêle, avoine)');
    alimEviter.push('Protéines animales en excès (viandes rouges, charcuteries, fromages)');
    alimEviter.push('Sel raffiné et aliments ultra-salés');
    alimEviter.push('Café, alcool, sodas (très acidifiants)');
    alimEviter.push('Sucres raffinés et farines blanches (acidose métabolique)');
    alimEviter.push('Légumineuses en excès si arthritisme (acide urique)');
  }
  if (dominantSurch === 'graisseuse' || fatC > 0 && dominantSurch === 'graisseuse') {
    alimPriv.push('Légumes fibreux et drainants (artichaut, brocoli, asperge, betterave)');
    alimPriv.push('Fruits rouges et agrumes (antioxydants, drainants hépatiques)');
    alimPriv.push('Oméga-3 (poissons gras, graines de lin et chia, huile de cameline)');
    alimPriv.push('Aromates hépatiques (romarin, thym, persil, curcuma)');
    alimPriv.push('Activité physique régulière — composante bromatologique essentielle');
    alimEviter.push('Graisses saturées (beurre, fromages gras, charcuteries, viandes grasses)');
    alimEviter.push('Cholestérol alimentaire en excès (abats, jaunes d\'œuf en excès)');
    alimEviter.push('Sucres simples (favorisent la lipogenèse hépatique)');
    alimEviter.push('Alcool (hépatotoxique, favorise la stéatose)');
  }
  if (!dominantSurch || dominantSurch === 'mixte') {
    alimPriv.push('Alimentation naturelle, biologique, diversifiée');
    alimPriv.push('Crudités de saison en début de repas (enzymes digestives)');
    alimPriv.push('Céréales complètes, légumes secs bien cuits, légumineuses trempées');
    alimPriv.push('Fruits frais mûrs — idéalement loin des repas (digestion facile)');
    alimEviter.push('Aliments industriels transformés, additifs, conservateurs');
    alimEviter.push('Sucres raffinés et excès de produits animaux');
    alimEviter.push('Alcool, tabac, café en excès');
  }
  // Contre-indications communes
  alimEviter.push('Tabac — dévitalisant majeur et pro-inflammatoire systémique');

  // ── 6c. Drainage émonctoriel — plantes et techniques par organe ──
  const DRAINAGE_DB = {
    foie: {
      icon:'🟡', label:'Foie · Vésicule biliaire',
      plantes:['Artichaut (Cynara scolymus) — cholagogue, hépatoprotecteur','Radis noir (Raphanus sativus) — stimulant biliaire puissant','Pissenlit (Taraxacum officinale) — foie, reins, diurétique doux','Chardon-marie (Silybum marianum) — hépatoprotecteur'],
      hydro:['Bouillotte chaude sur le foie (soir)','Bain chaud général — vasodilatateur hépatique'],
      autres:['Réflexologie plantaire — zone foie/vésicule','Exercice physique modéré (marche, yoga)','Chi Nei Tsang (massage abdominal)'],
      alim:['Réduire amidons, produits laitiers, graisses cuites','Privilégier amer : endive, chicorée, artichaut, curcuma','Citron le matin à jeun (stimulation biliaire)']
    },
    colons: {
      icon:'🟠', label:'Côlons',
      plantes:['Psyllium (Plantago psyllium) — mucilage, transit doux','Mauve (Malva sylvestris) — émolliente, laxative douce','Guimauve (Althaea officinalis) — adoucissante muqueuses','Bourdaine (Rhamnus frangula) — laxative si constipation sévère'],
      hydro:['Hydrothérapie du côlon si indiquée','Lavement à l\'eau tiède','Bouillotte abdominale en cas de spasmes'],
      autres:['Chi Nei Tsang — libère les tensions coliques','Massage abdominal circulaire (sens des aiguilles)','Réflexologie — zone colique','Exercice physique quotidien'],
      alim:['Fibres végétales en abondance (légumes, fruits, céréales complètes)','Eau pure : 1,5 à 2 L/jour minimum','Réduire produits laitiers, viandes, aliments raffinés','Probiotiques naturels (kéfir, lacto-fermentés en phase de revit)']
    },
    intestin_grele: {
      icon:'🟠', label:'Intestin grêle',
      plantes:['Mauve (Malva sylvestris) — adoucissante','Camomille (Matricaria chamomilla) — anti-inflammatoire digestive','Fenouil (Foeniculum vulgare) — antispasmodique, carminatif'],
      hydro:['Bouillotte abdominale','Bain chaud'],
      autres:['Chi Nei Tsang — libération fascias digestifs','Réflexologie — zone intestin grêle','Alimentation dissociée si intolérances'],
      alim:['Supprimer gluten et lactose si intolérance confirmée','Privilégier aliments faciles à digérer (riz, légumes vapeur)','Mastication lente — 30 fois par bouchée']
    },
    pancreas: {
      icon:'🟡', label:'Pancréas',
      plantes:['Gymnema (Gymnema sylvestre) — régulateur glycémie','Cannelle (Cinnamomum verum) — sensibilité insulinique','Bardane (Arctium lappa) — drainant pancréatique'],
      hydro:['Bouillotte côté gauche (zone pancréatique)'],
      autres:['Réflexologie — zone pancréas','Activité physique régulière (clé pour la glycémie)'],
      alim:['Supprimer sucres simples, sucres industriels, sodas','Réduire farines blanches et amidons rapides','Chromium alimentaire (levure, épinards, brocoli)','Repas petits et réguliers — éviter les pics glycémiques']
    },
    reins: {
      icon:'🔵', label:'Reins · Urinaire',
      plantes:['Orthosiphon (Orthosiphon stamineus) — diurétique doux, référence','Busserole (Arctostaphylos uva-ursi) — antiseptique urinaire','Prêle (Equisetum arvense) — drainante, reminéralisante','Ortie (Urtica dioica) — diurétique douce, reminéralisante'],
      hydro:['Eaux peu minéralisées (Évian, Volvic) — 1,5–2 L/jour','Chaleur locale lombaire (bouillotte)','⚠ Bain froid général UNIQUEMENT si vitalité suffisante (EV>6)'],
      autres:['Réflexologie — zone rénale','⚠ Plantes rénales avec prudence — reins fragiles','Dérivation préférable : ouvrir la peau (sudation) pour soulager les reins'],
      alim:['Réduire protéines animales, sel, légumineuses en excès','Éviter eau trop minéralisée (Hépar, Contrex) sauf prescription','Réduire acide urique : moins de purines (abats, anchois, bière)']
    },
    poumons: {
      icon:'🌬', label:'Poumons · ORL',
      plantes:['Thym (Thymus vulgaris) — balsamique, béchique, antiseptique','Eucalyptus (Eucalyptus globulus) — expectorant, fluidifiant','Marrube blanc (Marrubium vulgare) — expectorant','Plantain (Plantago major) — adoucissant muqueuses respiratoires'],
      hydro:['Inhalations vapor d\'eau aromatisée (thym, eucalyptus)','Bain chaud avec huiles essentielles','Sauna (si EV suffisante)'],
      autres:['Exercice physique aérobique — drainage bronchique naturel','Cohérence cardiaque — régule SNA + oxygénation','Bol d\'air Jacquier (si disponible)','Réflexologie — zone pulmonaire','Techniques respiratoires (respiration abdominale, 4-7-8)'],
      alim:['Réduire produits laitiers (mucus)','Réduire glucides raffinés (acidose + mucus)','Privilégier aliments mucolytiques : ail, oignon, radis, raifort','N-acétylcystéine alimentaire : ail, oignon, brocoli']
    },
    peau_emonct: {
      icon:'🟤', label:'Peau — émonctoire cutané',
      plantes:['Sureau (Sambucus nigra) — sudorifique, ouvre la peau','Tilleul (Tilia cordata) — sudorifique doux, relaxant','Bardane (Arctium lappa) — dépurative cutanée','Pensée sauvage (Viola tricolor) — dépurative, anti-acné'],
      hydro:['Bain chaud général (40°C, 20 min) — ouvre les pores','Hammam / sauna — drainage cutané profond','Douches alternées chaud/froid — active la circulation','Thalassothérapie / thermalisme'],
      autres:['Brossage à sec de la peau (brosse végétale)','Frictions au gant de crin — active circulation sous-cutanée','Massages du corps — drainage lymphatique','Héliothérapie (exposition solaire modérée)','Réflexologie'],
      alim:['Réduire graisses saturées, sucres et sel (qui surchargent la peau)','Augmenter apport en zinc (graines de courge, spiruline)','Vitamines A et C (carottes, poivrons, agrumes)','Hydratation suffisante — peau = émonctoire hydrodépendant']
    },
    lymphe: {
      icon:'🟣', label:'Système lymphatique',
      plantes:['Clématite (Clematis vitalba) — drainante lymphatique','Calendula (Calendula officinalis) — lymphatique, anti-inflammatoire','Mélilot (Melilotus officinalis) — tonique veineux et lymphatique','Harpagophytum (Harpagophytum procumbens) — anti-inflammatoire'],
      hydro:['Bains froids des jambes / douches alternées jambes','Bain de siège froid — stimule retour lymphatique'],
      autres:['Drainage lymphatique manuel (Vodder) — technique reine','Réflexologie lymphatique','Exercice physique régulier — pompe lymphatique naturelle','Rebond sur trampoline — drainage lymphatique mécanique','Éviter positions statiques prolongées'],
      alim:['Réduire produits colloïdaux (lait, sucres, farines)','Augmenter fruits et légumes frais','Antioxydants (baies, grenade, raisin noir)']
    },
    coeur: {
      icon:'❤', label:'Cœur · Circulation sanguine',
      plantes:['Aubépine (Crataegus monogyna) — cardiotonique, régulateur','Vigne rouge (Vitis vinifera) — veinotonique, anti-œdème','Ginkgo biloba — vasodilatateur, micro-circulation','Hamamélis (Hamamelis virginiana) — veinotonique'],
      hydro:['Douches alternées jambes (chaud/froid) — tonifie les parois','Bains de pieds alternés','Bain froid général si EV suffisante (tonique)'],
      autres:['Exercice physique régulier — pompe cardiaque naturelle','Réflexologie — zone cœur/circulation','Cohérence cardiaque (5 min, 3×/jour)','Éviter positions assises prolongées'],
      alim:['Réduire sel (HTA), graisses saturées, alcool','Omega-3 (poissons, lin, noix)','Magnésium (légumes verts, oléagineux, cacao cru)','Antioxydants polyphénols (baies, raisin, thé vert)']
    },
    thyroide: {
      icon:'🔵', label:'Glande thyroïde',
      plantes:['⚠ Plantes sur la thyroïde = PRUDENCE — ne pas stimuler sans bilan sanguin','Rhodiola (Rhodiola rosea) — adaptogène, soutien surrénalien lié','Ashwagandha (Withania somnifera) — modulateur thyroïdien doux'],
      hydro:['Douche froide cervicale (nuque) — stimulante douce','Cataplasme d\'argile froide sur gorge (hypo) — drainant'],
      autres:['Réflexologie — zone thyroïde (gros orteil, bande thyroïdienne)','Yoga du cou et asanas cervicaux','Méditation — régule le SNA et l\'axe neuro-endocrinien'],
      alim:['Iode alimentaire (algues, poissons, crustacés) si hypothyroïdie','Éviter goitrigènes crus en excès (choux, soja) si hypo','Sélénium (noix du Brésil, 2/jour) — cofacteur enzymatique thyroïdien','Supprimer le gluten si thyroïdite de Hashimoto (lien auto-immun)']
    },
    surrenales: {
      icon:'🟡', label:'Glandes surrénales',
      plantes:['Rhodiola (Rhodiola rosea) — adaptogène surrénalien majeur','Ashwagandha (Withania somnifera) — anti-cortisol, revitalisant','Ginseng (Panax ginseng) — tonique surrénalien','Réglisse (Glycyrrhiza glabra) — soutient le cortisol naturel'],
      hydro:['Douches alternées matin — activent l\'axe surrénalien','Bain froid rapide au réveil (si vitalité suffisante)'],
      autres:['Magnétologie — recharge énergétique plexus solaire','Gestion du stress (psychologie, méditation, cohérence cardiaque)','Sommeil réparateur — priorité absolue','Réflexologie — zone surrénalienne'],
      alim:['Magnésium (oléagineux, légumes verts, cacao cru)','Vitamine C (kiwi, poivron, cassis)','Sel non raffiné (si surrénales épuisées avec hypo)','Réduire café (épuise l\'axe HPA)','Éviter le jeûne — surrénales épuisées ont besoin de glucose régulier']
    },
    genitaux: {
      icon:'🌸', label:'Génitaux · Hormones',
      plantes:['Gattilier (Vitex agnus-castus) — régulateur progestérone/prolactine','Alchémille (Alchemilla vulgaris) — régulateur cycles','Igname sauvage (Dioscorea villosa) — progestérone-like','Maca (Lepidium meyenii) — adaptogène endocrinien'],
      hydro:['Bain de siège froid (stimulant pelvien)','Cataplasme d\'argile bas-ventre'],
      autres:['Réflexologie — zone génitale/endocrine','Yoga hormonal, yin yoga','Gestion du stress (lien stress/hormones direct)'],
      alim:['Phytoestrogènes doux (lin, légumineuses) si carence oestrogènes','Zinc (graines de courge, huître) — essentiel à la spermatogenèse','Réduire xénoestrogènes (plastiques, pesticides, cosmétiques)','Iode et sélénium si hypothyroïdie associée']
    },
    colonne: {
      icon:'🦴', label:'Colonne · Ostéo-musculaire',
      plantes:['Harpagophytum (Harpagophytum procumbens) — anti-inflammatoire articulaire','Reine des prés (Filipendula ulmaria) — anti-inflammatoire naturelle','Cassis (Ribes nigrum) — cortisone végétale','Prêle (Equisetum arvense) — reminéralisante, silice'],
      hydro:['Bains chauds (anti-spasmes musculaires)','Cataplasmes d\'argile sur articulations douloureuses','Hydrothérapie thermale si arthritisme avancé'],
      autres:['Ostéopathie / fasciathérapie — prioritaire','Yoga, tai-chi, qi gong — entretien articulaire','Réflexologie — zone rachis','Étirements quotidiens'],
      alim:['Réduire acides (café, alcool, sucres, viandes rouges) — arthritisme','Collagène alimentaire (bouillon d\'os, vitamine C)','Silice (prêle, ortie, bambu)','Magnésium (anti-spasmes, anti-crises de goutte)','Réduire purines si goutte (abats, anchois, bière)']
    },
    estomac: {
      icon:'🟠', label:'Estomac · Duodénum',
      plantes:['Camomille (Matricaria chamomilla) — anti-inflammatoire gastrique','Réglisse (Glycyrrhiza glabra) — cicatrisant muqueux gastrique','Fenouil (Foeniculum vulgare) — antispasmodique gastrique','Aloe vera (gel) — régénérant muqueuses'],
      hydro:['Bouillotte épigastrique (chaleur douce)'],
      autres:['Réflexologie — zone estomac/duodénum','Chi Nei Tsang','Mastication lente et prolongée'],
      alim:['Repas calmes, mastication 30 fois par bouchée','Réduire café, alcool, acidifiants (brûlures)','Éviter alimentation trop chaude ou froide','Curcuma-poivre (anti-inflammatoire gastrique)','Aliments doux si gastrite : riz, courgette, carotte, compote']
    }
  };

  // Organes prioritaires = en difficulté + à soutenir
  const organesPrioritaires = [...endiff.map(n=>Object.keys(organNames).find(k=>organNames[k]===n)), ...asoutenir.map(n=>Object.keys(organNames).find(k=>organNames[k]===n))].filter(Boolean);

  // ── 6d. Techniques complémentaires recommandées (3–4 max) ──
  const techSet = new Set();
  // Toujours : bromatologie + kinésiologie
  techSet.add('🥦 Bromatologie — réforme alimentaire personnalisée (base obligatoire de toute cure)');
  techSet.add('🏃 Kinésiologie — activité physique régulière adaptée (marche, natation, yoga) + respiration');

  if (cureType === 'revitalisation' || cureType === 'revit_puis_detox') {
    techSet.add('🔋 Magnétologie — recharge énergétique par passes magnétiques ou aimants thérapeutiques');
    techSet.add('☀ Actinologie — héliothérapie (exposition solaire modérée quotidienne), sauna infrarouge');
  }
  if (vitScores['evPsy'] !== null && vitScores['evPsy'] < 5) {
    techSet.add('🧠 Psychologie — gestion du stress et des émotions : cohérence cardiaque, méditation, sophrologie');
  }
  if (vitScores['evNerv'] !== null && vitScores['evNerv'] < 5) {
    techSet.add('🧠 Psychologie — récupération nerveuse : sommeil, ralentissement, coupure des stimuli');
    techSet.add('💧 Hydrologie — bains tièdes le soir, douches alternées matin, cataplasmes relaxants');
  }
  if (organesPrioritaires.includes('foie') || organesPrioritaires.includes('colons') || organesPrioritaires.includes('intestin_grele')) {
    techSet.add('🌱 Phytologie — plantes hépatiques et intestinales sur 21 jours (artichaut, radis noir, psyllium)');
    techSet.add('👐 Chirologie — Chi Nei Tsang (massage abdominal profond), libération des fascias digestifs');
  }
  if (organesPrioritaires.includes('reins') || organesPrioritaires.includes('lymphe')) {
    techSet.add('🌱 Phytologie — plantes drainantes douces sur 21 jours (orthosiphon, prêle, ortie)');
    techSet.add('💧 Hydrologie — eaux peu minéralisées, douches alternées jambes, drainage lymphatique');
  }
  if (organesPrioritaires.includes('peau_emonct')) {
    techSet.add('💧 Hydrologie — bains chauds, hammam, sauna (ouvre l\'émonctoire cutané)');
    techSet.add('☀ Actinologie — héliothérapie (stimule la circulation cutanée et la sudation)');
  }
  if (organesPrioritaires.includes('poumons')) {
    techSet.add('🌬 Pneumologie — cohérence cardiaque, respiration abdominale, bol d\'air Jacquier');
    techSet.add('🌱 Phytologie — plantes balsamiques et expectorantes (thym, eucalyptus, plantain)');
  }
  if (organesPrioritaires.includes('colonne') || organesPrioritaires.includes('coeur')) {
    techSet.add('👐 Chirologie — ostéopathie, fasciathérapie, massages structurels');
    techSet.add('🦶 Réflexologie — stimulation des zones réflexes correspondantes');
  }
  if (organesPrioritaires.includes('surrenales') || organesPrioritaires.includes('thyroide')) {
    techSet.add('🔋 Magnétologie — rééquilibrage plexus solaire et axe neuro-endocrinien');
    techSet.add('🧠 Psychologie — gestion du stress chronique (adaptogènes, méditation, sophrologie)');
  }
  // Toujours réflexologie
  techSet.add('🦶 Réflexologie — stimulation des zones réflexes des organes prioritaires (pieds/oreilles)');

  const techList = [...techSet].slice(0, 6); // max 6 techniques

  // ── HTML Section 5 ──
  let s5 = '';

  // A. Type de cure
  const cureColors = {
    'revitalisation':'#b8956a', 'revit_puis_detox':'#b8956a',
    'detox_puis_revit':'#4a7050', 'detox_complete':'#3d6b45', 'indetermine':'#8a9e8c'
  };
  const cureIcons = {
    'revitalisation':'⚡ REVITALISATION', 'revit_puis_detox':'⚡→🧹 REVIT puis DÉTOX',
    'detox_puis_revit':'🧹→⚡ DÉTOX puis REVIT', 'detox_complete':'🧹 DÉSINTOXICATION',
    'indetermine':'— Indéterminé'
  };
  const cc = cureColors[cureType] || '#8a9e8c';

  s5 += `<div style="margin-bottom:1rem;padding:1rem 1.2rem;background:rgba(${cureType==='revitalisation'||cureType==='revit_puis_detox'?'184,149,106':'74,112,80'},0.08);border-radius:12px;border-left:4px solid ${cc};">
    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:${cc};margin-bottom:0.35rem;font-weight:600;">Cure recommandée</div>
    <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:600;color:${cc};margin-bottom:0.4rem;">${cureIcons[cureType]}</div>
    <div style="font-size:0.83rem;color:var(--text-secondary);line-height:1.55;">${cureSeq}</div>
    ${cureDuree ? `<div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.35rem;">⏱ ${cureDuree} · 🌿 ${cureSaison}</div>` : ''}
    ${cureAlert ? `<div style="margin-top:0.6rem;padding:0.5rem 0.8rem;background:rgba(194,106,62,0.1);border-radius:8px;font-size:0.8rem;color:var(--alert-col);line-height:1.5;">${cureAlert}</div>` : ''}
  </div>`;

  // B. Bromatologie
  if (alimPriv.length > 0 || alimEviter.length > 0) {
    s5 += `<div style="margin-bottom:1rem;">
      <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:var(--sage-dark);margin-bottom:0.5rem;font-weight:600;">🥦 Bromatologie — alimentation adaptée</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <div style="background:rgba(74,112,80,0.06);border-radius:10px;padding:0.9rem;border:1px solid rgba(74,112,80,0.15);">
          <div style="font-size:0.75rem;font-weight:600;color:#4a7050;margin-bottom:0.5rem;">✅ À privilégier</div>
          ${alimPriv.map(a=>`<div style="font-size:0.79rem;color:var(--text-secondary);line-height:1.5;padding:0.15rem 0 0.15rem 0.5rem;border-left:2px solid rgba(74,112,80,0.3);margin-bottom:0.25rem;">${a}</div>`).join('')}
        </div>
        <div style="background:rgba(194,106,62,0.06);border-radius:10px;padding:0.9rem;border:1px solid rgba(194,106,62,0.15);">
          <div style="font-size:0.75rem;font-weight:600;color:var(--alert-col);margin-bottom:0.5rem;">🚫 À réduire / éviter</div>
          ${alimEviter.map(a=>`<div style="font-size:0.79rem;color:var(--text-secondary);line-height:1.5;padding:0.15rem 0 0.15rem 0.5rem;border-left:2px solid rgba(194,106,62,0.3);margin-bottom:0.25rem;">${a}</div>`).join('')}
        </div>
      </div>
    </div>`;
  }

  // C. Drainage émonctoriel détaillé
  if (organesPrioritaires.length > 0) {
    s5 += `<div style="margin-bottom:1rem;">
      <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:var(--sage-dark);margin-bottom:0.6rem;font-weight:600;">🌱 Drainage émonctoriel prioritaire</div>`;

    organesPrioritaires.slice(0, 5).forEach(orgKey => {
      const db = DRAINAGE_DB[orgKey];
      if (!db) return;
      const isDiff = endiff.includes(organNames[orgKey]);
      const borderCol = isDiff ? 'var(--alert-col)' : 'var(--gold)';
      const statusBadge = isDiff
        ? `<span style="font-size:0.68rem;background:rgba(194,106,62,0.15);color:var(--alert-col);padding:0.1em 0.5em;border-radius:10px;margin-left:0.4rem;">❌ En difficulté</span>`
        : `<span style="font-size:0.68rem;background:rgba(184,149,106,0.15);color:var(--bark);padding:0.1em 0.5em;border-radius:10px;margin-left:0.4rem;">⚠ À soutenir</span>`;

      s5 += `<div style="margin-bottom:0.8rem;padding:0.9rem 1.1rem;background:rgba(255,255,255,0.5);border-radius:11px;border:1px solid var(--border-light);border-left:3px solid ${borderCol};">
        <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);margin-bottom:0.6rem;">${db.icon} ${db.label}${statusBadge}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;font-size:0.78rem;">
          <div>
            <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage-dark);margin-bottom:0.3rem;font-weight:600;">🌿 Phytologie (21 jours)</div>
            ${db.plantes.map(p=>`<div style="color:var(--text-secondary);line-height:1.45;margin-bottom:0.2rem;">• ${p}</div>`).join('')}
          </div>
          <div>
            <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage-dark);margin-bottom:0.3rem;font-weight:600;">💧 Hydrologie & stimulations</div>
            ${db.hydro.map(h=>`<div style="color:var(--text-secondary);line-height:1.45;margin-bottom:0.2rem;">• ${h}</div>`).join('')}
            ${db.autres.slice(0,3).map(a=>`<div style="color:var(--text-secondary);line-height:1.45;margin-bottom:0.2rem;">• ${a}</div>`).join('')}
          </div>
        </div>
        ${db.alim && db.alim.length > 0 ? `<div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--border-light);">
          <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--sage-dark);margin-bottom:0.3rem;font-weight:600;">🥦 Alimentation spécifique</div>
          <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">${db.alim.map(a=>`<span style="font-size:0.75rem;background:var(--leaf-light);color:var(--moss);padding:0.15em 0.6em;border-radius:15px;">${a}</span>`).join('')}</div>
        </div>` : ''}
      </div>`;
    });
    s5 += `</div>`;
  }

  // D. Techniques complémentaires
  s5 += `<div>
    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.09em;color:var(--sage-dark);margin-bottom:0.5rem;font-weight:600;">🛠 Techniques complémentaires (3–4 max à retenir)</div>
    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">Choisir selon la volonté et le contexte de vie du patient — ne pas tout prescrire simultanément.</div>
    ${techList.map((t,i)=>`<div style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.45rem 0;border-bottom:1px solid var(--border-light);">
      <span style="font-size:0.7rem;font-weight:700;color:var(--sage-dark);min-width:1.2rem;">${i+1}.</span>
      <span style="font-size:0.8rem;color:var(--text-secondary);line-height:1.5;">${t}</span>
    </div>`).join('')}
  </div>`;

  // ── Assemblage ──
  let html = `<div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:1rem;text-align:right;">Généré le ${dateStr} · ${nom}</div>`;
  html += section('◆', '1 · Tempérament naturopathique & hippocratique', s1);
  html += section('◎', '2 · Bilan des surcharges (Khune)', s2);
  html += section('⚡', '3 · Bilan de vitalité', s3);
  html += section('⌘', '4 · Bilan émonctoriel', s4);
  html += section('✦', '5 · Recommandations de cure & techniques', s5);

  bilanContent.innerHTML = html;
  bilanResult.style.display = 'block';
  bilanResult.scrollIntoView({ behavior:'smooth', block:'start' });
}

// ====== IMPRESSION SYNTHÉTIQUE ======
function printBilan() {
  const bilanResult = document.getElementById('bilanResult');
  const content     = document.getElementById('bilanContent');
  const nom   = (document.getElementById('prenom')?.value?.trim()||'') + ' ' + (document.getElementById('nom')?.value?.trim()||'');
  const age   = document.getElementById('age')?.value?.trim() || '';
  const now   = new Date().toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' });
  const dateB = document.getElementById('dateB')?.value || '';
  if (bilanResult.style.display === 'none' || !content.innerHTML.trim()) {
    lancerBilan(); setTimeout(printBilan, 350); return;
  }
  function extractSections() {
    let out = '';
    content.querySelectorAll('div[style*="border-radius:14px"]').forEach(sec => {
      const titleEl = sec.querySelector('div[style*="Cormorant"]');
      if (titleEl) out += `<h2>${titleEl.textContent.trim()}</h2>`;
      sec.querySelectorAll('span[style*="border-radius:20px"]').forEach(p => {
        out += `<span class="pill">${p.textContent.trim()}</span> `;
      });
      out += '<br>';
      ['0.82rem','0.83rem','0.85rem'].forEach(fs => {
        sec.querySelectorAll(`div[style*="${fs}"]`).forEach(d => {
          const t = d.textContent.trim();
          if (t && t.length > 15 && !d.querySelector('span[style*="border-radius"]')) {
            out += `<p>${t}</p>`;
          }
        });
      });
      sec.querySelectorAll('div[style*="1.9rem"], div[style*="3rem"]').forEach(sb => {
        const parent = sb.closest('div[style*="border-radius:10px"]');
        if (!parent) return;
        const lbl = parent.querySelector('div[style*="0.75rem"]');
        const val = sb.textContent.replace('/10','').trim();
        const num = parseFloat(val);
        const cls = num>=7?'good':num>=4.5?'mid':'bad';
        if (lbl||val) out += `<div class="score-line"><span>${lbl?lbl.textContent.trim():''}</span><span class="score-val ${cls}">${val}/10</span></div>`;
      });
      sec.querySelectorAll('div[style*="border-left:3px"]').forEach(og => {
        const tl = og.querySelector('div[style*="0.72rem"]');
        if (tl) out += `<p class="group-title">${tl.textContent.trim()}</p>`;
        og.querySelectorAll('span[style*="border-radius"]').forEach(s => { out += `<span class="pill">${s.textContent.trim()}</span> `; });
        out += '<br>';
      });
      sec.querySelectorAll('div[style*="grid-template-columns:1fr 1fr"]').forEach(grid => {
        const cols = grid.children;
        if (cols.length < 2) return;
        const l = cols[0].querySelectorAll('div[style*="border-left:2px"]');
        const r2 = cols[1].querySelectorAll('div[style*="border-left:2px"]');
        if (l.length === 0 && r2.length === 0) return;
        out += '<table class="alim"><tr><td><strong>✅ À privilégier</strong><ul>';
        l.forEach(i => out += `<li>${i.textContent.trim()}</li>`);
        out += '</ul></td><td><strong>🚫 À éviter</strong><ul>';
        r2.forEach(i => out += `<li>${i.textContent.trim()}</li>`);
        out += '</ul></td></tr></table>';
      });
      let hasTech = false;
      sec.querySelectorAll('div[style*="border-bottom:1px solid"]').forEach(t => {
        const spans = t.querySelectorAll('span');
        if (spans.length >= 2) {
          if (!hasTech) { out += '<ol>'; hasTech = true; }
          out += `<li>${spans[1].textContent.trim()}</li>`;
        }
      });
      if (hasTech) out += '</ol>';
      sec.querySelectorAll('div[style*="border-left:4px"]').forEach(cb => {
        const tl = cb.querySelector('div[style*="1.2rem"]');
        const dc = cb.querySelector('div[style*="0.83rem"]');
        const du = cb.querySelector('div[style*="0.78rem"]');
        if (tl) out += `<p class="cure-title">${tl.textContent.trim()}</p>`;
        if (dc) out += `<p>${dc.textContent.trim()}</p>`;
        if (du) out += `<p class="muted">${du.textContent.trim()}</p>`;
      });
    });
    return out;
  }
  const w = window.open('','_blank','width=820,height=1000');
  w.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Bilan — ${nom.trim()||'Patient'}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,serif;font-size:10.5pt;color:#1a1a1a;line-height:1.55;padding:1.8cm 2cm;background:#fff}
h1{font-size:17pt;margin-bottom:4px}h2{font-size:12pt;border-bottom:1px solid #aaa;margin:14px 0 5px;padding-bottom:2px}
.meta{font-size:9pt;color:#555;margin-bottom:14px}
.pill{display:inline-block;border:1px solid #888;border-radius:10px;padding:1px 9px;font-size:9pt;margin:2px 2px 2px 0;background:#f5f5f5}
p{margin:3px 0;font-size:9.5pt}.group-title{font-weight:bold;margin:6px 0 2px}
.score-line{display:flex;justify-content:space-between;margin:3px 0}.score-val{font-weight:bold}
.good{color:#2d5a34}.mid{color:#8a6a2a}.bad{color:#9e2a1a}
.cure-title{font-weight:bold;font-size:10.5pt;margin:4px 0 2px}.muted{color:#666;font-size:9pt}
table.alim{width:100%;border-collapse:collapse;font-size:9pt;margin:5px 0}
table.alim td{vertical-align:top;padding:4px 6px;border:1px solid #ddd;width:50%}
ul,ol{margin:3px 0 3px 16px}li{margin:1px 0}
.footer{margin-top:18px;font-size:8.5pt;color:#888;border-top:1px solid #ccc;padding-top:5px}
@page{margin:0}
</style></head><body>
<h1>Bilan de Vitalité Naturopathique</h1>
<div class="meta">Patient : <strong>${nom.trim()||'—'}</strong>${age?' · '+age+' ans':''} · Bilan du ${dateB?new Date(dateB).toLocaleDateString('fr-FR'):now} · Édité le ${now}</div>
${extractSections()}
<div class="footer">Document généré automatiquement — à compléter par le praticien · Confidentiel</div>
</body></html>`);
  w.document.close();
  w.onload = () => { w.focus(); w.print(); };
}

// ====== INIT ======
// Restore persisted state first, then update UI
restoreState();
updateProgress();

// Initialize all sliders display (restoreState already handles persisted ranges,
// but this ensures default value display for any non-persisted sliders)
document.querySelectorAll('input[type=range]').forEach(i => updateSlider(i, i.id + 'Val'));

// Keep first section open
document.getElementById('body-sec1').style.display = 'block';
</script>
</section>
