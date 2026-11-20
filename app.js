/* CondoCare – shared JavaScript for all pages */
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

// dd/mm/yyyy -> Date (or null if invalid)
function parseDate(v) {
  const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec((v || '').trim());
  if (!m) return null;
  const d = new Date(+m[3], +m[2] - 1, +m[1]);
  return d.getMonth() === +m[2] - 1 ? d : null;
}
function today() { const t = new Date(); t.setHours(0, 0, 0, 0); return t; }

const LABEL = {
  reported: 'Reported', review: 'Under review', progress: 'In progress', resolved: 'Resolved',
  pending: 'Pending', approved: 'Approved', cancelled: 'Cancelled', completed: 'Completed'
};

/* =====================================================
   1. Tables: live search + status chips + filters + row actions
   ===================================================== */
(function () {
  const table = $('#tbl');
  if (!table) return;
  const search = $('#search'), tower = $('#tower'), dateF = $('#dateFilter'), none = $('#noData');
  const heads = $$('thead th', table).map(th => th.textContent.trim());
  let status = 'all';

  function apply() {
    const q = search ? search.value.trim().toLowerCase() : '';
    const d = dateF ? parseDate(dateF.value) : null;
    let shown = 0;
    $$('tbody tr', table).forEach(tr => {
      // search every column except the last (Action)
      const text = Array.from(tr.cells).slice(0, -1).map(c => c.textContent).join(' ').toLowerCase();
      let ok = !q || text.includes(q);
      if (ok && status !== 'all') ok = tr.dataset.status === status;
      if (ok && tower && tower.value) ok = tr.dataset.tower === tower.value;
      if (ok && d) { const rd = parseDate(tr.dataset.date || ''); ok = !!rd && rd.getTime() === d.getTime(); }
      tr.hidden = !ok;
      if (ok) shown++;
    });
    if (none) none.hidden = shown > 0;
  }

  [search, tower, dateF].forEach(el => {
    if (!el) return;
    el.addEventListener('input', apply);
    el.addEventListener('change', apply);
  });
  $$('.chip').forEach(chip => chip.addEventListener('click', () => {
    $$('.chip').forEach(c => c.classList.toggle('on', c === chip));
    status = chip.dataset.filter;
    apply();
  }));

  function setStatus(tr, s, label) {
    tr.dataset.status = s;
    const cell = $('.status', tr);
    if (cell) cell.textContent = label || LABEL[s];
    if (['cancelled', 'completed', 'resolved'].includes(s)) {
      $$('[data-action=cancel],.js-edit', tr).forEach(x => x.remove());
    }
    apply();
  }

  document.addEventListener('click', e => {
    const b = e.target.closest('[data-action]');
    if (!b) return;
    const tr = b.closest('tr'), a = b.dataset.action, st = tr.dataset.status;

    if (a === 'delete') {
      if (confirm('Are you sure you want to delete this record?')) { tr.remove(); apply(); }

    } else if (a === 'view') {
      alert(Array.from(tr.cells).slice(0, -1).map((c, i) => heads[i] + ': ' + c.textContent.trim()).join('\n'));

    } else if (a === 'cancel') {                       // resident cancels own request
      const bd = parseDate(tr.dataset.date || '');
      if (tr.dataset.type === 'booking' && bd && bd <= today()) return alert('Cancel is only allowed before the booking date.');
      if (tr.dataset.type === 'maintenance' && !['reported', 'review'].includes(st)) return alert('This request can no longer be cancelled because work has started.');
      if (confirm('Are you sure you want to cancel this request?')) setStatus(tr, 'cancelled');

    } else if (a === 'approve') {                      // admin: bookings
      if (st !== 'pending') return alert('Only pending bookings can be approved.');
      setStatus(tr, 'approved');
    } else if (a === 'reject') {
      if (st !== 'pending') return alert('Only pending bookings can be rejected.');
      if (confirm('Reject this booking?')) setStatus(tr, 'cancelled', 'Rejected');
    } else if (a === 'used') {
      if (st !== 'approved') return alert('Only approved bookings can be marked as used.');
      setStatus(tr, 'completed');

    } else if (a === 'assign') {                       // admin: maintenance / dashboard
      if (['resolved', 'cancelled'].includes(st)) return alert('Cannot assign a technician to a closed ticket.');
      const name = prompt('Assign technician:\nAhmad Faiz / Siti Aminah / Kumar R. / Lee Wei Jie');
      if (!name || !name.trim()) return;
      if (tr.dataset.type === 'new') { tr.remove(); alert('Assigned to ' + name.trim() + '.'); return; }
      const t = $('.tech', tr);
      if (t) t.textContent = name.trim();
      if (st === 'reported') setStatus(tr, 'review');

    } else if (a === 'update') {                       // admin: change status
      const s = (prompt('New status (reported / review / progress / resolved / cancelled):', st) || '').trim().toLowerCase();
      if (!s) return;
      if (!['reported', 'review', 'progress', 'resolved', 'cancelled'].includes(s)) return alert('Invalid status.');
      setStatus(tr, s);
    }
  });
})();

