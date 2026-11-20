/* CondoCare - Core Client-side JavaScript
   Rapid Application Development (DCS2102)
   Features:
   1. Live table search & multi-field filter (chips, tower, date)
   2. Interactive facility booking & dynamic rate calculation
   3. Live character counter (500 max characters)
   4. Comprehensive client-side form validation (regex, required fields, passwords)
   5. Modal & delete confirmation system
*/

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

// Parse dd/mm/yyyy or yyyy-mm-dd -> Date object
function parseDate(v) {
  if (!v) return null;
  v = v.trim();
  // dd/mm/yyyy
  let m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(v);
  if (m) {
    const d = new Date(+m[3], +m[2] - 1, +m[1]);
    return d.getMonth() === +m[2] - 1 ? d : null;
  }
  // yyyy-mm-dd
  m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(v);
  if (m) {
    const d = new Date(+m[1], +m[2] - 1, +m[3]);
    return d.getMonth() === +m[2] - 1 ? d : null;
  }
  return null;
}

function today() {
  const t = new Date();
  t.setHours(0, 0, 0, 0);
  return t;
}

/* =====================================================
   1. Tables: Live search + Status chips + Multi-filters
   ===================================================== */
(function () {
  const table = $('#tbl');
  if (!table) return;

  const search = $('#search');
  const tower = $('#tower');
  const dateF = $('#dateFilter');
  const none = $('#noData');
  let currentStatus = 'all';

  function applyFilter() {
    const q = search ? search.value.trim().toLowerCase() : '';
    const d = dateF ? parseDate(dateF.value) : null;
    let shownCount = 0;

    $$('tbody tr', table).forEach(tr => {
      // Combine all columns except action
      const cells = Array.from(tr.cells);
      const text = cells.slice(0, -1).map(c => c.textContent).join(' ').toLowerCase();

      let matchSearch = !q || text.includes(q);
      let matchStatus = (currentStatus === 'all') || (tr.dataset.status === currentStatus);
      let matchTower  = (!tower || !tower.value) || (tr.dataset.tower === tower.value);

      let matchDate = true;
      if (d && tr.dataset.date) {
        const rowD = parseDate(tr.dataset.date);
        matchDate = !!rowD && rowD.getTime() === d.getTime();
      }

      const visible = matchSearch && matchStatus && matchTower && matchDate;
      tr.hidden = !visible;
      if (visible) shownCount++;
    });

    if (none) {
      none.hidden = shownCount > 0;
    }
  }

  [search, tower, dateF].forEach(el => {
    if (!el) return;
    el.addEventListener('input', applyFilter);
    el.addEventListener('change', applyFilter);
  });

  $$('.chip').forEach(chip => {
    chip.addEventListener('click', () => {
      $$('.chip').forEach(c => c.classList.remove('on'));
      chip.classList.add('on');
      currentStatus = chip.dataset.filter || 'all';
      applyFilter();
    });
  });
})();

/* =====================================================
   2. Report / Edit Defect: Real-time Character Counter & Form Validation
   ===================================================== */
(function () {
  const form = $('#reportForm');
  if (!form) return;

  const desc = $('#desc');
  const counter = $('#counter');
  const msg = $('#msg');

  const showMsg = (text, type = 'error') => {
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'msg ' + type;
  };

  if (desc && counter) {
    const updateCount = () => {
      const len = desc.value.length;
      counter.textContent = `${len} / 500 characters`;
      if (len > 500) {
        counter.style.color = '#b00020';
      } else {
        counter.style.color = '#777';
      }
    };
    desc.addEventListener('input', updateCount);
    updateCount();
  }

  form.addEventListener('submit', e => {
    // Client-side validations
    const requiredInputs = $$('[required]', form);
    for (const el of requiredInputs) {
      if (!el.value.trim()) {
        e.preventDefault();
        const lbl = el.dataset.label || 'Required field';
        showMsg(`Please fill in: ${lbl}.`, 'error');
        el.focus();
        return;
      }
    }

    const pdate = $('#pdate');
    if (pdate && pdate.value.trim()) {
      const d = parseDate(pdate.value);
      if (!d) {
        e.preventDefault();
        showMsg('Preferred date must be in dd/mm/yyyy or yyyy-mm-dd format.', 'error');
        pdate.focus();
        return;
      }
      if (d < today()) {
        e.preventDefault();
        showMsg('Preferred date cannot be in the past.', 'error');
        pdate.focus();
        return;
      }
    }

    const photo = $('#photo');
    if (photo && photo.files && photo.files[0]) {
      const file = photo.files[0];
      const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        e.preventDefault();
        showMsg('Photo must be JPG, PNG or WEBP.', 'error');
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        e.preventDefault();
        showMsg('Photo must be less than 5 MB.', 'error');
        return;
      }
    }
  });
})();

/* =====================================================
   3. Facility Booking: Slots Picker + Live Cost Calculation
   ===================================================== */
