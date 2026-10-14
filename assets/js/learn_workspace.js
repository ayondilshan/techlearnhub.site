/* Code Lab: task workspace
 * - CodeMirror editor (falls back to a plain textarea if the CDN is blocked)
 * - HTML/CSS: live preview + DOM checks in a hidden sandboxed frame
 * - Python: Pyodide in a Web Worker (input via a "Program input" box, 8 s time limit)
 * - PHP: static pattern checks (PHP cannot run in the browser)
 */
(function () {
  'use strict';

  var T = window.LP_TASK;
  if (!T) return;

  var lang = T.language;
  var isWeb = lang === 'html' || lang === 'css';
  var isPy = lang === 'python';

  function $(id) { return document.getElementById(id); }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  /* =====================================================
     Editor
     ===================================================== */
  var ta = $('lpCode');
  var cm = null;
  var modeMap = { html: 'htmlmixed', css: 'htmlmixed', php: 'application/x-httpd-php', python: 'python' };

  var draftKey = 'lp_draft_' + T.taskId;
  function readDraft() { try { return localStorage.getItem(draftKey); } catch (e) { return null; } }
  function writeDraft(v) { try { localStorage.setItem(draftKey, v); } catch (e) {} }
  function clearDraft() { try { localStorage.removeItem(draftKey); } catch (e) {} }

  if (window.CodeMirror) {
    cm = CodeMirror.fromTextArea(ta, {
      mode: modeMap[lang] || 'htmlmixed',
      theme: 'material-darker',
      lineNumbers: true,
      indentUnit: 4,
      tabSize: 4,
      indentWithTabs: false,
      autoCloseBrackets: true,
      matchBrackets: true,
      autoCloseTags: lang !== 'python',
      extraKeys: {
        Tab: function (c) {
          if (c.somethingSelected()) c.indentSelected('add');
          else c.replaceSelection('    ', 'end');
        },
        'Shift-Tab': function (c) { c.indentSelected('subtract'); }
      }
    });
  } else {
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        var s = ta.selectionStart;
        ta.value = ta.value.slice(0, s) + '    ' + ta.value.slice(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = s + 4;
      }
    });
    ta.classList.add('lp-plain-editor');
  }

  function getCode() { return cm ? cm.getValue() : ta.value; }
  function setCode(v) { if (cm) cm.setValue(v); else ta.value = v; }
  function onChange(fn) { if (cm) cm.on('change', fn); else ta.addEventListener('input', fn); }

  // start from: local draft > saved code > starter code
  var draft = readDraft();
  if (draft !== null && draft !== getCode()) setCode(draft);
  else if (T.saved != null && T.saved !== getCode()) setCode(T.saved);

  var draftTimer = null;
  onChange(function () {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(function () { writeDraft(getCode()); }, 400);
  });

  /* =====================================================
     Requirements list
     ===================================================== */
  var checklist = $('lpChecklist');
  var resultBox = $('lpResult');

  function shortText(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

  function labelFor(c) {
    if (c.message) return c.message;
    switch (c.type) {
      case 'code_contains': return 'Your code includes "' + shortText(c.value, 40) + '"';
      case 'code_regex': return 'Your code follows the required pattern';
      case 'dom_exists': return 'The page has a ' + c.selector + ' element';
      case 'dom_count_min': return 'At least ' + c.value + ' × ' + c.selector;
      case 'dom_text_contains': return c.value ? c.selector + ' contains "' + shortText(c.value, 40) + '"' : c.selector + ' has some text';
      case 'dom_style': return c.selector + ' has ' + c.extra + ': ' + c.value;
      case 'output_equals':
      case 'output_contains': return c.extra ? 'Correct output when the input is "' + shortText(c.extra.replace(/\n/g, ', '), 30) + '"' : 'Correct output';
      default: return 'Requirement';
    }
  }

  var rows = [];
  (T.checks || []).forEach(function (c) {
    var li = el('li', 'lp-check is-pending');
    li.appendChild(el('span', 'lp-dot'));
    var txt = el('span', 'lp-check-text', labelFor(c));
    li.appendChild(txt);
    checklist.appendChild(li);
    rows.push({ li: li, txt: txt, detail: null });
  });
  if (!rows.length) {
    var none = el('li', 'lp-check', 'This task has no automatic checks yet.');
    checklist.appendChild(none);
  }

  function paint(i, r) {
    var row = rows[i];
    if (!row) return;
    row.li.className = 'lp-check ' + (r.ok ? 'is-pass' : 'is-fail');
    if (row.detail) { row.detail.remove(); row.detail = null; }
    if (!r.ok && r.detail) {
      row.detail = el('small', 'lp-check-detail', r.detail);
      row.txt.appendChild(row.detail);
    }
  }

  function resetChecklist() {
    rows.forEach(function (row) {
      row.li.className = 'lp-check is-pending';
      if (row.detail) { row.detail.remove(); row.detail = null; }
    });
  }

  function showResult(kind, title, body, actions) {
    resultBox.className = 'lp-result is-' + kind;
    resultBox.textContent = '';
    resultBox.appendChild(el('strong', null, title));
    if (body) resultBox.appendChild(el('p', null, body));
    if (actions && actions.length) {
      var bar = el('div', 'lp-result-actions');
      actions.forEach(function (a) { bar.appendChild(a); });
      resultBox.appendChild(bar);
    }
  }

  function linkBtn(text, href, solid) {
    var a = el('a', 'lp-btn' + (solid ? ' lp-btn-solid' : ''), text);
    a.href = href;
    return a;
  }

  /* =====================================================
     Server calls
     ===================================================== */
  function api(body) {
    return fetch(T.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

  /* =====================================================
     HTML / CSS: live preview + hidden checker frame
     ===================================================== */
  var preview = $('lpPreview');
  var previewTimer = null;
  function renderPreview() { if (preview) preview.srcdoc = getCode(); }

  if (isWeb) {
    renderPreview();
    onChange(function () {
      clearTimeout(previewTimer);
      previewTimer = setTimeout(renderPreview, 450);
    });
  }

  // A fresh, script-free, same-origin frame so we can read the DOM safely.
  function loadChecker(code) {
    return new Promise(function (resolve) {
      var host = $('lpCheckerHost');
      var f = document.createElement('iframe');
      f.setAttribute('sandbox', 'allow-same-origin');
      f.width = 900; f.height = 700;
      var settled = false;
      function done() { if (!settled) { settled = true; resolve(f); } }
      f.addEventListener('load', done);
      setTimeout(done, 3000);
      host.appendChild(f);
      f.srcdoc = code;
    });
  }

  function normVal(s) { return String(s == null ? '' : s).toLowerCase().replace(/\s+/g, ''); }

  function normColorLike(doc, win, prop, value) {
    try {
      var probe = doc.createElement('span');
      probe.style.setProperty(prop, value);
      doc.body.appendChild(probe);
      var v = win.getComputedStyle(probe).getPropertyValue(prop);
      probe.remove();
      return v || value;
    } catch (e) { return value; }
  }

  function domCheck(c, frame) {
    var doc = frame && frame.contentDocument;
    var win = frame && frame.contentWindow;
    if (!doc || !doc.body) return { ok: false, detail: 'The page could not be read.' };

    var sel = c.selector, found = null, count = 0;
    try {
      found = sel ? doc.querySelector(sel) : null;
      count = sel ? doc.querySelectorAll(sel).length : 0;
    } catch (e) { return { ok: false, detail: 'Invalid selector in this check.' }; }

    switch (c.type) {
      case 'dom_exists':
        return { ok: !!found, detail: 'Not found: ' + sel };
      case 'dom_count_min':
        var need = parseInt(c.value, 10) || 1;
        return { ok: count >= need, detail: 'Found ' + count + ', need ' + need };
      case 'dom_text_contains':
        if (!found) return { ok: false, detail: 'Not found: ' + sel };
        var text = (found.textContent || '').trim().toLowerCase();
        var want = String(c.value || '').trim().toLowerCase();
        return { ok: want ? text.indexOf(want) !== -1 : text.length > 0, detail: want ? 'Text does not include "' + c.value + '"' : 'It is empty' };
      case 'dom_style':
        if (!found) return { ok: false, detail: 'Not found: ' + sel };
        var prop = String(c.extra || '').trim();
        if (!prop) return { ok: false, detail: 'This check has no CSS property.' };
        var actual = win.getComputedStyle(found).getPropertyValue(prop);
        var expected = /color/i.test(prop) ? normColorLike(doc, win, prop, c.value) : c.value;
        return { ok: normVal(actual) === normVal(expected), detail: 'Currently ' + (actual || 'not set') };
    }
    return { ok: false, detail: 'Unknown check.' };
  }

  /* =====================================================
     Python (Pyodide inside a Web Worker)
     ===================================================== */
  var PYODIDE_BASE = 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/';

  // Runs inside the worker (serialised with toString, so keep it self-contained).
  function workerMain() {
    importScripts('https://cdn.jsdelivr.net/pyodide/v0.26.4/full/pyodide.js');
    var ready = loadPyodide({ indexURL: 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/' });
    ready.then(function () { postMessage({ type: 'ready' }); },
               function (e) { postMessage({ type: 'fatal', error: String(e && e.message || e) }); });

    var SETUP = [
      'import builtins',
      'if not hasattr(builtins, "_lp_orig_input"):',
      '    builtins._lp_orig_input = builtins.input',
      'def _lp_make(hide):',
      '    def _input(prompt=""):',
      '        if not hide:',
      '            print(prompt, end="")',
      '        v = builtins._lp_orig_input()',
      '        if not hide:',
      '            print(v)',
      '        return v',
      '    return _input',
      'builtins.input = _lp_make(__HIDE__)'
    ].join('\n');

    self.onmessage = async function (e) {
      var d = e.data;
      var py = await ready;
      var out = '';
      var raw = String(d.stdin || '').replace(/\r/g, '').replace(/\n$/, '');
      var lines = raw === '' ? [] : raw.split('\n');
      var i = 0;
      py.setStdin({ stdin: function () { return i < lines.length ? lines[i++] : undefined; } });
      py.setStdout({ batched: function (s) { out += s + '\n'; } });
      py.setStderr({ batched: function (s) { out += s + '\n'; } });
      try {
        py.runPython(SETUP.replace('__HIDE__', d.hidePrompts ? 'True' : 'False'));
        var g = py.globals.get('dict')();
        try {
          await py.runPythonAsync(d.code, { globals: g });
        } finally {
          g.destroy();
        }
        py.runPython('import sys; sys.stdout.flush()');
        postMessage({ type: 'result', id: d.id, ok: true, output: out });
      } catch (err) {
        postMessage({ type: 'result', id: d.id, ok: false, output: out, error: String(err && err.message || err) });
      }
    };
  }

  var worker = null, engineReady = null, pending = {}, seq = 0;

  function setEngine(text) { var n = $('lpEngine'); if (n) n.textContent = text; }

  function startWorker() {
    setEngine('Loading Python… (first time takes a few seconds)');
    var src = '(' + workerMain.toString() + ')()';
    var blobUrl = URL.createObjectURL(new Blob([src], { type: 'application/javascript' }));
    var w = new Worker(blobUrl);
    var resolveReady, rejectReady;
    engineReady = new Promise(function (res, rej) { resolveReady = res; rejectReady = rej; });
    engineReady.catch(function () {});   // avoid unhandled rejection noise
    w.onmessage = function (e) {
      var m = e.data;
      if (m.type === 'ready') { setEngine('Python ready'); resolveReady(); }
      else if (m.type === 'fatal') { setEngine('Python failed to load'); rejectReady(new Error(m.error)); }
      else if (m.type === 'result' && pending[m.id]) {
        var p = pending[m.id];
        delete pending[m.id];
        clearTimeout(p.timer);
        p.resolve(m);
      }
    };
    w.onerror = function () { setEngine('Python failed to load'); rejectReady(new Error('The Python engine could not start.')); };
    worker = w;
  }

  function friendlyPyError(msg) {
    var text = String(msg || '');
    if (/EOF when reading a line/i.test(text)) {
      return 'The program asked for input but there was none left. Add the values in "Program input", one per line.';
    }
    var lines = text.trim().split('\n');
    return lines.slice(-6).join('\n');
  }

  function runPython(code, stdin, hidePrompts) {
    if (!worker) startWorker();
    return engineReady.then(function () {
      return new Promise(function (resolve) {
        var id = ++seq;
        var timer = setTimeout(function () {
          delete pending[id];
          try { worker.terminate(); } catch (e) {}
          worker = null;
          resolve({ ok: false, output: '', error: 'Time limit reached (8 seconds). Check for a loop that never ends.' });
        }, 8000);
        pending[id] = { resolve: resolve, timer: timer };
        worker.postMessage({ id: id, code: code, stdin: stdin || '', hidePrompts: !!hidePrompts });
      });
    }).then(function (m) {
      return { ok: m.ok, output: m.output || '', error: m.ok ? '' : friendlyPyError(m.error) };
    }, function (err) {
      return { ok: false, output: '', error: 'Python could not start. Check your internet connection and reload the page. (' + err.message + ')' };
    });
  }

  function normOut(s) {
    return String(s || '').replace(/\r/g, '').split('\n').map(function (l) { return l.replace(/\s+$/, ''); }).join('\n').trim();
  }

  if (isPy) startWorker();

  /* Manual run (Python) */
  var consoleEl = $('lpConsole');
  var runBtn = $('lpRun');
  function runManual() {
    if (!isPy || !runBtn) return;
    runBtn.disabled = true;
    consoleEl.textContent = 'Running…';
    var stdin = $('lpStdin') ? $('lpStdin').value : '';
    runPython(getCode(), stdin, false).then(function (r) {
      consoleEl.textContent = '';
      if (r.output) consoleEl.appendChild(document.createTextNode(r.output));
      if (!r.ok) consoleEl.appendChild(el('span', 'lp-err', (r.output ? '\n' : '') + r.error));
      if (r.ok && !r.output) consoleEl.textContent = '(the program finished without printing anything)';
      runBtn.disabled = false;
    });
  }
  if (runBtn) runBtn.addEventListener('click', runManual);

  /* =====================================================
     Check answer
     ===================================================== */
  var checkBtn = $('lpCheck');
  var busy = false;

  function evalOne(c, code, frame, cache) {
    switch (c.type) {
      case 'code_contains':
        return Promise.resolve({ ok: code.toLowerCase().indexOf(String(c.value).toLowerCase()) !== -1, detail: 'Not found in your code yet.' });
      case 'code_regex':
        try { return Promise.resolve({ ok: new RegExp(c.value, 'i').test(code), detail: 'Your code does not match yet.' }); }
        catch (e) { return Promise.resolve({ ok: false, detail: 'This check has an invalid pattern.' }); }
      case 'dom_exists':
      case 'dom_count_min':
      case 'dom_text_contains':
      case 'dom_style':
        return Promise.resolve(isWeb ? domCheck(c, frame) : { ok: false, detail: 'Only available for HTML/CSS tasks.' });
      case 'output_equals':
      case 'output_contains':
        if (!isPy) return Promise.resolve({ ok: false, detail: 'Only available for Python tasks.' });
        var key = c.extra || '';
        if (!cache[key]) cache[key] = runPython(code, key, true);
        return cache[key].then(function (r) {
          if (!r.ok) return { ok: false, detail: r.error };
          var got = normOut(r.output), want = normOut(c.value);
          var ok = c.type === 'output_equals' ? got === want : got.indexOf(want) !== -1;
          return { ok: ok, detail: got ? 'Your program printed: ' + shortText(got.replace(/\n/g, ' / '), 80) : 'Your program printed nothing.' };
        });
    }
    return Promise.resolve({ ok: false, detail: 'Unknown check type.' });
  }

  function checkAnswer() {
    if (busy) return;
    busy = true;
    checkBtn.disabled = true;
    checkBtn.textContent = 'Checking…';
    resetChecklist();
    showResult('info', 'Checking your code…', isPy ? 'Python runs a few tests. The first run can take a few seconds.' : '');

    var code = getCode();
    writeDraft(code);

    var needsDom = isWeb && (T.checks || []).some(function (c) { return c.type.indexOf('dom_') === 0; });
    var framePromise = needsDom ? loadChecker(code) : Promise.resolve(null);
    var cache = {};
    var results = [];

    framePromise.then(function (frame) {
      // run sequentially so the list fills in top to bottom
      var chain = Promise.resolve();
      (T.checks || []).forEach(function (c, i) {
        chain = chain.then(function () {
          return evalOne(c, code, frame, cache).then(function (r) { results[i] = r; paint(i, r); });
        });
      });
      return chain.then(function () { if (frame) frame.remove(); });
    }).then(function () {
      var total = results.length;
      var passed = results.filter(function (r) { return r && r.ok; }).length;
      if (total && passed === total) return onPass(code);
      return onFail(code, passed, total);
    }).catch(function (e) {
      showResult('bad', 'Something went wrong', String(e && e.message || e));
    }).then(function () {
      busy = false;
      checkBtn.disabled = false;
      checkBtn.textContent = 'Check answer';
    });
  }

  function nextActions() {
    var a = [];
    if (T.nextUrl) a.push(linkBtn('Next task', T.nextUrl, true));
    a.push(linkBtn('Back to path', T.backUrl, !T.nextUrl));
    return a;
  }

  /* =====================================================
     Congratulations popup
     ===================================================== */
  function showCongrats(d) {
    var host = document.querySelector('.lp') || document.body;
    var old = host.querySelector('.lp-modal');
    if (old) old.remove();

    var again = !!d.already_done;
    var overlay = el('div', 'lp-modal');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'lpModalTitle');
    var card = el('div', 'lp-modal-card');

    if (!again && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
      var box = el('div', 'lp-confetti');
      var colors = ['#3b82f6', '#7c3aed', '#22c55e', '#f59e0b', '#ec4899'];
      for (var i = 0; i < 30; i++) {
        var piece = document.createElement('span');
        piece.style.left = (Math.random() * 100) + '%';
        piece.style.background = colors[i % colors.length];
        piece.style.animationDelay = (Math.random() * 0.6) + 's';
        piece.style.animationDuration = (1.8 + Math.random() * 1.4) + 's';
        box.appendChild(piece);
      }
      card.appendChild(box);
    }

    card.appendChild(el('div', 'lp-modal-emoji', again ? '👍' : '🎉'));
    var h = el('h2', null, again ? 'Nice work!' : 'Congratulations!');
    h.id = 'lpModalTitle';
    card.appendChild(h);
    card.appendChild(el('p', null, again ? 'You passed this task again.' : 'You completed the task.'));

    if (d.preview) {
      card.appendChild(el('p', 'lp-modal-sub', 'Preview mode: nothing is saved for admins.'));
    } else {
      if (!again && d.points_awarded) card.appendChild(el('div', 'lp-modal-points', '+' + d.points_awarded + ' points'));
      if (d.total_points != null) card.appendChild(el('p', 'lp-modal-sub', 'You now have ' + d.total_points + ' points in total.'));
      if (d.module_done) card.appendChild(el('p', 'lp-modal-sub', 'You finished every task in this module!'));
    }

    var actions = el('div', 'lp-modal-actions');
    nextActions().forEach(function (a) { actions.appendChild(a); });
    var keep = el('button', 'lp-btn lp-btn-ghost', 'Keep editing');
    keep.type = 'button';
    actions.appendChild(keep);
    card.appendChild(actions);
    overlay.appendChild(card);
    host.appendChild(overlay);

    function close() {
      overlay.remove();
      document.removeEventListener('keydown', onKey);
    }
    function onKey(e) { if (e.key === 'Escape') close(); }
    keep.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', onKey);
    var first = actions.querySelector('a, button');
    if (first) first.focus();
  }

  function onPass(code) {
    if (T.preview) {
      showResult('ok', 'All requirements passed', 'Preview mode: progress is not saved for admins.', nextActions());
      showCongrats({ preview: true });
      return;
    }
    return api({ action: 'complete', task_id: T.taskId, code: code }).then(function (d) {
      if (!d.success) {
        showResult('bad', 'All requirements passed, but saving failed', d.error || 'Please check your connection and press Check answer again.');
        return;
      }
      clearDraft();
      var title = d.already_done ? 'Passed again' : 'Task complete! +' + d.points_awarded + ' points';
      var body = d.already_done ? 'You already had the points for this task.' : 'You now have ' + d.total_points + ' points in total.';
      if (d.module_done) body += ' You finished every task in this module.';
      showResult('ok', title, body, nextActions());
      showCongrats(d);
    }).catch(function () {
      showResult('bad', 'All requirements passed, but saving failed', 'Please check your connection and press Check answer again.');
    });
  }

  function onFail(code, passed, total) {
    showResult('bad', passed + ' of ' + total + ' requirements passed',
      'Look at the items marked with a cross on the left. Each one says what is still missing.');
    if (!T.preview) api({ action: 'save_code', task_id: T.taskId, code: code, attempt: true }).catch(function () {});
  }

  checkBtn.addEventListener('click', checkAnswer);

  /* Reset */
  $('lpReset').addEventListener('click', function () {
    if (!confirm('Reset the editor to the starting code? Your current code will be replaced.')) return;
    setCode(T.starter || '');
    clearDraft();
    resetChecklist();
    resultBox.className = 'lp-result';
    resultBox.textContent = '';
  });

  /* Ctrl/Cmd + Enter */
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      if (isPy && runBtn) runManual(); else checkAnswer();
    }
  });
})();