/* success banner on My Requests (shown after submitting a defect) */
(function () {
  const b = $('#banner');
  if (b && /submitted=1/.test(location.search)) b.hidden = false;
})();

/* =====================================================
   2. Login
   ===================================================== */
(function () {
  const f = $('#loginForm');
  if (!f) return;
  let role = 'resident';
  const ACC = {
    resident:   { email: 'resident@email.com', pw: '12345678', go: 'resident-home.html' },
    management: { email: 'admin@email.com',    pw: 'admin123', go: 'admin-dashboard.html' }
  };
  $$('[data-role]').forEach(b => b.addEventListener('click', () => {
    role = b.dataset.role;
    $$('[data-role]').forEach(x => x.classList.toggle('on', x === b));
  }));
  f.addEventListener('submit', e => {
    e.preventDefault();
    const em = $('#email').value.trim().toLowerCase(), pw = $('#pw').value, msg = $('#msg');
    if (!em || !pw) { msg.textContent = 'Please enter your email and password.'; msg.className = 'msg error'; return; }
    const a = ACC[role];
    if (em === a.email && pw === a.pw) location.href = a.go;
    else { msg.textContent = 'Invalid email or password.'; msg.className = 'msg error'; }
  });
})();

/* =====================================================
   3. Report a Defect: counter + validation
   ===================================================== */
(function () {
  const f = $('#reportForm');
  if (!f) return;
  const desc = $('#desc'), counter = $('#counter'), msg = $('#msg');
  const say = (t, type) => { msg.textContent = t; msg.className = 'msg ' + (type || ''); };

  desc.addEventListener('input', () => {
    counter.textContent = desc.value.length + ' / 500 characters (JS counter)';
  });

  f.addEventListener('submit', e => {
    e.preventDefault();
    for (const el of $$('[required]', f)) {
      if (!el.value.trim()) { say('Required field empty: ' + el.dataset.label + '.', 'error'); el.focus(); return; }
    }
    const pd = $('#pdate').value.trim();
    if (pd) {
      const d = parseDate(pd);
      if (!d) return say('Preferred date must be in the format dd/mm/yyyy.', 'error');
      if (d < today()) return say('Preferred date cannot be in the past.', 'error');
    }
    const file = $('#photo').files[0];
    if (file && (!/^image\/(jpeg|png)$/.test(file.type) || file.size > 5 * 1024 * 1024)) {
      return say('Photo must be JPG / PNG, max 5 MB.', 'error');
    }
    location.href = 'my-requests-maintenance.html?submitted=1';
  });
})();

/* =====================================================
   4. Book a Facility: slots + auto total
   ===================================================== */
