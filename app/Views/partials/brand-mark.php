<?php /** Logo ScoreLive : tuile dégradée, pulsation « direct » et point lumineux. */ ?>
<svg class="brand-mark<?= isset($class) ? ' ' . e($class) : '' ?>" viewBox="0 0 64 64" width="<?= (int) ($size ?? 32) ?>" height="<?= (int) ($size ?? 32) ?>" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="sl-mark-gradient" x1="6" y1="4" x2="58" y2="60" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#3DF5A8"/>
      <stop offset="1" stop-color="#12A6EA"/>
    </linearGradient>
  </defs>
  <rect width="64" height="64" rx="18" fill="url(#sl-mark-gradient)"/>
  <path d="M9 35.5h10.5l5.2-12.5 8.6 21.5 6.2-15 3.4 6H55" fill="none" stroke="#04131F" stroke-width="5.6" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="50" cy="16.5" r="5.2" fill="#04131F"/>
</svg>