(function () {
  const box = $('#slots');
  if (!box) return;

  const facSelect = $('#facility');
  const dateInput = $('#date');
  const msg       = $('#msg');
  const form      = $('#bookingForm');
  const startH    = $('#start_hour');
  const endH      = $('#end_hour');
  const totalInp  = $('#total_amount');

  const sFac   = $('#sFac');
  const sDate  = $('#sDate');
  const sTime  = $('#sTime');
  const sTotal = $('#sTotal');

  const showMsg = (text, type = 'error') => {
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'msg ' + type;
  };

  const t12 = h => (h % 12 || 12) + ':00 ' + (h % 24 < 12 ? 'AM' : 'PM');

  // Operating hours of the selected facility (set by management in admin-facilities.php)
  function getOperatingHours() {
    const opt = facSelect && facSelect.selectedIndex >= 0 ? facSelect.options[facSelect.selectedIndex] : null;
    const open  = opt && opt.dataset.open  !== undefined && opt.dataset.open  !== '' ? parseInt(opt.dataset.open, 10)  : 5;
    const close = opt && opt.dataset.close !== undefined && opt.dataset.close !== '' ? parseInt(opt.dataset.close, 10) : 22;
    return { open, close };
  }

  // Build one slot button per hour between opening and closing time (defaults to 5 AM - 10 PM)
  function renderSlots() {
    const { open, close } = getOperatingHours();
    box.innerHTML = '';
    for (let h = open; h < close; h++) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'slot';
      b.dataset.hour = h;
      b.textContent = (h % 12 || 12) + (h < 12 ? 'AM' : 'PM');
      box.appendChild(b);
    }
  }
  renderSlots();

  function getSelectedHours() {
    return $$('.slot.sel', box).map(s => +s.dataset.hour).sort((a, b) => a - b);
  }

  function getFacilityRate() {
    if (!facSelect) return 0;
    const opt = facSelect.options[facSelect.selectedIndex];
    return opt ? parseFloat(opt.dataset.rate || 0) : 0;
  }

  function updateSummary() {
    const sel = getSelectedHours();
    const rate = getFacilityRate();
    const facName = facSelect && facSelect.value ? facSelect.options[facSelect.selectedIndex].text.split('(')[0].trim() : '______';

    if (sFac) sFac.textContent = facName;
    if (sDate) sDate.textContent = dateInput.value || '______';

    if (sel.length > 0) {
      const start = sel[0];
      const end = sel[sel.length - 1] + 1;
      if (sTime) sTime.textContent = `${t12(start)} - ${t12(end)}`;
      const total = (sel.length * rate).toFixed(2);
      if (sTotal) sTotal.textContent = total;

      if (startH) startH.value = start;
      if (endH) endH.value = end;
      if (totalInp) totalInp.value = total;
    } else {
      if (sTime) sTime.textContent = '______';
      if (sTotal) sTotal.textContent = '__.__';
      if (startH) startH.value = '';
      if (endH) endH.value = '';
      if (totalInp) totalInp.value = '';
    }
  }

  // Fetch booked slots dynamically from backend
  async function checkAvailability() {
    if (!facSelect.value || !dateInput.value) return;
    const facId = facSelect.value;
    const dateVal = dateInput.value;

    try {
      const res = await fetch(`api_get-booked-slots.php?facility_id=${encodeURIComponent(facId)}&date=${encodeURIComponent(dateVal)}`);
      if (res.ok) {
        const data = await res.json();
        const booked = data.booked_hours || [];
        $$('.slot', box).forEach(slot => {
          const h = +slot.dataset.hour;
          if (booked.includes(h)) {
            slot.classList.add('booked');
            slot.classList.remove('sel');
            slot.disabled = true;
          } else {
            slot.classList.remove('booked');
            slot.disabled = false;
          }
        });
        updateSummary();
      }
    } catch (err) {
      console.warn('Slot check failed:', err);
    }
  }

  box.addEventListener('click', e => {
    const b = e.target.closest('.slot');
    if (!b || b.disabled) return;

    b.classList.toggle('sel');
    const sel = getSelectedHours();

    // Verify slots are contiguous
    if (sel.length > 0 && sel[sel.length - 1] - sel[0] + 1 !== sel.length) {
      b.classList.toggle('sel');
      showMsg('Please choose consecutive time slots.', 'error');
      return;
    }
    showMsg('', '');
    updateSummary();
  });

  if (facSelect) {
    facSelect.addEventListener('change', () => {
      renderSlots();          // hours differ per facility, so rebuild (and clear) the slot buttons
      checkAvailability();
      updateSummary();
    });
  }

  if (dateInput) {
    dateInput.addEventListener('change', () => {
      checkAvailability();
      updateSummary();
    });
    dateInput.addEventListener('input', () => {
      updateSummary();
    });
  }

  if (form) {
    form.addEventListener('submit', e => {
      if (!facSelect.value) {
        e.preventDefault();
        showMsg('Please select a facility.', 'error');
        facSelect.focus();
        return;
      }

      const d = parseDate(dateInput.value);
      if (!d) {
        e.preventDefault();
        showMsg('Please enter a valid date (dd/mm/yyyy or yyyy-mm-dd).', 'error');
        dateInput.focus();
        return;
      }
      if (d < today()) {
        e.preventDefault();
        showMsg('Date cannot be in the past.', 'error');
        dateInput.focus();
        return;
      }

      const sel = getSelectedHours();
      if (sel.length === 0) {
        e.preventDefault();
        showMsg('Please select at least one available time slot.', 'error');
        return;
      }
    });
  }

  updateSummary();
})();

