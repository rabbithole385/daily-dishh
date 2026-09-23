/* Daily Dish: ordering interactions (progressive enhancement: every form works without JS). */
(function () {
  'use strict';
  var body = document.body;
  var base = body.getAttribute('data-base') || '/';
  var csrf = body.getAttribute('data-csrf') || '';

  /* Mobile nav */
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* Toast */
  var toastEl = document.querySelector('[data-toast]');
  var toastTimer;
  function toast(msg, isError) {
    if (!toastEl || !msg) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('error', !!isError);
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.hidden = true; }, 2600);
  }

  /* Cart state in the UI */
  function syncCart(data) {
    document.querySelectorAll('[data-cart-count]').forEach(function (el) {
      el.textContent = data.count;
      el.hidden = data.count < 1;
      el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
    });
    document.querySelectorAll('[data-cart-subtotal]').forEach(function (el) { el.textContent = data.subtotal_text; });
    var bar = document.querySelector('[data-cart-bar]');
    if (bar) bar.hidden = data.count < 1;
    body.classList.toggle('has-cart-bar', !!bar && data.count > 0);
  }
  if (document.querySelector('[data-cart-bar]:not([hidden])')) body.classList.add('has-cart-bar');

  function post(fields) {
    var fd = new FormData();
    fd.append('csrf', csrf);
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    return fetch(base + 'api/cart.php', {
      method: 'POST', body: fd, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    }).then(function (r) { return r.json(); });
  }

  function markAdded(itemId, qty) {
    document.querySelectorAll('form[data-add] input[name="item_id"][value="' + itemId + '"]').forEach(function (inp) {
      var btn = inp.form.querySelector('.btn-add');
      var q = inp.form.querySelector('[data-qty]');
      if (q) { q.textContent = qty; q.hidden = qty < 1; }
      if (btn) { btn.classList.remove('added'); void btn.offsetWidth; btn.classList.add('added'); }
    });
  }

  /* Quick add buttons */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-add]')) return;
    e.preventDefault();
    var id = form.querySelector('[name="item_id"]').value;
    post({ action: 'add', item_id: id, qty: 1 }).then(function (d) {
      if (!d.ok) return toast(d.message, true);
      syncCart(d); markAdded(id, d.qty); toast(d.message);
    }).catch(function () { form.submit(); });
  });

  /* Dish sheet */
  var sheet = document.getElementById('dishSheet');
  if (sheet && typeof sheet.showModal === 'function') {
    var cur = null, qty = 1;
    var $ = function (s) { return sheet.querySelector(s); };
    function renderQty() {
      $('[data-sheet-qty]').textContent = qty;
      $('[data-sheet-total]').textContent = cur ? formatNaira(cur.price * qty) : '';
    }
    function formatNaira(n) { return '₦' + Math.round(n).toLocaleString('en-NG'); }
    function open(d) {
      cur = d; qty = 1;
      $('[data-sheet-img]').src = d.img; $('[data-sheet-img]').alt = d.name;
      $('[data-sheet-cat]').textContent = d.cat;
      $('[data-sheet-name]').textContent = d.name;
      $('[data-sheet-desc]').textContent = d.desc;
      renderQty();
      sheet.showModal(); body.classList.add('sheet-open');
    }
    sheet.addEventListener('close', function () { body.classList.remove('sheet-open'); });
    sheet.addEventListener('click', function (e) { if (e.target === sheet) sheet.close(); });
    $('[data-sheet-close]').addEventListener('click', function () { sheet.close(); });
    $('[data-sheet-dec]').addEventListener('click', function () { qty = Math.max(1, qty - 1); renderQty(); });
    $('[data-sheet-inc]').addEventListener('click', function () { qty = Math.min(20, qty + 1); renderQty(); });
    $('[data-sheet-add]').addEventListener('click', function () {
      var btn = this; btn.disabled = true;
      post({ action: 'add', item_id: cur.id, qty: qty }).then(function (d) {
        btn.disabled = false;
        if (!d.ok) return toast(d.message, true);
        syncCart(d); markAdded(cur.id, d.qty); sheet.close(); toast(d.message);
      }).catch(function () { btn.disabled = false; toast('Could not reach the kitchen. Check your connection.', true); });
    });
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-open-dish]');
      if (!t) return;
      var host = t.closest('[data-dish]');
      if (!host) return;
      e.preventDefault();
      open(JSON.parse(host.getAttribute('data-dish')));
    });
  }

  /* Menu: live search + active chip */
  var search = document.getElementById('menuSearch');
  if (search) {
    var cats = document.querySelectorAll('[data-menu-cat]');
    var empty = document.getElementById('menuEmpty');
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      var any = false;
      cats.forEach(function (cat) {
        var shown = 0;
        cat.querySelectorAll('[data-search]').forEach(function (el) {
          var hit = !q || el.getAttribute('data-search').indexOf(q) !== -1;
          el.hidden = !hit; if (hit) shown++;
        });
        cat.querySelectorAll('[data-group]').forEach(function (g) {
          g.hidden = !g.querySelector('[data-search]:not([hidden])');
        });
        cat.hidden = shown === 0; if (shown) any = true;
      });
      if (empty) empty.hidden = any;
    });
  }
  var chips = document.querySelectorAll('.menu-bar .chip');
  if (chips.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        chips.forEach(function (c) {
          var on = c.getAttribute('href') === '#' + en.target.id;
          c.classList.toggle('on', on);
          if (on) c.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    document.querySelectorAll('[data-menu-cat]').forEach(function (s) { io.observe(s); });
  }

  /* Home: delivery fee check */
  var zoneSel = document.getElementById('zoneCheck');
  if (zoneSel) {
    zoneSel.addEventListener('change', function () {
      var opt = zoneSel.options[zoneSel.selectedIndex];
      document.getElementById('zoneFee').textContent = opt.getAttribute('data-fee-text') || '';
    });
  }

  /* Checkout: delivery vs pickup, payment, live total */
  var co = document.getElementById('checkoutForm');
  if (co) {
    var sub = parseFloat(co.getAttribute('data-subtotal')) || 0;
    function money(n) { return '₦' + Math.round(n).toLocaleString('en-NG'); }
    function update() {
      var ful = co.querySelector('[name="fulfillment"]:checked').value;
      var pay = co.querySelector('[name="payment_method"]:checked');
      document.querySelectorAll('[data-if-delivery]').forEach(function (el) { el.hidden = ful !== 'delivery'; });
      var zone = co.querySelector('[name="delivery_zone"]');
      var fee = 0;
      if (ful === 'delivery' && zone && zone.value) fee = parseFloat(zone.options[zone.selectedIndex].getAttribute('data-fee')) || 0;
      var feeEl = document.getElementById('sumFee');
      if (feeEl) feeEl.textContent = ful === 'pickup' ? 'Pickup: free' : (zone && zone.value ? money(fee) : 'Choose area');
      var tot = document.getElementById('sumTotal');
      if (tot) tot.textContent = money(sub + fee);
      var bank = document.getElementById('bankBox');
      if (bank) bank.hidden = !(pay && pay.value === 'transfer_now');
      var t = co.querySelector('[name="when"]:checked');
      var sched = document.getElementById('schedTime');
      if (sched) sched.hidden = !(t && t.value === 'later');
    }
    co.addEventListener('change', update);
    update();
    co.addEventListener('submit', function () {
      var b = co.querySelector('[type="submit"]'); if (b) { b.disabled = true; b.textContent = 'Sending to the kitchen…'; }
    });
  }

  /* Tracking page auto-refresh */
  var tr = document.querySelector('[data-autorefresh]');
  if (tr) setTimeout(function () { location.reload(); }, 45000);
})();
