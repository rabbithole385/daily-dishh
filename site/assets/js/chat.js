/* AI Errands — front-end chat widget.
   - No innerHTML (XSS safety): all text via textContent + createElement.
   - Session history in sessionStorage.
   - Actions: link, scroll, add_to_cart (window.cartAdd), whatsapp.
   - Mobile: visualViewport keeps input above keyboard. */
(function () {
    'use strict';

    var STORAGE_KEY = 'dd_chat_v1';
    var GREETING = {
        text: "Chef’s kiss — welcome to the table! 🍽️ I’m Errands, your errand boy on the inside. Anything you need from our kitchen — it’s granted. Browse dishes, ask what’s hot today, tell me where in Abuja you need delivery, or even build a custom plate. I’ve got you.",
        suggestions: ["What’s good today?", "Show featured dishes", "Wuse delivery trays", "I’m on a budget"]
    };

    var body = document.body;
    var base = body.getAttribute('data-base') || '/';
    var csrf = body.getAttribute('data-csrf') || '';

    var fab = document.getElementById('chatFab');
    var dialog = document.getElementById('chatDialog');
    var chatClose = document.getElementById('chatClose');
    var chatBody = document.getElementById('chatBody');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var sendBtn = document.getElementById('chatSend');
    var suggestionsWrap = document.getElementById('chatSuggestions');
    var badge = document.getElementById('chatFabBadge');

    if (!fab || !dialog || !chatBody || !form || !input) return;

    var history = loadHistory();
    var hasGreeted = history.length > 0;
    var pendingTypingEl = null;
    var sendLock = false;

    // Expose for debugging / external triggers
    window.ddChat = { open: openChat, close: closeChat, send: handleUserSend };

    /* --------------------------- History --------------------------- */

    function loadHistory() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            var arr = JSON.parse(raw);
            if (Array.isArray(arr)) return arr;
        } catch (_) {}
        return [];
    }

    function saveHistory() {
        try {
            // Trim to last ~30 messages to keep storage light
            var trimmed = history.slice(-30);
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
        } catch (_) {}
    }

    function pushHistory(role, content) {
        history.push({ role: role, content: content, ts: Date.now() });
        saveHistory();
    }

    /* --------------------------- Render helpers (TEXT ONLY SAFE) --------------------------- */

    function esc(s) {
        if (s == null) return '';
        return String(s);
    }

    function scrollToBottom() {
        try {
            chatBody.scrollTop = chatBody.scrollHeight;
        } catch (_) {}
    }

    function addBubble(role, text, actions) {
        var wrap = document.createElement('div');
        wrap.className = 'bubble-wrap';

        var bubble = document.createElement('div');
        bubble.className = 'bubble ' + (role === 'user' ? 'user' : 'ai');
        bubble.textContent = esc(text || '');
        wrap.appendChild(bubble);

        if (actions && actions.length) {
            var actionRow = document.createElement('div');
            actionRow.className = 'bubble-actions';
            actions.forEach(function (a) { renderAction(a, actionRow, bubble); });
            wrap.appendChild(actionRow);
        }

        chatBody.appendChild(wrap);
        requestAnimationFrame(scrollToBottom);
        return wrap;
    }

    function renderAction(a, container, originBubble) {
        if (!a || !a.type || !a.label) return;
        var btn;
        var primary = a.primary !== false;
        if (a.type === 'link') {
            btn = document.createElement('a');
            btn.href = a.url && String(a.url).startsWith('/')
                ? base.replace(/\/$/, '') + String(a.url)
                : String(a.url || base + 'menu.php');
            btn.target = '_self';
            btn.rel = 'noopener';
        } else {
            btn = document.createElement('button');
            btn.type = 'button';
        }
        btn.className = 'bubble-action' + (primary ? '' : ' secondary');
        btn.textContent = esc(a.label);

        if (a.type === 'scroll') {
            btn.addEventListener('click', function () {
                var sel = a.selector || '';
                if (!sel) return;
                closeChat();
                setTimeout(function () {
                    var el = document.querySelector(sel);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 60);
            });
        } else if (a.type === 'add_to_cart') {
            btn.addEventListener('click', function () {
                var id = parseInt(a.item_id, 10);
                var qty = parseInt(a.qty, 10) || 1;
                if (!id || id < 1) return;
                btn.disabled = true;
                btn.textContent = 'Adding…';
                Promise.resolve(typeof window.cartAdd === 'function'
                    ? window.cartAdd(id, qty, originBubble)
                    : fallbackCartAdd(id, qty)
                ).then(function () {
                    btn.textContent = 'Added ✓';
                    btn.classList.remove('secondary');
                }).catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Try again';
                });
            });
        } else if (a.type === 'whatsapp') {
            if (!(btn instanceof HTMLAnchorElement)) {
                var wa = body.getAttribute('data-whatsapp') || '';
                var t = encodeURIComponent(String(a.text || 'Hi Daily Dish!'));
                // Convert to anchor so middle-click/open-in-new-tab works too
                var anchor = document.createElement('a');
                anchor.href = wa ? 'https://wa.me/' + String(wa).replace(/\D/g, '') + '?text=' + t
                                : base + 'contact.php';
                anchor.target = '_blank';
                anchor.rel = 'noopener';
                anchor.className = btn.className;
                anchor.textContent = btn.textContent;
                if (container && btn.parentNode === container) container.replaceChild(anchor, btn);
                btn = anchor;
            }
        }
        if (btn && btn.parentNode !== container) container.appendChild(btn);
        else if (btn && !btn.parentNode) container.appendChild(btn);
    }

    function fallbackCartAdd(itemId, qty) {
        var fd = new FormData();
        fd.append('csrf', csrf);
        fd.append('action', 'add');
        fd.append('item_id', String(itemId));
        fd.append('qty', String(qty || 1));
        return fetch(base + 'api/cart.php', {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (typeof window.syncStickyCart === 'function') window.syncStickyCart(d);
            if (typeof window.toast === 'function') window.toast(d.message || 'Added');
            return d;
        });
    }

    function showTyping() {
        if (pendingTypingEl) return pendingTypingEl;
        var wrap = document.createElement('div');
        wrap.className = 'bubble-wrap';
        var bubble = document.createElement('div');
        bubble.className = 'bubble ai';
        var typing = document.createElement('div');
        typing.className = 'typing';
        typing.innerHTML = '<span></span><span></span><span></span>';
        bubble.appendChild(typing);
        wrap.appendChild(bubble);
        chatBody.appendChild(wrap);
        pendingTypingEl = wrap;
        scrollToBottom();
        return wrap;
    }

    function removeTyping() {
        if (pendingTypingEl && pendingTypingEl.parentNode) {
            pendingTypingEl.parentNode.removeChild(pendingTypingEl);
        }
        pendingTypingEl = null;
    }

    function renderSuggestions(list) {
        if (!suggestionsWrap) return;
        suggestionsWrap.innerHTML = '';
        if (!list || !list.length) return;
        list.slice(0, 4).forEach(function (s) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'chip-suggest';
            chip.textContent = esc(s);
            chip.addEventListener('click', function () {
                handleUserSend(s);
            });
            suggestionsWrap.appendChild(chip);
        });
    }

    /* --------------------------- API --------------------------- */

    function callAI(message) {
        showTyping();
        var payload = {
            csrf: csrf,
            message: message,
            history: history.slice(-12).map(function (h) {
                return { role: h.role, content: h.content };
            })
        };
        var bodyText = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        return fetch(base + 'api/ai.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
            body: bodyText
        }).then(function (r) {
            if (!r.ok && r.status !== 429) throw new Error('status ' + r.status);
            return r.json();
        }).then(function (d) {
            return {
                reply: (typeof d.reply === 'string') ? d.reply : 'Sorry chef, I missed that. Say it again?',
                actions: Array.isArray(d.actions) ? d.actions : [],
                suggestions: Array.isArray(d.suggestions) ? d.suggestions : []
            };
        }).catch(function () {
            return {
                reply: "My notepad went missing — try one more time? If I’m still out, tap WhatsApp and the kitchen will sort you out directly.",
                actions: [],
                suggestions: []
            };
        });
    }

    /* --------------------------- Flow --------------------------- */

    function showGreeter() {
        addBubble('ai', GREETING.text, []);
        renderSuggestions(GREETING.suggestions);
        pushHistory('assistant', GREETING.text);
    }

    function restoreHistory() {
        chatBody.innerHTML = '';
        if (history.length === 0) { showGreeter(); return; }
        for (var i = 0; i < history.length; i++) {
            var h = history[i];
            // Actions aren't persisted; re-render plain bubbles
            addBubble(h.role === 'user' ? 'user' : 'ai', h.content, []);
        }
        renderSuggestions(GREETING.suggestions);
    }

    function handleUserSend(text) {
        if (sendLock) return;
        var msg = typeof text === 'string' ? text.trim() : (input.value || '').trim();
        if (!msg) return;
        input.value = '';
        sendLock = true;
        sendBtn.disabled = true;

        addBubble('user', msg, []);
        pushHistory('user', msg);

        callAI(msg).then(function (res) {
            removeTyping();
            addBubble('ai', res.reply, res.actions);
            pushHistory('assistant', res.reply);
            renderSuggestions(res.suggestions);
        }).finally(function () {
            sendLock = false;
            sendBtn.disabled = false;
            setTimeout(function () { try { input.focus(); } catch (_) {} }, 30);
        });
    }

    function openChat() {
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') {
            try { dialog.showModal(); } catch (_) { dialog.setAttribute('open', ''); }
        } else {
            dialog.setAttribute('open', '');
        }
        fab.classList.remove('hello');
        if (badge) badge.hidden = true;
        setTimeout(function () {
            try { input.focus(); } catch (_) {}
        }, 40);
        if (!hasGreeted) {
            hasGreeted = true;
            restoreHistory();
        } else {
            requestAnimationFrame(scrollToBottom);
        }
    }

    function closeChat() {
        if (!dialog) return;
        try { if (dialog.open) dialog.close(); } catch (_) {}
        dialog.removeAttribute('open');
    }

    /* --------------------------- Wiring --------------------------- */

    fab.addEventListener('click', openChat);
    if (chatClose) chatClose.addEventListener('click', closeChat);
    // Backdrop click: <dialog> with showModal reports click target as dialog on backdrop
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) closeChat();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dialog.hasAttribute('open')) { /* let native <dialog> handle */ }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        handleUserSend();
    });

    // Show an unread badge once on load if the user hasn't opened it yet (subtle)
    if (!hasGreeted && badge) {
        setTimeout(function () {
            if (!dialog.hasAttribute('open')) badge.hidden = false;
        }, 4500);
    }

    // Mobile: keep input above soft keyboard
    if (window.visualViewport && typeof window.visualViewport.addEventListener === 'function') {
        var vv = window.visualViewport;
        function onViewport() {
            if (!dialog.hasAttribute('open')) return;
            // Nudge dialog bottom so input isn't hidden
            var offset = Math.max(0, window.innerHeight - (vv.height + vv.offsetTop));
            dialog.style.bottom = offset ? offset + 'px' : '';
            scrollToBottom();
        }
        vv.addEventListener('resize', onViewport);
        vv.addEventListener('scroll', onViewport);
    }

    // Re-apply sticky cart state on first paint (chat loads defer, after app.js)
    try { if (window.syncStickyCart) window.syncStickyCart(); } catch (_) {}

    // Prime the chat body with greeter or restored content the first time open is
    // clicked (above). Pre-render greeter only after a short idle delay if no history.
    // Else nothing until openChat fires.
})();
