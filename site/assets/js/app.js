/* Daily Dish: interactions + gamification. Progressive enhancement —
   every form still works without JS.
   Features: hero food slider, featured carousel, scroll reveal,
   daily spin wheel with confetti, XP pops, loyalty pill sync,
   floating emoji rain, counter animations, sticky WA FAB,
   original cart / sheet / menu search / checkout flows. */
(function () {
  'use strict';
  var body = document.body;
  var base = body.getAttribute('data-base') || '/';
  var csrf = body.getAttribute('data-csrf') || '';
  var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------ Layers ------------------------------ */
  function ensureLayer(id) {
    var existing = document.getElementById(id);
    if (existing) return existing;
    var el = document.createElement('div');
    el.id = id;
    el.setAttribute('aria-hidden', 'true');
    document.body.appendChild(el);
    return el;
  }
  var confettiLayer, popLayer, emojiLayer;

  /* ------------------------------ Spice / Herb particles (confetti, food-friendly palette + SPICY emoji) ------------------------------ */
  function confetti(opts) {
    if (prefersReduced) return;
    opts = opts || {};
    confettiLayer = confettiLayer || ensureLayer('confettiLayer');
    var palette = opts.palette || ['#8B5A2B', '#C7862A', '#2E6B3F', '#A83A12', '#FFE6A8', '#D2B48C', '#704214'];
    var spicyEmojis = opts.spicy === false ? [] : (opts.spicyEmojis || ['🌶️', '🧄', '🧅', '🌿', '🍋', '🥬', '🌽', '🧂']);
    var count = opts.count || 120;
    var originX = opts.x == null ? 0.5 : opts.x;
    var originY = opts.y == null ? -0.1 : opts.y;
    var spread = opts.spread || 1.4;
    for (var i = 0; i < count; i++) {
      (function () {
        var piece = document.createElement('span');
        piece.className = 'conf';
        var isSpicy = spicyEmojis.length && Math.random() < (opts.spicyRatio || 0.22);
        if (isSpicy) {
          piece.classList.add('spicy');
          piece.textContent = spicyEmojis[(Math.random() * spicyEmojis.length) | 0];
        } else {
          piece.style.background = palette[(Math.random() * palette.length) | 0];
          if (Math.random() < 0.25) piece.style.borderRadius = '50%';
        }
        piece.style.left = (originX * 100) + '%';
        piece.style.top = (originY * 100) + '%';
        piece.style.transform = 'translate3d(0,0,0) rotate(0deg)';
        piece.style.opacity = '1';
        confettiLayer.appendChild(piece);
        var angle = (Math.random() - 0.5) * spread * Math.PI;
        var speed = (500 + Math.random() * 900);
        var vx = Math.sin(angle) * speed;
        var vy = -Math.cos(angle) * (speed * 0.9) + (Math.random() * -200);
        var rot = (Math.random() - 0.5) * 1080;
        var g = 1400 + Math.random() * 400;
        var dur = 1.4 + Math.random() * 1.2;
        var t0 = performance.now();
        function tick(t) {
          var p = Math.min(1, (t - t0) / 1000 / dur);
          var x = vx * p * dur / 1000;
          var y = vy * p * dur / 1000 + 0.5 * g * Math.pow(p * dur / 1000, 2);
          piece.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0) rotate(' + (rot * p) + 'deg)';
          piece.style.opacity = String(Math.max(0, 1 - p * 1.15));
          if (p < 1) requestAnimationFrame(tick); else piece.remove();
        }
        requestAnimationFrame(tick);
      })();
    }
  }

  /* ------------------------------ XP / Pop ------------------------------ */
  function xpPop(text, x, y, variant) {
    if (!text) return;
    popLayer = popLayer || ensureLayer('popLayer');
    var el = document.createElement('span');
    el.className = 'pop' + (variant ? ' ' + variant : '');
    el.textContent = text;
    el.style.left = x + 'px';
    el.style.top = y + 'px';
    popLayer.appendChild(el);
    setTimeout(function () { el.remove(); }, 1100);
  }

  /* ------------------------------ Floating ingredient / steam rise (food-only, gentle) ------------------------------ */
  function emojiRain(emojis, count) {
    if (prefersReduced) return;
    emojiLayer = emojiLayer || ensureLayer('emojiLayer');
    emojis = emojis || ['🍛', '🥘', '🌶️', '🍗', '🍲', '🍚', '🥩', '🍤', '🥬', '🧄', '🧅', '🍅'];
    count = count || 14;
    for (var i = 0; i < count; i++) {
      (function () {
        var f = document.createElement('span');
        f.className = 'floater';
        f.textContent = emojis[(Math.random() * emojis.length) | 0];
        f.style.left = (Math.random() * 100) + '%';
        f.style.animationDelay = (Math.random() * 2) + 's';
        f.style.fontSize = (1.1 + Math.random() * 1.6) + 'rem';
        emojiLayer.appendChild(f);
        setTimeout(function () { f.remove(); }, 9200);
      })();
    }
  }

  /* ------------------------------ Steam / heat burst (anchored to an element rect) ------------------------------ */
  function steamBurst(el, count, glyphs) {
    if (prefersReduced || !el) return;
    glyphs = glyphs || ['〰', '∿', '～', '~'];
    count = count || 3;
    var rect = el.getBoundingClientRect();
    var scrollX = window.pageXOffset || document.documentElement.scrollLeft;
    var scrollY = window.pageYOffset || document.documentElement.scrollTop;
    var layer = ensureLayer('steamLayer');
    for (var i = 0; i < count; i++) {
      (function () {
        var s = document.createElement('span');
        s.className = 'steam rise';
        s.textContent = glyphs[(Math.random() * glyphs.length) | 0];
        var x = rect.left + scrollX + rect.width * (0.35 + Math.random() * 0.3);
        var y = rect.top + scrollY + rect.height * 0.15;
        s.style.left = x + 'px';
        s.style.top = y + 'px';
        s.style.animationDelay = (Math.random() * 0.3) + 's';
        s.style.fontSize = (0.9 + Math.random() * 0.8) + 'rem';
        layer.appendChild(s);
        setTimeout(function () { s.remove(); }, 2800);
      })();
    }
  }

  /* ------------------------------ Mobile nav ------------------------------ */
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainNav');
  if (toggle && nav) {
    function setNav(open) {
      nav.classList.toggle('open', open);
      body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle.addEventListener('click', function () {
      setNav(!nav.classList.contains('open'));
    });
    // tap backdrop to close nav
    body.addEventListener('click', function (e) {
      if (!body.classList.contains('nav-open')) return;
      if (nav.contains(e.target) || toggle.contains(e.target)) return;
      setNav(false);
    }, true);
  }

  /* ------------------------------ Toast ------------------------------ */
  var toastEl = document.querySelector('[data-toast]');
  var toastTimer;
  function toast(msg, isError, variant) {
    if (!toastEl || !msg) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('error', !!isError);
    toastEl.classList.toggle('gold', variant === 'gold');
    toastEl.hidden = false;
    // Force reflow then animate in
    requestAnimationFrame(function () {
      // show transition: class-based
      if (toastEl.classList) toastEl.classList.add('show');
    });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toastEl.classList.remove('show');
      setTimeout(function () { toastEl.hidden = true; toastEl.classList.remove('gold', 'error'); }, 320);
    }, 2800);
  }

  /* ------------------------------ Game state sync ------------------------------ */
  var gameState = null;
  (function () {
    var pill = document.querySelector('[data-game-pill]');
    if (pill) {
      try { gameState = JSON.parse(pill.getAttribute('data-state') || 'null'); }
      catch (_) { gameState = null; }
    }
  })();

  function applyGameState(state) {
    if (!state) return;
    gameState = state;
    document.querySelectorAll('[data-game-points]').forEach(function (el) { el.textContent = state.points; });
    document.querySelectorAll('[data-game-streak]').forEach(function (el) { el.textContent = state.streak; });
    document.querySelectorAll('[data-game-orders]').forEach(function (el) { el.textContent = state.orders; });
    document.querySelectorAll('[data-game-xp]').forEach(function (el) { el.textContent = state.xp; });
    document.querySelectorAll('[data-game-level-name]').forEach(function (el) { el.textContent = state.level.name; });
    document.querySelectorAll('[data-game-level-emoji]').forEach(function (el) { el.textContent = state.level.emoji; });
    document.querySelectorAll('[data-game-next-name]').forEach(function (el) { el.textContent = state.level.next_name || 'Max level!'; });
    document.querySelectorAll('[data-game-next-min]').forEach(function (el) {
      if (state.level.next_min) el.textContent = state.level.next_min;
    });
    document.querySelectorAll('[data-game-progress]').forEach(function (el) {
      el.style.width = (state.level.progress || 0) + '%';
    });
    document.querySelectorAll('[data-can-spin]').forEach(function (el) {
      el.disabled = !state.can_spin;
      if (!state.can_spin) {
        var orig = el.getAttribute('data-orig-label');
        if (orig) el.innerHTML = orig;
        el.textContent = orig || 'Come back tomorrow!';
      }
    });
    document.querySelectorAll('[data-rd-points]').forEach(function (el) { el.textContent = state.points; });
    document.querySelectorAll('[data-rd-xp]').forEach(function (el) { el.textContent = state.xp; });
    document.querySelectorAll('[data-rd-streak]').forEach(function (el) { el.textContent = state.streak; });
    var pill = document.querySelector('[data-game-pill]');
    if (pill) {
      pill.classList.remove('pulse');
      void pill.offsetWidth;
      pill.classList.add('pulse');
      var avatar = pill.querySelector('.gp-level');
      if (avatar) avatar.style.setProperty('--lc', state.level.color || '#F4B63F');
      try { pill.setAttribute('data-state', JSON.stringify(state)); } catch (_) {}
    }
  }

  function rollCounter(el, from, to, duration) {
    if (!el || from === to) return;
    if (prefersReduced) { el.textContent = to; return; }
    duration = duration || 900;
    var t0 = performance.now();
    function tick(t) {
      var p = Math.min(1, (t - t0) / duration);
      // easeOutCubic
      var e = 1 - Math.pow(1 - p, 3);
      var v = Math.round(from + (to - from) * e);
      el.textContent = v;
      if (p < 1) requestAnimationFrame(tick);
      else el.textContent = to;
    }
    requestAnimationFrame(tick);
  }

  function celebrateAdd(btn, pointsText) {
    if (!btn) return;
    var rect = btn.getBoundingClientRect();
    var x = rect.left + rect.width / 2 + window.scrollX;
    var y = rect.top + window.scrollY;
    xpPop(pointsText || '+10 tasting notes', x, y, 'green');
    confetti({ count: 18, x: (rect.left + rect.width / 2) / window.innerWidth, y: (rect.top) / window.innerHeight, spread: 1.0, palette: ['#C7862A', '#8B5A2B', '#2E6B3F'] });
  }

  /* ------------------------------ Cart state ------------------------------ */
  function syncCart(data) {
    document.querySelectorAll('[data-cart-count]').forEach(function (el) {
      var before = parseInt(el.textContent || '0', 10) || 0;
      el.textContent = data.count;
      el.hidden = data.count < 1;
      if (data.count > before) {
        el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
      }
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

  function postSpin() {
    var fd = new FormData();
    fd.append('csrf', csrf);
    return fetch(base + 'api/spin.php', {
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

  /* ------------------------------ Quick add buttons (with XP pop) ------------------------------ */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-add]')) return;
    e.preventDefault();
    var id = form.querySelector('[name="item_id"]').value;
    var btn = form.querySelector('.btn-add');
    post({ action: 'add', item_id: id, qty: 1 }).then(function (d) {
      if (!d.ok) return toast(d.message, true);
      syncCart(d); markAdded(id, d.qty);
      toast(d.message, false, 'gold');
      celebrateAdd(btn, '+80 tasting notes');
    }).catch(function () { form.submit(); });
  });

  /* ------------------------------ Dish sheet (with interactive quantity slider) ------------------------------ */
  var sheet = document.getElementById('dishSheet');
  if (sheet && typeof sheet.showModal === 'function') {
    var cur = null, qty = 1;
    var $ = function (s) { return sheet.querySelector(s); };
    var slider = $('[data-sheet-slider]');
    function updateSliderFill() {
      if (!slider) return;
      var min = parseInt(slider.min || '1', 10);
      var max = parseInt(slider.max || '20', 10);
      var pct = ((qty - min) / (max - min)) * 100;
      slider.style.setProperty('--fill', pct + '%');
    }
    function renderQty() {
      $('[data-sheet-qty]').textContent = qty;
      $('[data-sheet-total]').textContent = cur ? formatNaira(cur.price * qty) : '';
      if (slider) slider.value = String(qty);
      updateSliderFill();
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
    if (slider) {
      slider.addEventListener('input', function () {
        qty = Math.max(1, Math.min(20, parseInt(slider.value, 10) || 1));
        $('[data-sheet-qty]').textContent = qty;
        $('[data-sheet-total]').textContent = cur ? formatNaira(cur.price * qty) : '';
        updateSliderFill();
      });
      slider.addEventListener('pointerdown', function () { slider.classList.add('dragging'); });
      slider.addEventListener('pointerup', function () { slider.classList.remove('dragging'); });
    }
    $('[data-sheet-add]').addEventListener('click', function () {
      var btn = this; btn.disabled = true;
      post({ action: 'add', item_id: cur.id, qty: qty }).then(function (d) {
        btn.disabled = false;
        if (!d.ok) return toast(d.message, true);
        syncCart(d); markAdded(cur.id, d.qty); sheet.close();
        toast(d.message, false, 'gold');
        var rect = btn.getBoundingClientRect();
        xpPop('+' + (80 * qty) + ' tasting notes', rect.left + rect.width / 2 + window.scrollX, rect.top + window.scrollY, 'green');
        confetti({ count: 36, x: (rect.left + rect.width / 2) / window.innerWidth, y: rect.top / window.innerHeight });
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

  /* ------------------------------ Cart page stepper / clear AJAX ------------------------------ */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var act = form.querySelector('[name="action"]');
    if (!act) return;
    var actionVal = act.value;
    var isStepper = form.closest('.stepper');
    if (isStepper) {
      if (actionVal !== 'inc' && actionVal !== 'dec') return;
    } else {
      if (actionVal !== 'clear') return;
    }
    e.preventDefault();
    var fd = new FormData(form);
    fetch(base + 'api/cart.php', {
      method: 'POST', body: fd, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { toast(d.message || 'Could not update. Refreshing…', true); setTimeout(function () { location.reload(); }, 900); return; }
      syncCart(d);
      if (actionVal === 'clear') {
        toast(d.message, false);
        setTimeout(function () { location.reload(); }, 400);
        return;
      }
      var oline = form.closest('.oline');
      if (oline) {
        var out = oline.querySelector('output');
        var lineTotalEl = oline.querySelector('.oline-total');
        if (out && typeof d.qty === 'number') out.textContent = d.qty;
        if (actionVal === 'dec' && d.qty < 1) {
          oline.style.transition = 'opacity .25s, transform .25s';
          oline.style.opacity = '0'; oline.style.transform = 'translateX(20px)';
          setTimeout(function () {
            oline.remove();
            if (!document.querySelector('.oline')) { location.reload(); }
            else { updateCartDocket(); syncCartDocketSubtotal(); }
          }, 260);
        } else {
          var priceEl = oline.querySelector('.unit');
          if (priceEl && lineTotalEl) {
            var unitMatch = (priceEl.textContent || '').match(/[\d,]+/g);
            if (unitMatch) {
              var unit = parseFloat(String(unitMatch[0]).replace(/,/g, ''));
              if (!isNaN(unit)) lineTotalEl.textContent = '₦' + Math.round(unit * (d.qty || 0)).toLocaleString('en-NG');
            }
          }
          updateCartDocket(); syncCartDocketSubtotal();
        }
      }
    }).catch(function () { form.submit(); });
  });

  function updateCartDocket() {
    var lines = document.querySelectorAll('.oline');
    var docket = document.querySelector('.sticky-col .docket');
    if (!docket || !lines.length) return;
  }
  function syncCartDocketSubtotal() {
    var sub = 0;
    document.querySelectorAll('.oline').forEach(function (ol) {
      var t = ol.querySelector('.oline-total');
      if (!t) return;
      var m = (t.textContent || '').match(/[\d,]+/g);
      if (m) sub += parseFloat(String(m[0]).replace(/,/g, ''));
    });
    var docket = document.querySelector('.sticky-col .docket');
    if (!docket) return;
    var rows = docket.querySelectorAll('.docket-row:not(.total)');
    var lineItems = Array.prototype.slice.call(rows);
    lines = document.querySelectorAll('.oline');
    if (lineItems.length === lines.length) {
      var idx = 0;
      lines.forEach(function (ol) {
        var qtyOut = ol.querySelector('output');
        var name = ol.querySelector('h3');
        var totalEl = ol.querySelector('.oline-total');
        if (qtyOut && name && lineItems[idx]) {
          lineItems[idx].querySelector('span:first-child').textContent = (qtyOut.textContent || '0') + ' × ' + (name.textContent || '');
          if (totalEl && lineItems[idx].querySelector('span:last-child')) {
            lineItems[idx].querySelector('span:last-child').textContent = totalEl.textContent;
          }
          idx++;
        }
      });
    }
    var totalRow = docket.querySelector('.docket-row.total span:last-child');
    if (totalRow && sub > 0) totalRow.textContent = '₦' + Math.round(sub).toLocaleString('en-NG');
  }

  /* ------------------------------ Menu: live search + active chip ------------------------------ */
  var search = document.getElementById('menuSearch');
  if (search) {
    var cats = document.querySelectorAll('[data-menu-cat]');
    var empty = document.getElementById('menuEmpty');
    var challenged = new Set();
    var challengeBar = document.querySelector('[data-challenge-bar]');
    var challengeCount = document.querySelector('[data-challenge-count]');
    function checkChallenge() {
      if (!challengeBar) return;
      var uniq = challenged.size;
      if (challengeCount) challengeCount.textContent = uniq;
      if (challengeBar) challengeBar.style.width = Math.min(100, (uniq / 3) * 100) + '%';
      if (uniq >= 3) {
        toast('🎉 Taste Challenge complete! +200 XP bonus added at checkout.', false, 'gold');
        confetti({ count: 100, x: 0.5, y: 0.2, spread: 2.4 });
      }
    }
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
      if (q && Math.random() < 0.2) emojiRain(['🔍', '🌶️', '🍚'], 3);
    });
    // Track categories clicked for "Taste Challenge"
    document.querySelectorAll('[data-menu-cat]').forEach(function (catEl) {
      catEl.addEventListener('click', function (e) {
        var host = e.target.closest('[data-dish], .mrow');
        if (!host) return;
        var id = catEl.id;
        if (!challenged.has(id)) {
          challenged.add(id);
          var rect = e.target.getBoundingClientRect();
          xpPop('New category!', rect.left + rect.width / 2 + window.scrollX, rect.top + window.scrollY, 'gold');
        }
        checkChallenge();
      });
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

  /* ------------------------------ Home: delivery fee check ------------------------------ */
  var zoneSel = document.getElementById('zoneCheck');
  if (zoneSel) {
    zoneSel.addEventListener('change', function () {
      var opt = zoneSel.options[zoneSel.selectedIndex];
      document.getElementById('zoneFee').textContent = opt.getAttribute('data-fee-text') || '';
    });
  }

  /* ------------------------------ Checkout: delivery vs pickup, payment, live total ------------------------------ */
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

  /* ------------------------------ Tracking page auto-refresh ------------------------------ */
  var tr = document.querySelector('[data-autorefresh]');
  if (tr) setTimeout(function () { location.reload(); }, 45000);

  /* ------------------------------ Hero food slider ------------------------------ */
  (function () {
    var track = document.querySelector('[data-hs-track]');
    var slides = track ? Array.from(track.children) : [];
    if (!track || slides.length < 2) return;
    var dotsWrap = document.querySelector('[data-hs-dots]');
    var dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('button')) : [];
    var prev = document.querySelector('[data-hs-prev]');
    var next = document.querySelector('[data-hs-next]');
    var i = 0;
    function go(newI, user) {
      i = (newI + slides.length) % slides.length;
      track.style.transform = 'translateX(' + (-100 * i) + '%)';
      slides.forEach(function (s, k) { s.classList.toggle('on', k === i); });
      dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
      if (user) stopAuto();
    }
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k, true); }); });
    if (prev) prev.addEventListener('click', function () { go(i - 1, true); });
    if (next) next.addEventListener('click', function () { go(i + 1, true); });
    function startAuto() {
      if (prefersReduced) return;
      stopAuto();
      window.__hsTimer = setInterval(function () { go(i + 1); }, 5200);
    }
    function stopAuto() { if (window.__hsTimer) { clearInterval(window.__hsTimer); window.__hsTimer = null; } }
    var slider = track.parentElement;
    if (slider) {
      slider.addEventListener('mouseenter', stopAuto);
      slider.addEventListener('mouseleave', startAuto);
    }
    // keyboard arrows on focus
    if (slider) {
      slider.tabIndex = 0;
      slider.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') go(i - 1, true);
        else if (e.key === 'ArrowRight') go(i + 1, true);
      });
    }
    go(0);
    startAuto();
  })();

  /* ------------------------------ Featured dish carousel ------------------------------ */
  (function () {
    var track = document.querySelector('[data-carousel-track]');
    if (!track) return;
    var left = document.querySelector('[data-carousel-prev]');
    var right = document.querySelector('[data-carousel-next]');
    function scrollBy(delta) { track.scrollBy({ left: delta, behavior: prefersReduced ? 'auto' : 'smooth' }); }
    if (left) left.addEventListener('click', function () { scrollBy(-340); });
    if (right) right.addEventListener('click', function () { scrollBy(340); });
    if (!prefersReduced) {
      // subtle idle drift: pause on hover
      var paused = false;
      track.addEventListener('mouseenter', function () { paused = true; });
      track.addEventListener('mouseleave', function () { paused = false; });
      setInterval(function () { if (!paused) scrollBy(1); }, 90);
    }
  })();

  /* ================================================================
     🔥 CONTAGIOUS INTERACTIONS — tilt, ripple, stagger, tap, steam
     ================================================================ */
  (function () {
    var isCoarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

    /* ---- 1. Grid stagger numberer: assign --g-i and --dish-i for waterfall reveals ---- */
    var containers = document.querySelectorAll(
      '.dish-grid, .badges, .steps, .rc-stats, .gallery, .footer-grid, .rd-stats, .mrows, .chips'
    );
    containers.forEach(function (grp) {
      var kids = grp.children;
      for (var k = 0; k < kids.length; k++) {
        kids[k].style.setProperty('--g-i', String(k));
        kids[k].style.setProperty('--dish-i', String(k));
        if (!kids[k].classList.contains('reveal')) kids[k].classList.add('reveal', 'gs-fast');
      }
    });

    /* ---- 2. 3D mouse tilt on dish cards (desktop / fine pointer only) ---- */
    if (!isCoarse && !prefersReduced && 'onpointermove' in window) {
      var dishEls = document.querySelectorAll('.dish');
      dishEls.forEach(function (dish) {
        var isIn = false;
        dish.addEventListener('pointerenter', function () { isIn = true; dish.classList.add('tilt'); });
        dish.addEventListener('pointerleave', function () {
          isIn = false; dish.classList.remove('tilt');
          dish.style.transform = '';
          dish.style.setProperty('--rx', '0deg');
          dish.style.setProperty('--ry', '0deg');
        });
        dish.addEventListener('pointermove', function (e) {
          if (!isIn) return;
          var r = dish.getBoundingClientRect();
          var px = (e.clientX - r.left) / r.width;  // 0..1
          var py = (e.clientY - r.top) / r.height;  // 0..1
          var ry = (px - 0.5) * 14;  // rotateY ±7 deg
          var rx = (0.5 - py) * 12;  // rotateX ±6 deg
          dish.style.setProperty('--mx', (px * 100) + '%');
          dish.style.setProperty('--my', (py * 100) + '%');
          dish.style.transform = 'perspective(1000px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg) translateY(-6px) scale(1.015)';
        });
      });
    }

    /* ---- 3. Dish tap feedback + steam burst (mobile-first) ---- */
    document.addEventListener('touchstart', function (e) {
      var dish = e.target.closest('.dish');
      if (!dish) return;
      dish.classList.remove('tap');
      void dish.offsetWidth;
      dish.classList.add('tap');
      setTimeout(function () { dish.classList.remove('tap'); }, 400);
      if (Math.random() < 0.55) {
        var photo = dish.querySelector('.dish-photo');
        steamBurst(photo || dish, 2 + ((Math.random() * 2) | 0));
      }
    }, { passive: true });

    /* ---- 4. Click ripple trigger (pointerdown) for buttons/chips ---- */
    function setRippleXY(el, ev) {
      var r = el.getBoundingClientRect();
      var mx = ((ev.clientX - r.left) / r.width) * 100;
      var my = ((ev.clientY - r.top) / r.height) * 100;
      el.style.setProperty('--mx', mx + '%');
      el.style.setProperty('--my', my + '%');
    }
    document.addEventListener('pointerdown', function (e) {
      var el = e.target.closest('.btn, .btn-add, .chip, .stepper button, .sheet-add, .btn-ask, .btn-gold');
      if (!el) return;
      setRippleXY(el, e);
      el.classList.remove('ripple');
      void el.offsetWidth;
      el.classList.add('ripple');
      setTimeout(function () { el.classList.remove('ripple'); }, 650);
    });

    /* ---- 5. Steam on dish photo hover (desktop) ---- */
    if (!isCoarse && !prefersReduced) {
      var steamTimer = {};
      document.addEventListener('mouseover', function (e) {
        var photo = e.target.closest('.dish-photo, .hero-slider, .rewards-card');
        if (!photo) return;
        var id = photo.getAttribute('data-steam-id');
        if (!id) { id = Math.random().toString(36).slice(2); photo.setAttribute('data-steam-id', id); }
        if (steamTimer[id]) return;
        steamBurst(photo, 2);
        steamTimer[id] = setInterval(function () {
          if (document.body.contains(photo)) steamBurst(photo, 1);
          else { clearInterval(steamTimer[id]); delete steamTimer[id]; }
        }, 2200 + Math.random() * 800);
      });
      document.addEventListener('mouseout', function (e) {
        var photo = e.target.closest('.dish-photo, .hero-slider, .rewards-card');
        if (!photo) return;
        var related = e.relatedTarget;
        if (related && photo.contains(related)) return;
        var id = photo.getAttribute('data-steam-id');
        if (id && steamTimer[id]) { clearInterval(steamTimer[id]); delete steamTimer[id]; }
      });
    }

    /* ---- 6. Ambient steam kickoff for hero + rewards (first 5s) ---- */
    if (!prefersReduced) {
      var hs = document.querySelector('.hero-slider');
      var rc = document.querySelector('.rewards-card');
      setTimeout(function () { if (hs) steamBurst(hs, 3); }, 1800);
      setTimeout(function () { if (hs) steamBurst(hs, 2); }, 4200);
      setTimeout(function () { if (rc) steamBurst(rc, 2); }, 3000);
    }
  })();

  /* ------------------------------ Scroll Reveal ------------------------------ */
  (function () {
    var items = document.querySelectorAll('.reveal');
    if (!items.length || !('IntersectionObserver' in window)) {
      items.forEach(function (i) { i.classList.add('on'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('on'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
  })();

  /* ------------------------------ Counter rolls on reward reveal ------------------------------ */
  (function () {
    if (!gameState || !('IntersectionObserver' in window)) return;
    var targets = document.querySelectorAll('[data-roll]');
    if (!targets.length) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        var to = parseInt(el.getAttribute('data-roll') || '0', 10);
        el.classList.add('rolling');
        rollCounter(el, 0, to, 1100);
        setTimeout(function () { el.classList.remove('rolling'); }, 1150);
        io.unobserve(el);
      });
    }, { threshold: 0.35 });
    targets.forEach(function (el) { io.observe(el); });

    // Progress bar animation on reveal
    var bars = document.querySelectorAll('[data-game-progress]');
    if (bars.length && gameState) {
      var ib = new IntersectionObserver(function (ents) {
        ents.forEach(function (en) {
          if (!en.isIntersecting) return;
          setTimeout(function () {
            en.target.style.width = (gameState.level.progress || 0) + '%';
          }, 120);
          ib.unobserve(en.target);
        });
      }, { threshold: 0.3 });
      bars.forEach(function (b) {
        b.style.width = '0%';
        ib.observe(b);
      });
    }
  })();

  /* ------------------------------ Spin wheel ------------------------------ */
  (function () {
    var wheel = document.querySelector('[data-spin-wheel]');
    var btn = document.querySelector('[data-spin-go]');
    var dialog = document.getElementById('spinResult');
    if (!btn) return;
    if (btn && gameState && !gameState.can_spin) {
      var orig = btn.innerHTML || btn.textContent;
      btn.setAttribute('data-orig-label', orig);
      btn.disabled = true;
      btn.textContent = 'Come back tomorrow!';
    }
    function applySpinRes(d) {
      if (!d || !d.state) return;
      applyGameState(d.state);
      // Update counters on home rewards card (if present)
      var pts = document.querySelector('[data-roll="points"]'); if (pts) { pts.setAttribute('data-roll', String(d.state.points)); rollCounter(pts, parseInt(pts.textContent, 10) || 0, d.state.points, 900); }
      var xp = document.querySelector('[data-roll="xp"]'); if (xp) { xp.setAttribute('data-roll', String(d.state.xp)); rollCounter(xp, parseInt(xp.textContent, 10) || 0, d.state.xp, 900); }
      var ord = document.querySelector('[data-roll="orders"]'); if (ord) { ord.setAttribute('data-roll', String(d.state.orders)); }
    }
    function showReward(payload) {
      if (!dialog || typeof dialog.showModal !== 'function') {
        toast(payload.message, false, 'gold');
        return;
      }
      var title = dialog.querySelector('[data-rd-title]');
      var body = dialog.querySelector('[data-rd-body]');
      var emoji = dialog.querySelector('[data-rd-emoji]');
      var hero = dialog.querySelector('.rd-hero');
      if (title) title.textContent = payload.ok ? 'You won!' : 'Come back tomorrow';
      if (body) body.textContent = payload.message;
      if (emoji) emoji.textContent = payload.ok ? (
        payload.reward_id === 'bonus' ? '🚀' :
        payload.reward_id === 'free_drink' ? '🥤' :
        payload.reward_id === 'p300' ? '💰' :
        payload.reward_id === 'p150' ? '🪙' :
        payload.reward_id === 'badge' ? '🎖️' :
        payload.reward_id === 'try_tomorrow' ? '🌙' : '🎉'
      ) : '⏳';
      if (hero) hero.classList.toggle('gold', !!payload.ok);
      dialog.showModal();
      body.classList.add('sheet-open');
      dialog.addEventListener('close', function once() { body.classList.remove('sheet-open'); dialog.removeEventListener('close', once); }, { once: true });
    }
    function spinIt(handlers) {
      if (!wheel) { handlers.done(); return; }
      // Random stop position in degrees; full rotations + segment
      var turns = 5 + Math.random() * 3; // 5-8 full turns
      var deg = turns * 360 + (Math.random() * 360);
      var curr = parseFloat(wheel.getAttribute('data-rot') || '0');
      var target = curr + deg;
      wheel.style.transform = 'rotate(' + target + 'deg)';
      wheel.setAttribute('data-rot', String(target));
      setTimeout(handlers.done, 4700);
    }
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      btn.disabled = true;
      confetti({ count: 60, x: 0.5, y: 0.45, spread: 1.8, palette: ['#F4B63F', '#D9541E', '#2E6B3F', '#fff'] });
      spinIt({
        done: function () {
          postSpin().then(function (res) {
            applySpinRes(res);
            if (res.ok) {
              confetti({ count: 180, x: 0.5, y: 0.1, spread: 2.8 });
              emojiRain(['🎉', '🏆', '⭐', '💰', '👑', '✨'], 22);
              showReward(res);
              toast(res.message, false, 'gold');
            } else {
              showReward(res);
              toast(res.message, true);
            }
            if (!res.state || !res.state.can_spin) {
              var orig = btn.innerHTML || btn.textContent;
              btn.setAttribute('data-orig-label', orig);
              btn.textContent = 'Come back tomorrow!';
            }
          }).catch(function () {
            btn.disabled = false;
            toast('Could not reach the kitchen — try again.', true);
          });
        }
      });
    });
    if (dialog) {
      dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
      var cx = dialog.querySelector('[data-rd-close]');
      if (cx) cx.addEventListener('click', function () { dialog.close(); });
    }
  })();

  /* ------------------------------ Button radial shine ------------------------------ */
  document.addEventListener('pointermove', function (e) {
    var btn = e.target.closest('.btn');
    if (!btn) return;
    var rect = btn.getBoundingClientRect();
    btn.style.setProperty('--mx', ((e.clientX - rect.left) / rect.width * 100) + '%');
    btn.style.setProperty('--my', ((e.clientY - rect.top) / rect.height * 100) + '%');
  });

  /* ------------------------------ Confirmation: order reward celebration ------------------------------ */
  (function () {
    var meta = document.querySelector('[data-order-reward]');
    if (!meta) return;
    var data; try { data = JSON.parse(meta.getAttribute('data-order-reward') || 'null'); } catch (_) { return; }
    if (!data) return;
    setTimeout(function () {
      confetti({ count: 220, x: 0.5, y: 0.0, spread: 3.2 });
      setTimeout(function () { confetti({ count: 140, x: 0.5, y: 0.0, spread: 2.6 }); }, 400);
      setTimeout(function () { confetti({ count: 90,  x: 0.5, y: 0.0, spread: 2.2 }); }, 900);
      emojiRain(['🎉', '🥳', '🍛', '⭐', '👑', '🔥', '✨'], 30);
      toast('+' + (data.points_gained || 0) + ' points earned!', false, 'gold');
      if (data.leveled_up) {
        setTimeout(function () {
          confetti({ count: 240, x: 0.5, y: 0.2, spread: 3.0 });
          toast('🆙 LEVEL UP: ' + (data.level_name || ''), false, 'gold');
        }, 1400);
      }
      if (data.new_badges && data.new_badges.length) {
        setTimeout(function () {
          data.new_badges.forEach(function (b, i) {
            setTimeout(function () {
              toast('🎖️ Badge unlocked: ' + b.emoji + ' ' + b.name, false, 'gold');
              confetti({ count: 80, x: 0.5, y: 0.2, spread: 2.2 });
            }, i * 1500);
          });
        }, 800);
      }
    }, 500);
  })();

  /* ------------------------------ Gentle ambient emoji rain on home ------------------------------ */
  if (body.classList.contains('page-home') && !prefersReduced) {
    var rainTimer;
    function ambientRain() {
      emojiRain(['🍛', '🔥', '⭐', '🥘', '🍗'], 2 + Math.floor(Math.random() * 3));
      rainTimer = setTimeout(ambientRain, 9000 + Math.random() * 8000);
    }
    rainTimer = setTimeout(ambientRain, 6000);
    // Pause when tab hidden
    document.addEventListener('visibilitychange', function () {
      if (document.hidden && rainTimer) { clearTimeout(rainTimer); rainTimer = null; }
      else if (!document.hidden && !rainTimer) { rainTimer = setTimeout(ambientRain, 3000); }
    });
  }
})();