/* =====================================================
   4. Admin / Resident / Facility Add / Edit Validation
   ===================================================== */
(function () {
  const f = $('form[data-validate]');
  if (!f) return;

  const msg = $('#msg');
  const showMsg = (t, type = 'error') => {
    if (!msg) return;
    msg.textContent = t;
    msg.className = 'msg ' + type;
  };

  f.addEventListener('submit', e => {
    const kind = f.dataset.validate;
    const v = name => (f.elements[name] ? f.elements[name].value.trim() : '');

    // Check required inputs
    for (const el of $$('[required]', f)) {
      if (!el.value.trim()) {
        e.preventDefault();
        showMsg(`Required field empty: ${el.dataset.label || el.name}.`, 'error');
        el.focus();
        return;
      }
    }

    // Facility rules (admin-facilities-add.php)
    if (kind === 'facility') {
      const rate = parseFloat(v('hourly_rate'));
      if (isNaN(rate) || rate < 0) {
        e.preventDefault();
        showMsg('Hourly rate must be 0 or more (use 0 for free facilities).', 'error');
        f.elements['hourly_rate'].focus();
        return;
      }
      if (parseInt(v('open_hour'), 10) >= parseInt(v('close_hour'), 10)) {
        e.preventDefault();
        showMsg('Closing time must be later than opening time.', 'error');
        f.elements['close_hour'].focus();
        return;
      }
    }

    // Email check
    if (f.elements['email'] && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('email'))) {
      e.preventDefault();
      showMsg('Please enter a valid email address.', 'error');
      f.elements['email'].focus();
      return;
    }

    // Phone check
    if (f.elements['phone'] && !/^[0-9+\-\s]{8,18}$/.test(v('phone'))) {
      e.preventDefault();
      showMsg('Please enter a valid phone number (e.g. 012-345 6789).', 'error');
      f.elements['phone'].focus();
      return;
    }

    // Resident unit format
    if (kind === 'resident') {
      if (f.elements['unit'] && !/^[A-Za-z]-\d{1,2}-\d{2}$/.test(v('unit'))) {
        e.preventDefault();
        showMsg('Unit number format should be e.g. A-12-03.', 'error');
        f.elements['unit'].focus();
        return;
      }
    }

    // Password confirmation on Admin or new Resident
    if (f.elements['password'] && f.elements['password'].value) {
      if (v('password').length < 6) {
        e.preventDefault();
        showMsg('Password must be at least 6 characters.', 'error');
        f.elements['password'].focus();
        return;
      }
      if (f.elements['confirm'] && v('password') !== v('confirm')) {
        e.preventDefault();
        showMsg('Passwords do not match.', 'error');
        f.elements['confirm'].focus();
        return;
      }
    }
  });
})();

/* =====================================================
   5. Modal & Confirmation Helpers
   ===================================================== */
function openModal(title, bodyHtml, footerButtons = '') {
  let modal = $('#globalModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'globalModal';
    modal.className = 'modal-backdrop';
    document.body.appendChild(modal);
  }

  modal.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <h3>${title}</h3>
        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <div class="modal-body">${bodyHtml}</div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal()">Close</button>
        ${footerButtons}
      </div>
    </div>
  `;
  modal.hidden = false;
}

function closeModal() {
  const modal = $('#globalModal');
  if (modal) modal.hidden = true;
}

// Global click delegation for view and delete confirmation
document.addEventListener('click', e => {
  // Delete confirmation
  const delBtn = e.target.closest('[data-confirm]');
  if (delBtn) {
    const text = delBtn.dataset.confirm || 'Are you sure you want to proceed?';
    if (!confirm(text)) {
      e.preventDefault();
      return;
    }
  }

  // View modal
  const viewBtn = e.target.closest('[data-view-json]');
  if (viewBtn) {
    try {
      const data = JSON.parse(viewBtn.dataset.viewJson);
      let html = '<table style="width:100%;border-collapse:collapse;">';
      for (const [key, val] of Object.entries(data)) {
        html += `<tr><td style="font-weight:700;width:35%;padding:6px;border-bottom:1px solid #eee;">${key}</td><td style="padding:6px;border-bottom:1px solid #eee;">${val || '-'}</td></tr>`;
      }
      html += '</table>';
      openModal(viewBtn.dataset.title || 'Details', html);
    } catch (err) {
      console.error(err);
    }
  }
});
