<?php
// includes/header.php - Global Navigation and Top Bar

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

$cur_user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'CondoCare';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> - CondoCare</title>
  <link rel="stylesheet" href="comm_style.css?v=<?= @filemtime(__DIR__ . '/comm_style.css') ?: time() ?>">

  <script>
    /* Apply saved theme before the page paints (avoids a light flash) */
    (function () {
      try {
        if (localStorage.getItem('condocare-theme') === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {}
    })();
  </script>
  <style>
    /* ---------- Light / dark mode toggle ---------- */
    .theme-toggle{
      position:absolute; right:28px; top:72px; z-index:50;
      display:inline-flex; padding:3px; gap:2px;
      background:#ffffff; border:1.5px solid var(--gray-border); border-radius:999px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
    }
    .theme-toggle button{
      width:30px; height:26px; border:0; border-radius:999px; background:transparent;
      color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center;
      transition:background .15s, color .15s; padding:0;
    }
    .theme-toggle button svg{width:16px; height:16px; display:block}
    .theme-toggle button:hover{color:var(--primary)}
    .theme-toggle button.on{background:var(--primary); color:#ffffff}
    @media (max-width:1280px){
      .theme-toggle{position:static; display:flex; width:max-content; margin:8px 14px 0 auto}
    }

    /* ---------- Dark theme ---------- */
    html[data-theme="dark"]{
      color-scheme:dark;
      --gray-bg:#0f172a; --gray-card:#1e293b; --gray-border:#334155; --gray-border-dark:#64748b;
      --gray-text:#94a3b8; --gray-heading:#f1f5f9; --dark:#e2e8f0;
      --primary-tint:#12301f; --primary-mint:#14532d;
    }
    html[data-theme="dark"] body{background:var(--gray-bg); color:var(--dark)}
    html[data-theme="dark"] .topbar{background:#111c30; box-shadow:0 1px 4px rgba(0,0,0,.4)}
    html[data-theme="dark"] .nav a,
    html[data-theme="dark"] .btn,
    html[data-theme="dark"] .input,
    html[data-theme="dark"] .chip,
    html[data-theme="dark"] .slot,
    html[data-theme="dark"] .toggle>*,
    html[data-theme="dark"] .terms-tab{background:#1e293b; color:#e2e8f0}
    html[data-theme="dark"] .user{color:#cbd5e1}
    html[data-theme="dark"] .user small{color:#94a3b8!important}
    html[data-theme="dark"] .nav a.active,
    html[data-theme="dark"] .btn.btn-dark,
    html[data-theme="dark"] .chip.on,
    html[data-theme="dark"] .slot.sel,
    html[data-theme="dark"] .toggle>.on{background:var(--primary); color:#ffffff}
    html[data-theme="dark"] .nav a:hover,
    html[data-theme="dark"] .btn:hover,
    html[data-theme="dark"] .chip:hover,
    html[data-theme="dark"] .slot:hover:not(:disabled),
    html[data-theme="dark"] .terms-tab:hover{background:#12301f; color:#86efac; border-color:#2d6a4f}
    html[data-theme="dark"] .btn.btn-dark:hover{background:var(--primary-hover); color:#ffffff}
    html[data-theme="dark"] .avatar{color:#bbf7d0}
    html[data-theme="dark"] .terms-tab{color:#6ee7b7!important}
    html[data-theme="dark"] .terms-check a{color:#93c5fd!important}
    html[data-theme="dark"] .terms-check a:hover{background:#1e3a5f; color:#bfdbfe!important}
    html[data-theme="dark"] .stat b{color:#86efac}
    html[data-theme="dark"] .card,
    html[data-theme="dark"] .box,
    html[data-theme="dark"] .stat,
    html[data-theme="dark"] .chart,
    html[data-theme="dark"] .panel,
    html[data-theme="dark"] .summary,
    html[data-theme="dark"] .table-wrap,
    html[data-theme="dark"] .modal-content,
    html[data-theme="dark"] .terms-toc,
    html[data-theme="dark"] .login .right{background:#1e293b; box-shadow:0 2px 6px rgba(0,0,0,.35)}
    html[data-theme="dark"] .banner{background:#172033; color:#cbd5e1}
    html[data-theme="dark"] .banner-success{background:#14532d; color:#bbf7d0; border-color:#166534}
    html[data-theme="dark"] .banner-error{background:#450a0a; color:#fca5a5; border-color:#7f1d1d}
    html[data-theme="dark"] .banner-info{background:#082f49; color:#7dd3fc; border-color:#0c4a6e}
    html[data-theme="dark"] th{background:#172033; color:#cbd5e1}
    html[data-theme="dark"] td{border-bottom-color:#334155}
    html[data-theme="dark"] tbody tr:hover{background:#1a2740}
    html[data-theme="dark"] .status{border-color:rgba(255,255,255,.12)}
    html[data-theme="dark"] .status.reported,
    html[data-theme="dark"] .status.pending{background:#422006; color:#fcd34d}
    html[data-theme="dark"] .status.review,
    html[data-theme="dark"] .status.progress{background:#0c4a6e; color:#7dd3fc}
    html[data-theme="dark"] .status.resolved,
    html[data-theme="dark"] .status.approved,
    html[data-theme="dark"] .status.completed{background:#14532d; color:#86efac}
    html[data-theme="dark"] .status.available{background:#14532d; color:#86efac}
    html[data-theme="dark"] .status.maintenance{background:#422006; color:#fcd34d}
    html[data-theme="dark"] .fac-desc{color:#94a3b8}
    html[data-theme="dark"] .status.cancelled,
    html[data-theme="dark"] .status.rejected{background:#7f1d1d; color:#fca5a5}
    html[data-theme="dark"] .status.banned{background:#7f1d1d; color:#fca5a5}
    html[data-theme="dark"] .status.lifted{background:#14532d; color:#86efac}
    html[data-theme="dark"] .status.expired{background:#334155; color:#cbd5e1}
    html[data-theme="dark"] .bl-alert{background:#450a0a; color:#fca5a5; border-color:#7f1d1d}
    html[data-theme="dark"] .field label{color:#cbd5e1}
    html[data-theme="dark"] .msg.error{color:#fca5a5}
    html[data-theme="dark"] .msg.success{color:#86efac}
    html[data-theme="dark"] .counter,
    html[data-theme="dark"] .modal-close{color:#94a3b8}
    html[data-theme="dark"] .slot.booked{background:#0f172a; color:#64748b; border-color:#1e293b}
    html[data-theme="dark"] select.input option:disabled,
    html[data-theme="dark"] select.input option.opt-unavailable{background:#172033; color:#64748b}
    html[data-theme="dark"] .terms-toc a{color:#6ee7b7}
    html[data-theme="dark"] .terms-toc a:hover{background:#12301f; color:#bbf7d0}
    html[data-theme="dark"] .theme-toggle{background:#1e293b; border-color:#334155}
    html[data-theme="dark"] .theme-toggle button{color:#94a3b8}
    html[data-theme="dark"] .theme-toggle button:hover{color:#e2e8f0}
    html[data-theme="dark"] .theme-toggle button.on{background:#2d6a4f; color:#ffffff}
  </style>
</head>
<body>
<header class="topbar">
  <a href="<?= ($cur_user && $cur_user['role'] === 'admin') ? 'admin-dashboard.php' : 'resident-home.php' ?>" class="logo">
    <span>🏢</span> CONDOCARE
  </a>
  
  <nav class="nav">
    <?php if ($cur_user && $cur_user['role'] === 'admin'): ?>
      <a href="admin-dashboard.php" class="<?= in_array($currentPage, ['admin-dashboard.php']) ? 'active' : '' ?>">Dashboard</a>
      <a href="admin-maintenance.php" class="<?= in_array($currentPage, ['admin-maintenance.php']) ? 'active' : '' ?>">Maintenance</a>
      <a href="admin-bookings.php" class="<?= in_array($currentPage, ['admin-bookings.php']) ? 'active' : '' ?>">Bookings</a>
      <a href="admin-residents.php" class="<?= in_array($currentPage, ['admin-residents.php', 'admin-residents-add.php']) ? 'active' : '' ?>">Residents</a>
      <a href="admin-technicians.php" class="<?= in_array($currentPage, ['admin-technicians.php', 'admin-technicians-add.php']) ? 'active' : '' ?>">Technicians</a>
      <a href="admin-facilities.php" class="<?= in_array($currentPage, ['admin-facilities.php', 'admin-facilities-add.php']) ? 'active' : '' ?>">Facilities</a>
      <a href="admin-admins.php" class="<?= in_array($currentPage, ['admin-admins.php', 'admin-admins-add.php']) ? 'active' : '' ?>">Admins</a>
      <a href="admin-blacklist.php" class="<?= in_array($currentPage, ['admin-blacklist.php', 'admin-blacklist-add.php']) ? 'active' : '' ?>">Blacklist</a>
    <?php elseif ($cur_user && $cur_user['role'] === 'resident'): ?>
      <a href="resident-home.php" class="<?= in_array($currentPage, ['resident-home.php']) ? 'active' : '' ?>">Home</a>
      <a href="report-defect.php" class="<?= in_array($currentPage, ['report-defect.php']) ? 'active' : '' ?>">Report Defect</a>
      <a href="book-facility.php" class="<?= in_array($currentPage, ['book-facility.php']) ? 'active' : '' ?>">Book Facility</a>
      <a href="my-requests-maintenance.php" class="<?= in_array($currentPage, ['my-requests-maintenance.php', 'my-requests-bookings.php', 'edit-defect.php']) ? 'active' : '' ?>">My Requests</a>
    <?php endif; ?>
  </nav>

  <?php if ($cur_user): ?>
    <div class="user">
      <?php if ($cur_user['role'] === 'resident'): ?>
        <a href="terms.php" class="terms-tab <?= $currentPage === 'terms.php' ? 'active' : '' ?>" style="color:var(--primary-light, #2d6a4f);font-weight:700;font-size:13px;text-decoration:underline;text-underline-offset:3px;margin-right:10px;white-space:nowrap;background:none;border:none;box-shadow:none;padding:0;">Terms &amp; Conditions</a>
      <?php endif; ?>
      <span class="avatar"><?= strtoupper(substr($cur_user['name'], 0, 1)) ?></span>
      <span>
        <strong><?= e($cur_user['name']) ?></strong>
        <small style="color:#64748b;">(<?= e($cur_user['role'] === 'resident' ? $cur_user['unit'] : $cur_user['staff_id']) ?>)</small>
      </span>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  <?php else: ?>
    <div class="user">
      <a class="btn btn-dark" href="login.php">Login</a>
    </div>
  <?php endif; ?>
</header>

<div class="theme-toggle" role="group" aria-label="Colour theme">
  <button type="button" id="themeLight" aria-label="Light mode" title="Light mode">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
  </button>
  <button type="button" id="themeDark" aria-label="Dark mode" title="Dark mode">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
  </button>
</div>
<script>
  (function () {
    var root = document.documentElement;
    var lightBtn = document.getElementById('themeLight');
    var darkBtn  = document.getElementById('themeDark');
    function apply(theme) {
      if (theme === 'dark') { root.setAttribute('data-theme', 'dark'); }
      else { root.removeAttribute('data-theme'); }
      lightBtn.classList.toggle('on', theme !== 'dark');
      darkBtn.classList.toggle('on', theme === 'dark');
      lightBtn.setAttribute('aria-pressed', theme !== 'dark');
      darkBtn.setAttribute('aria-pressed', theme === 'dark');
      try { localStorage.setItem('condocare-theme', theme); } catch (e) {}
    }
    lightBtn.addEventListener('click', function () { apply('light'); });
    darkBtn.addEventListener('click', function () { apply('dark'); });
    apply(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
  })();
</script>

<main class="wrap">
  <?= renderFlashes() ?>
