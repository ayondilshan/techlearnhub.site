/* Code Lab: learning path page (connector lines, classic view toggle, save module) */
(function () {
  'use strict';

  var root = document.querySelector('.lp');
  if (!root) return;

  /* ---------- connector lines between tiles ---------- */
  var wrap = document.getElementById('lpPathWrap');
  var svg = document.getElementById('lpLines');
  var path = document.getElementById('lpPath');

  function drawLines() {
    if (!wrap || !svg || !path) return;
    if (root.getAttribute('data-view') === 'classic') return;

    var wr = wrap.getBoundingClientRect();
    if (!wr.width) return;
    svg.setAttribute('width', wr.width);
    svg.setAttribute('height', wr.height);
    svg.setAttribute('viewBox', '0 0 ' + wr.width + ' ' + wr.height);

    var nodes = Array.prototype.slice.call(path.querySelectorAll('.lp-node'));
    var centers = nodes.map(function (n) {
      var r = n.querySelector('.lp-tile').getBoundingClientRect();
      return { x: r.left + r.width / 2 - wr.left, y: r.top + r.height / 2 - wr.top };
    });

    var out = '';
    for (var i = 0; i < nodes.length - 1; i++) {
      var a = centers[i], b = centers[i + 1];
      var lit = nodes[i].getAttribute('data-state') === 'done';
      out += '<line class="' + (lit ? 'lp-line-on' : 'lp-line-off') + '" x1="' + a.x.toFixed(1) +
             '" y1="' + a.y.toFixed(1) + '" x2="' + b.x.toFixed(1) + '" y2="' + b.y.toFixed(1) + '"/>';
    }
    svg.innerHTML = out;
  }

  var raf = null;
  function scheduleDraw() {
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(drawLines);
  }

  drawLines();
  window.addEventListener('resize', scheduleDraw);
  window.addEventListener('load', scheduleDraw);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(scheduleDraw);
  if (window.ResizeObserver && wrap) new ResizeObserver(scheduleDraw).observe(wrap);

  /* ---------- classic view toggle ---------- */
  var toggle = document.getElementById('lpClassic');
  function setView(v) {
    root.setAttribute('data-view', v);
    if (toggle) toggle.checked = (v === 'classic');
    if (v === 'path') scheduleDraw();
  }
  if (toggle) {
    var saved = null;
    try { saved = localStorage.getItem('lp_view'); } catch (e) {}
    setView(saved === 'classic' ? 'classic' : 'path');
    toggle.addEventListener('change', function () {
      var v = toggle.checked ? 'classic' : 'path';
      setView(v);
      try { localStorage.setItem('lp_view', v); } catch (e) {}
    });
  }

  /* ---------- save / unsave module ---------- */
  var saveBtn = document.querySelector('.lp-save');
  if (saveBtn && window.LP_PAGE) {
    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true;
      fetch(window.LP_PAGE.api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle_save', module_id: Number(saveBtn.getAttribute('data-module')) })
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.success) throw new Error(d.error || 'Could not save');
          saveBtn.setAttribute('aria-pressed', d.saved ? 'true' : 'false');
          saveBtn.querySelector('.lp-save-icon').textContent = d.saved ? '★' : '☆';
          saveBtn.querySelector('.lp-save-text').textContent = d.saved ? 'Saved' : 'Save';
        })
        .catch(function (e) { alert(e.message); })
        .then(function () { saveBtn.disabled = false; });
    });
  }
})();