<?php
// login.php - User authentication page

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $u = currentUser();
    header('Location: ' . ($u['role'] === 'admin' ? 'admin-dashboard.php' : 'resident-home.php'));
    exit;
}

$error = '';
$selected_role = $_POST['role'] ?? ($_GET['role'] ?? 'resident');
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'resident');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            // Find user by email and matching role
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && ($user['role'] === $role)) {
                // Verify password (supports bcrypt hash and fallback)
                $valid = password_verify($password, $user['password']) || ($password === $user['password']);

                if ($valid) {
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name']  = $user['full_name'];
                    $_SESSION['user_role']  = $user['role'];

                    if ($user['role'] === 'resident') {
                        $rStmt = $pdo->prepare("SELECT * FROM residents WHERE user_id = :uid LIMIT 1");
                        $rStmt->execute([':uid' => $user['id']]);
                        $resident = $rStmt->fetch();
                        $_SESSION['resident_id']    = $resident ? $resident['id'] : null;
                        $_SESSION['resident_unit']  = $resident ? $resident['unit_no'] : '';
                        $_SESSION['resident_tower'] = $resident ? $resident['tower'] : '';

                        setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                        header('Location: resident-home.php');
                        exit;
                    } else {
                        $aStmt = $pdo->prepare("SELECT * FROM admins WHERE user_id = :uid LIMIT 1");
                        $aStmt->execute([':uid' => $user['id']]);
                        $admin = $aStmt->fetch();
                        $_SESSION['admin_id']       = $admin ? $admin['id'] : null;
                        $_SESSION['admin_staff_id'] = $admin ? $admin['staff_id'] : '';

                        setFlash('success', 'Welcome to Management Dashboard, ' . $user['full_name'] . '!');
                        header('Location: admin-dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or selected role does not match this account.';
            }
        } catch (PDOException $e) {
            $error = 'System error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - CondoCare</title>
  <link rel="stylesheet" href="comm_style.css">
</head>
<body class="login">
  <div class="left">
    <div class="left-content">
      
      <!-- Architectural Condominium SVG Illustration -->
      <svg class="condo-svg-wrap" viewBox="0 0 420 270" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="towerAGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#1b4332"/>
            <stop offset="100%" stop-color="#0b2417"/>
          </linearGradient>
          <linearGradient id="towerBGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#245840"/>
            <stop offset="100%" stop-color="#123524"/>
          </linearGradient>
          <linearGradient id="glassGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#dcfce7" stop-opacity="0.85"/>
            <stop offset="100%" stop-color="#86efac" stop-opacity="0.35"/>
          </linearGradient>
        </defs>

        <!-- Ambient glowing moon & evening stars -->
        <circle cx="345" cy="45" r="26" fill="#40916c" fill-opacity="0.25"/>
        <circle cx="345" cy="45" r="14" fill="#ecfdf5" fill-opacity="0.9"/>
        <circle cx="45" cy="35" r="1.5" fill="#a7f3d0" opacity="0.6"/>
        <circle cx="110" cy="20" r="2" fill="#a7f3d0" opacity="0.8"/>
        <circle cx="175" cy="30" r="1.5" fill="#a7f3d0" opacity="0.5"/>
        <circle cx="280" cy="22" r="2" fill="#a7f3d0" opacity="0.75"/>

        <!-- Background Tower B (Tower B High-Rise) -->
        <rect x="225" y="65" width="115" height="185" rx="5" fill="url(#towerBGrad)" stroke="#40916c" stroke-width="1.5"/>
        <!-- Tower B Roof Terrace & Antenna -->
        <rect x="245" y="52" width="75" height="13" rx="2" fill="#2d6a4f" stroke="#52b788" stroke-width="1"/>
        <line x1="282" y1="28" x2="282" y2="52" stroke="#52b788" stroke-width="2"/>
        <circle cx="282" cy="28" r="3" fill="#86efac"/>

        <!-- Tower B Windows (Lit and dim apartments) -->
        <rect x="240" y="75" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.9"/>
        <rect x="264" y="75" width="16" height="10" rx="1.5" fill="#1b4332"/>
        <rect x="288" y="75" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.8"/>
        <rect x="312" y="75" width="16" height="10" rx="1.5" fill="#dcfce7" opacity="0.7"/>

        <rect x="240" y="95" width="16" height="10" rx="1.5" fill="#dcfce7" opacity="0.7"/>
        <rect x="264" y="95" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.85"/>
        <rect x="288" y="95" width="16" height="10" rx="1.5" fill="#1b4332"/>
        <rect x="312" y="95" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.9"/>

        <rect x="240" y="115" width="16" height="10" rx="1.5" fill="#1b4332"/>
        <rect x="264" y="115" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.8"/>
        <rect x="288" y="115" width="16" height="10" rx="1.5" fill="#dcfce7" opacity="0.75"/>
        <rect x="312" y="115" width="16" height="10" rx="1.5" fill="#1b4332"/>

        <rect x="240" y="135" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.9"/>
        <rect x="264" y="135" width="16" height="10" rx="1.5" fill="#dcfce7" opacity="0.7"/>
        <rect x="288" y="135" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.85"/>
        <rect x="312" y="135" width="16" height="10" rx="1.5" fill="#fef3c7" opacity="0.8"/>

        <!-- Foreground Tower A (Main Luxury Residence) -->
        <rect x="80" y="45" width="135" height="205" rx="6" fill="url(#towerAGrad)" stroke="#52b788" stroke-width="2"/>
        
        <!-- Tower A Sky Penthouse & Rooftop Garden -->
        <rect x="95" y="28" width="105" height="17" rx="3" fill="#2d6a4f" stroke="#86efac" stroke-width="1.5"/>
        <!-- Rooftop Trees -->
        <circle cx="112" cy="22" r="7" fill="#40916c"/>
        <circle cx="122" cy="23" r="5" fill="#52b788"/>
        <circle cx="175" cy="22" r="7" fill="#40916c"/>
        <circle cx="185" cy="23" r="5" fill="#52b788"/>

        <!-- Modern Balconies with glowing glass -->
        <!-- Floor 6 -->
        <rect x="92" y="58" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <rect x="150" y="58" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <line x1="92" y1="68" x2="142" y2="68" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>
        <line x1="150" y1="68" x2="200" y2="68" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>

        <!-- Floor 5 -->
        <rect x="92" y="88" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <rect x="150" y="88" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <line x1="92" y1="98" x2="142" y2="98" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>
        <line x1="150" y1="98" x2="200" y2="98" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>

        <!-- Floor 4 -->
        <rect x="92" y="118" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <rect x="150" y="118" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <line x1="92" y1="128" x2="142" y2="128" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>
        <line x1="150" y1="128" x2="200" y2="128" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>

        <!-- Floor 3 -->
        <rect x="92" y="148" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <rect x="150" y="148" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <line x1="92" y1="158" x2="142" y2="158" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>
        <line x1="150" y1="158" x2="200" y2="158" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>

        <!-- Floor 2 -->
        <rect x="92" y="178" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <rect x="150" y="178" width="50" height="20" rx="2" fill="url(#glassGrad)"/>
        <line x1="92" y1="188" x2="142" y2="188" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>
        <line x1="150" y1="188" x2="200" y2="188" stroke="#ffffff" stroke-width="1" stroke-opacity="0.8"/>

        <!-- Grand Entrance Lobby & Canopy -->
        <rect x="120" y="215" width="55" height="35" rx="2" fill="#fef3c7" opacity="0.95"/>
        <rect x="135" y="224" width="25" height="26" fill="#1b4332"/>
        <polygon points="105,215 190,215 180,206 115,206" fill="#2d6a4f" stroke="#86efac" stroke-width="1"/>

        <!-- Landscaping, Lush Trees and Ground -->
        <rect x="25" y="248" width="370" height="5" rx="2.5" fill="#2d6a4f"/>
        
        <!-- Left Garden Trees -->
        <ellipse cx="50" cy="242" rx="18" ry="14" fill="#40916c"/>
        <ellipse cx="62" cy="240" rx="14" ry="12" fill="#52b788"/>
        <ellipse cx="42" cy="245" rx="12" ry="8" fill="#1b4332"/>

        <!-- Right Garden Trees -->
        <ellipse cx="360" cy="242" rx="20" ry="14" fill="#40916c"/>
        <ellipse cx="375" cy="244" rx="15" ry="11" fill="#52b788"/>
        <ellipse cx="348" cy="245" rx="12" ry="8" fill="#1b4332"/>
      </svg>

      <!-- System Branding & Text -->
      <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(82,183,136,0.18);border:1px solid rgba(82,183,136,0.4);border-radius:20px;padding:4px 14px;margin-bottom:10px;">
        <span style="font-size:12px;font-weight:700;letter-spacing:1px;color:#a7f3d0;">RESIDENTIAL PORTAL</span>
      </div>
      
      <b style="font-size:26px;display:block;margin-bottom:6px;letter-spacing:1px;color:#ffffff;">CondoCare</b>
      <p style="font-size:13px;color:#cbd5e1;line-height:1.5;margin:0 auto;max-width:320px;">
        Condominium Maintenance &amp; Facility Booking Management System
      </p>

      <!-- Key Value Badges -->
      <div class="login-features">
        <div class="feature-badge">
          <span class="feature-badge-icon">🏸</span>
          <span>Instant Facility Booking with Live Time Slots</span>
        </div>
        <div class="feature-badge">
          <span class="feature-badge-icon">🔧</span>
          <span>Quick Defect Reporting &amp; Photo Attachment</span>
        </div>
        <div class="feature-badge">
          <span class="feature-badge-icon">📊</span>
          <span>Integrated Management &amp; Technician Tracking</span>
        </div>
      </div>

    </div>
  </div>

  <div class="right">
    <form id="loginForm" method="POST" action="login.php" novalidate>
      <div style="margin-bottom:20px;">
        <h1 style="margin:0 0 4px;font-size:24px;color:#111827;">Sign In</h1>
        <p style="margin:0;font-size:13px;color:#64748b;">Select your portal role to access your account</p>
      </div>
      
      <?= renderFlashes() ?>

      <div class="toggle" style="margin-bottom:18px;">
        <button type="button" class="<?= $selected_role === 'resident' ? 'on' : '' ?>" data-role="resident">Resident</button>
        <button type="button" class="<?= $selected_role === 'admin' ? 'on' : '' ?>" data-role="admin">Management</button>
      </div>

      <input type="hidden" name="role" id="roleInput" value="<?= e($selected_role) ?>">

      <div class="field">
        <label for="email">Email Address</label>
        <input class="input" id="email" name="email" type="email" placeholder="example@email.com" value="<?= e($email) ?>" required>
      </div>

      <div class="field">
        <label for="pw">Password</label>
        <input class="input" id="pw" name="password" type="password" placeholder="••••••••" required>
      </div>

      <button class="btn btn-dark btn-block" type="submit" style="margin-top:10px;font-size:14px;padding:12px;">Login</button>

      <?php if (!empty($error)): ?>
        <p class="msg error" id="msg"><?= e($error) ?></p>
      <?php else: ?>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px;margin-top:16px;font-size:12px;line-height:1.6;color:#475569;">
          <strong style="color:#111827;display:block;margin-bottom:4px;">Demo Login Credentials:</strong>
          • <strong>Resident:</strong> <a href="#" onclick="fillDemo('resident@email.com','12345678','resident');return false;" style="color:#1b4332;font-weight:700;text-decoration:underline;">resident@email.com / 12345678</a><br>
          • <strong>Management:</strong> <a href="#" onclick="fillDemo('admin@email.com','admin123','admin');return false;" style="color:#1b4332;font-weight:700;text-decoration:underline;">admin@email.com / admin123</a>
        </div>
      <?php endif; ?>
    </form>
  </div>

<script>
  const roleInput = document.getElementById('roleInput');
  const roleButtons = document.querySelectorAll('.toggle button');

  roleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      roleButtons.forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
      roleInput.value = btn.dataset.role;
    });
  });

  function fillDemo(em, pw, r) {
    document.getElementById('email').value = em;
    document.getElementById('pw').value = pw;
    roleInput.value = r;
    roleButtons.forEach(b => {
      b.classList.toggle('on', b.dataset.role === r);
    });
  }
</script>
</body>
</html>
