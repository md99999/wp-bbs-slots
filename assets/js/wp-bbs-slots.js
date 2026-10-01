/*
 * WP BBS Slots: spins the reels on the Play page and keeps the progressive jackpot ticking.
 *
 * The server decides every result. This script sends the wager, spins all three reels while it
 * waits, stops the first reel after three seconds and the others a second apart, then shows
 * what was won. Without JavaScript the Pull button still works as an ordinary form.
 */
(function () {
  'use strict';

  var cfg = window.WPBBS || {};
  var symbols = cfg.symbols || {};
  var keys = Object.keys(symbols);
  var fmt = function (n) {
    try { return new Intl.NumberFormat().format(n); } catch (e) { return String(n); }
  };
  var wait = function (ms) {
    return new Promise(function (resolve) { setTimeout(resolve, Math.max(0, ms)); });
  };

  /** A reel symbol, built with textContent so nothing from the network is parsed as HTML. */
  function symbolNode(key) {
    var s = symbols[key] || { label: key, icon: key };
    var outer = document.createElement('span');
    outer.className = 'wpbbs-sym wpbbs-sym-' + key;
    outer.setAttribute('role', 'img');
    outer.setAttribute('aria-label', s.label);
    var icon = document.createElement('span');
    icon.className = 'wpbbs-sym-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = s.icon;
    var label = document.createElement('span');
    label.className = 'wpbbs-sym-label';
    label.setAttribute('aria-hidden', 'true');
    label.textContent = s.label;
    outer.appendChild(icon);
    outer.appendChild(label);
    return outer;
  }

  function setAll(name, text) {
    document.querySelectorAll('[data-wpbbs="' + name + '"]').forEach(function (el) { el.textContent = text; });
  }

  /** Refreshes the progressive on any page that shows it. */
  function startTicker() {
    if (!cfg.ajaxUrl || !document.querySelector('[data-wpbbs="jackpot"]')) return;
    setInterval(function () {
      if (document.hidden || document.querySelector('.wpbbs-machine.wpbbs-busy')) return;
      fetch(cfg.ajaxUrl + '?action=wpbbs_jackpot', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (res && res.success) setAll('jackpot', fmt(res.data.jackpot)); })
        .catch(function () {});
    }, 30000);
  }

  function Machine(root) {
    this.root = root;
    this.form = root.querySelector('[data-wpbbs="form"]');
    this.bet = root.querySelector('[data-wpbbs="bet"]');
    this.pull = root.querySelector('[data-wpbbs="pull"]');
    this.status = root.querySelector('[data-wpbbs="status"]');
    this.extra = root.querySelector('[data-wpbbs="extra"]');
    this.reels = Array.prototype.slice.call(root.querySelectorAll('[data-wpbbs-reel]'));
    this.timers = [];
    this.busy = false;
    var self = this;

    this.form.addEventListener('submit', function (e) {
      e.preventDefault();
      self.spin();
    });
    document.addEventListener('keydown', function (e) {
      if (e.repeat || e.ctrlKey || e.altKey || e.metaKey) return;
      if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
      var t = e.target;
      var tag = t && t.tagName ? t.tagName.toLowerCase() : '';
      // Leave typing alone, and let the space bar open the wager list when it has focus.
      if (tag === 'input' || tag === 'textarea' || (t && t.isContentEditable)) return;
      if (tag === 'select' && e.key !== 'Enter') return;
      if ((tag === 'button' || tag === 'a' || tag === 'summary') && t !== self.pull) return;
      e.preventDefault();
      self.spin();
    });
  }

  Machine.prototype.startReel = function (i) {
    var reel = this.reels[i];
    reel.classList.add('wpbbs-spinning');
    reel.classList.remove('wpbbs-win');
    this.timers[i] = setInterval(function () {
      reel.replaceChildren(symbolNode(keys[Math.floor(Math.random() * keys.length)]));
    }, 70);
  };

  Machine.prototype.stopReel = function (i, key) {
    clearInterval(this.timers[i]);
    var reel = this.reels[i];
    reel.classList.remove('wpbbs-spinning');
    if (key) reel.replaceChildren(symbolNode(key));
    reel.classList.add('wpbbs-stopped');
    setTimeout(function () { reel.classList.remove('wpbbs-stopped'); }, 300);
  };

  Machine.prototype.setBusy = function (busy) {
    this.busy = busy;
    this.root.classList.toggle('wpbbs-busy', busy);
    this.pull.disabled = busy;
    this.bet.disabled = busy;
  };

  /** Updates the score, spins and wager list after a spin. */
  Machine.prototype.update = function (d) {
    if (typeof d.bankroll === 'number') setAll('bankroll', fmt(d.bankroll));
    if (typeof d.spins_left === 'number') setAll('spins', String(d.spins_left));
    if (typeof d.jackpot === 'number') setAll('jackpot', fmt(d.jackpot));

    var notes = [];
    if (typeof d.bankroll === 'number') {
      var chosen = parseInt(this.bet.value, 10);
      Array.prototype.forEach.call(this.bet.options, function (o) {
        o.disabled = parseInt(o.value, 10) > d.bankroll;
      });
      if (chosen > d.bankroll && d.next_bet) {
        this.bet.value = String(d.next_bet);
        notes.push('Your bankroll no longer covers a ' + fmt(chosen) + ' wager, so it has been lowered to ' + fmt(d.next_bet) + '.');
      }
    }
    var out = typeof d.bankroll === 'number' && d.bankroll < Math.min.apply(null, cfg.bets || [100]);
    var done = typeof d.spins_left === 'number' && d.spins_left <= 0;
    if (done) notes.push('No spins left today. Your spins come back tomorrow.');
    else if (out) notes.push('You are out of credits until tomorrow\'s top-up.');
    this.canSpin = !done && !out;
    return notes;
  };

  Machine.prototype.showExtra = function (lines) {
    this.extra.replaceChildren();
    var self = this;
    lines.forEach(function (line) {
      var p = document.createElement('p');
      p.textContent = line;
      self.extra.appendChild(p);
    });
  };

  Machine.prototype.spin = function () {
    if (this.busy || this.pull.disabled) return;
    var self = this;
    var bet = this.bet.value;
    var started = Date.now();
    this.setBusy(true);
    this.status.className = 'wpbbs-result';
    this.status.textContent = 'Spinning...';
    this.showExtra([]);
    for (var i = 0; i < this.reels.length; i++) this.startReel(i);

    var body = new FormData();
    body.append('action', 'wpbbs_spin');
    body.append('nonce', cfg.nonce || '');
    body.append('bet', bet);

    fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          var err = (res && res.data) || {};
          for (var j = 0; j < self.reels.length; j++) self.stopReel(j, null);
          self.status.className = 'wpbbs-result wpbbs-result-error';
          self.status.textContent = err.message || 'That spin did not go through. Please try again.';
          self.showExtra(self.update(err));
          return;
        }
        var d = res.data;
        // First reel at three seconds, then one a second.
        return wait(3000 - (Date.now() - started))
          .then(function () { self.stopReel(0, d.reels[0]); return wait(1000); })
          .then(function () { self.stopReel(1, d.reels[1]); return wait(1000); })
          .then(function () {
            self.stopReel(2, d.reels[2]);
            if (d.win > 0) self.reels.forEach(function (r) { r.classList.add('wpbbs-win'); });
            self.status.className = 'wpbbs-result ' + (d.kind === 'jackpot' ? 'wpbbs-result-jackpot' : (d.win > 0 ? 'wpbbs-result-win' : (d.kind === 'free' ? 'wpbbs-result-free' : '')));
            self.status.textContent = d.message;
            var lines = (d.news || []).slice();
            if (d.bailout) lines.push(d.bailout);
            self.showExtra(lines.concat(self.update(d)));
          });
      })
      .catch(function () {
        for (var k = 0; k < self.reels.length; k++) self.stopReel(k, null);
        self.status.className = 'wpbbs-result wpbbs-result-error';
        self.status.textContent = 'The machine could not be reached. Check your connection and try again.';
        self.canSpin = true;
      })
      .then(function () {
        self.setBusy(false);
        self.pull.disabled = self.canSpin === false;
        if (!self.pull.disabled) self.pull.focus({ preventScroll: true });
      });
  };

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-wpbbs-machine]');
    if (root && cfg.nonce && root.querySelector('[data-wpbbs="form"]')) new Machine(root);
    startTicker();
  });
})();
