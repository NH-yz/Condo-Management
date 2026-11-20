<?php
// terms.php - Facility booking Terms & Conditions page

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireLogin();

$pageTitle = 'Terms & Conditions';
require_once __DIR__ . '/includes_header.php';
?>

<style>
  html{scroll-behavior:smooth}
  .terms-wrap{max-width:860px;margin:0 auto;position:relative}
  .terms-wrap section{scroll-margin-top:20px}
  .terms-toc-col{position:absolute;right:100%;top:0;bottom:0;margin-right:22px;width:190px}
  .terms-toc{position:sticky;top:20px;margin-top:62px;background:#fff;border:1px solid var(--gray-border);
    border-top:3px solid var(--primary);border-radius:8px;padding:12px 14px;box-shadow:0 1px 4px rgba(0,0,0,.05)}
  .terms-toc h4{margin:0 0 8px;font-size:13px;color:var(--gray-heading)}
  .terms-toc ol{list-style:none;margin:0;padding:0}
  .terms-toc li{margin:0}
  .terms-toc a{display:block;padding:6px 8px;border-radius:5px;font-size:13px;font-weight:600;color:var(--primary-light);text-decoration:none}
  .terms-toc a span{display:inline-block;min-width:30px;font-weight:700}
  .terms-toc a:hover{background:var(--primary-tint);color:var(--primary)}
  @media (max-width:1200px){
    .terms-toc-col{position:static;width:auto;margin:0 0 14px}
    .terms-toc{position:static;margin-top:0}
  }
</style>

<div class="terms-wrap">
  <div class="terms-toc-col">
    <nav class="terms-toc" aria-label="Terms sections">
      <h4>Contents</h4>
      <ol>
          <li><a href="#general"><span>1.0</span> General</a></li>
          <li><a href="#function-hall"><span>2.0</span> Function Hall</a></li>
          <li><a href="#badminton-court"><span>3.0</span> Badminton Court</a></li>
          <li><a href="#bbq-pit"><span>4.0</span> BBQ Pit</a></li>
          <li><a href="#gymnasium"><span>5.0</span> Gymnasium</a></li>
          <li><a href="#swimming-pool"><span>6.0</span> Swimming Pool</a></li>
      </ol>
    </nav>
  </div>

<h1>Terms &amp; Conditions</h1>