(function () {
  const box = $('#slots');
  if (!box) return;
  const RATE = { 'Function Hall': 50, 'Badminton Court': 10, 'BBQ Pit': 30 };   // RM per hour
  const BOOKED = [8, 9, 13, 17];                                                // 8AM, 9AM, 1PM, 5PM
  const t12 = h => (h % 12 || 12) + ':00 ' + (h < 12 ? 'AM' : 'PM');
  const msg = $('#msg'), fac = $('#facility'), date = $('#date');
  const say = (t, type) => { msg.textContent = t; msg.className = 'msg ' + (type || ''); };

  for (let h = 8; h <= 21; h++) {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'slot'; b.dataset.hour = h;
    b.textContent = (h % 12 || 12) + (h < 12 ? 'AM' : 'PM');
    if (BOOKED.includes(h)) { b.classList.add('booked'); b.disabled = true; }
    box.appendChild(b);
  }

  const picked = () => $$('.slot.sel').map(s => +s.dataset.hour).sort((a, b) => a - b);

  function update() {
    const p = picked(), rate = RATE[fac.value] || 0;
    $('#sFac').textContent  = fac.value || '______';
    $('#sDate').textContent = date.value || '______';
    $('#sTime').textContent = p.length ? t12(p[0]) + ' – ' + t12(p[p.length - 1] + 1) : '______';
    $('#sTotal').textContent = p.length && rate ? (p.length * rate).toFixed(2) : '__.__';   // hours × rate
  }

  box.addEventListener('click', e => {
    const b = e.target.closest('.slot');
    if (!b || b.disabled) return;
    b.classList.toggle('sel');
    const p = picked();
    if (p.length && p[p.length - 1] - p[0] + 1 !== p.length) {          // must be consecutive
      b.classList.toggle('sel');
      say('Please choose consecutive time slots.', 'error');
      return;
    }
    say('', '');
    update();
  });
  [fac, date].forEach(el => el.addEventListener('input', update));

  $('#confirm').addEventListener('click', () => {
    if (!fac.value) return say('Please select a facility.', 'error');
    const d = parseDate(date.value);
    if (!d) return say('Please enter a valid date (dd/mm/yyyy).', 'error');
    if (d < today()) return say('Date cannot be in the past.', 'error');
    if (!picked().length) return say('Please choose at least one time slot.', 'error');
    alert('Booking submitted! Status: Pending approval.');
    location.href = 'my-requests-bookings.html';
  });
  update();
})();

/* =====================================================
   5. Admin "Add New" forms (resident / admin / technician)
   ===================================================== */
(function () {
  const f = $('form[data-form]');
  if (!f) return;
  const msg = $('#msg');
  const say = (t, type) => { msg.textContent = t; msg.className = 'msg ' + (type || ''); };
  const REG = {                                   // emails that already exist
    resident:   ['meiling.tan@email.com', 'weihong.t@email.com', 'aisyah@email.com', 'jiahui.lim@email.com'],
    admin:      ['admin@email.com', 'daniel.tan@email.com', 'huda@email.com'],
    technician: ['faiz@email.com', 'siti@email.com']
  };
  const FAC_NAMES = ['badminton court', 'bbq pit', 'function hall', 'gymnasium', 'swimming pool'];   // existing facility names

  f.addEventListener('submit', e => {
    e.preventDefault();
    const kind = f.dataset.form;
    const v = n => (f.elements[n] ? f.elements[n].value.trim() : '');

    for (const el of $$('[required]', f)) {
      if (!el.value.trim()) { say('Required field empty: ' + el.dataset.label + '.', 'error'); el.focus(); return; }
    }
    if (kind === 'facility') {                      // facilities have no email / phone
      const rate = parseFloat(v('hourly_rate'));
      if (isNaN(rate) || rate < 0) return say('Hourly rate must be 0 or more (use 0 for free facilities).', 'error');
      if (+v('open_hour') >= +v('close_hour')) return say('Closing time must be later than opening time.', 'error');
      if (FAC_NAMES.includes(v('name').toLowerCase())) return say('A facility with this name already exists.', 'error');
      say('Saved successfully. Redirecting…', 'success');
      setTimeout(() => { location.href = f.dataset.next; }, 1200);
      return;
    }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('email'))) return say('Please enter a valid email address.', 'error');
    if (REG[kind].includes(v('email').toLowerCase())) return say('This email is already registered.', 'error');
    if (!/^[0-9+\-\s]{9,15}$/.test(v('phone'))) return say('Please enter a valid phone number (e.g. 012-345 6789).', 'error');

    if (kind === 'resident') {
      if (!/^[A-Za-z]-\d{2}-\d{2}$/.test(v('unit'))) return say('Unit no. must look like A-12-03.', 'error');
      if (v('movein') && !parseDate(v('movein'))) return say('Move-in date must be in the format dd/mm/yyyy.', 'error');
    }
    if (kind !== 'technician') {
      if (v('password').length < 8) return say('Password must be at least 8 characters.', 'error');
      if (kind === 'admin' && v('password') !== v('confirm')) return say('Passwords do not match.', 'error');
    }
    say('Saved successfully. Redirecting…', 'success');
    setTimeout(() => { location.href = f.dataset.next; }, 1200);
  });
})();