<div class="card terms-page" style="max-width:none;">
  <section id="general">
    <h2 style="font-size:17px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--gray-border);">1.0 General Terms (All Facilities)</h2>
    <p>Please read the following terms carefully before booking any condominium facility. They apply to every booking.</p>
    <ol>
    <li><b>Eligibility.</b> Only registered residents may book facilities. You are responsible for all bookings made under your account.</li>
    <li><b>Approval.</b> All bookings are submitted as <i>Pending</i> and are only confirmed once approved by management. Management may decline or cancel a booking where necessary.</li>
    <li><b>Fees.</b> Hourly rates are shown at the time of booking. Fees are charged in Ringgit Malaysia (RM) and are payable as instructed by management.</li>
    <li><b>Time slots.</b> Facilities may only be used during the booked time slot. Overstaying may result in additional charges or restrictions on future bookings.</li>
    <li><b>Cancellations.</b> Please cancel any booking you no longer need so others may use the slot. Repeated no-shows may lead to suspension of booking privileges.</li>
    <li><b>Proper use.</b> Facilities must be used with care and in line with house rules. Noise must be kept reasonable and the facility left clean and tidy after use.</li>
    <li><b>Damage and liability.</b> Residents are liable for any damage to facilities or equipment caused by them or their guests. Use of facilities is at your own risk and management is not liable for personal injury or loss of belongings.</li>
    <li><b>Guests.</b> You are responsible for the conduct and safety of your guests, including children and must stay within each facility's capacity limits.</li>
    <li><b>Availability.</b> Facilities may be closed for maintenance or emergencies. Facilities marked as unavailable cannot be booked.</li>
    <li><b>Changes.</b> Management may update these terms from time to time. Continued use of the booking service means you accept the updated terms.</li>
  </ol>
  </section>

  <p style="margin:26px 0 0;"><b>Facility-specific rules</b> are listed below. By booking a facility you also agree to follow the rules for that facility.</p>

  <section id="function-hall" style="margin-top:26px;">
    <h2 style="font-size:17px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--gray-border);">2.0 Function Hall</h2>
    <ul style="padding-left:22px;margin:0;">
      <li>The maximum capacity of 100 persons must not be exceeded.</li>
      <li>Music and noise must stop by 10:00 PM and be kept at a reasonable level at all times.</li>
      <li>Decorations must not be fixed with nails, tape or adhesives that damage walls or fittings.</li>
      <li>The hall must be cleaned and all rubbish removed before the end of your booking. Management may charge a cleaning fee if it is left untidy.</li>
      <li>Management may require a refundable security deposit for large events.</li>
    </ul>
  </section>
  <section id="badminton-court" style="margin-top:26px;">
    <h2 style="font-size:17px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--gray-border);">3.0 Badminton Court</h2>
    <ul style="padding-left:22px;margin:0;">
      <li>Non-marking sports shoes must be worn on the court.</li>
      <li>Food and drinks (other than water) are not allowed on the court.</li>
      <li>A maximum of 6 players is allowed per booking, including those waiting to play.</li>
      <li>Players must leave the court promptly when the booked time ends.</li>
      <li>Please report any damage to the court, nets or lighting to management immediately.</li>
    </ul>
  </section>
  <section id="bbq-pit" style="margin-top:26px;">
    <h2 style="font-size:17px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--gray-border);">4.0 BBQ Pit</h2>
    <ul style="padding-left:22px;margin:0;">
      <li>Fires must never be left unattended and a responsible adult must be present throughout.</li>
      <li>Only charcoal or approved BBQ fuel may be used. Open fires of any other kind are prohibited.</li>
      <li>Hot charcoal and ashes must be fully cooled and disposed of in the designated area, not in drains or normal bins.</li>
      <li>The pit, sink and seating area must be cleaned after use.</li>
      <li>Residents are responsible for any fire, injury or damage caused by their use of the BBQ Pit.</li>
      <li>Use may be stopped immediately in bad weather or if there is a fire safety risk.</li>
    </ul>
  </section>
  <section id="gymnasium" style="margin-top:26px;">
    <h2 style="font-size:17px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--gray-border);">5.0 Gymnasium</h2>
    <ul style="padding-left:22px;margin:0;">
      <li>Proper sports attire and shoes must be worn and a towel should be used on equipment.</li>
      <li>Equipment must be used according to its instructions and wiped down after use. Weights must be returned to their racks.</li>
      <li>Residents exercise at their own risk and should be in suitable health to do so.</li>
      <li>Children below 16 years old must be accompanied by an adult.</li>
      <li>Food is not allowed in the gym. Closed bottles of water only.</li>
    </ul>
  </section>
  <section id="swimming-pool" style="margin-top:26px;">
    <h2 style="font-size:17px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--gray-border);">6.0 Swimming Pool</h2>
    <p class="note" style="margin:0 0 6px;"><i>The Swimming Pool is currently unavailable for booking. These rules will apply once it reopens.</i></p>
    <ul style="padding-left:22px;margin:0;">
      <li>Children under 12 must be supervised by an adult at all times.</li>
      <li>Proper swimwear is required and a shower must be taken before entering the pool.</li>
      <li>Glass items, food and pets are not allowed in the pool area.</li>
      <li>Diving, running and rough play are not allowed. Lifeguard instructions must be followed.</li>
      <li>The pool may be closed without notice for cleaning, maintenance or bad weather.</li>
    </ul>
  </section>

  <p style="margin-top:26px;">
    <a class="btn btn-dark" href="book-facility.php">Go to Book a Facility</a>
  </p>
</div>

</div>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
