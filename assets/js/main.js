// ============ Nav user dropdown ============
document.addEventListener('click', function(e) {
  const menu = document.getElementById('navUserMenu');
  const btn = document.querySelector('.nav-user-btn');
  if (!menu || !btn) return;
  if (!menu.contains(e.target) && !btn.contains(e.target)) {
    menu.classList.remove('open');
  }
});

// ============ Newsletter subscribe ============
async function subscribeNewsletter() {
  const input = document.getElementById('subscribeEmail');
  const status = document.getElementById('subscribeStatus');
  const email = input.value.trim();
  if (!email) { status.style.color = '#f87171'; status.innerText = 'Please enter your email.'; return; }

  status.style.color = '#8b949e';
  status.innerText = 'Subscribing...';

  try {
    const base = window.SITE_BASE_URL || '';
    const res = await fetch(base + 'api/subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email })
    });
    const data = await res.json();
    if (data.success) {
      status.style.color = '#4ade80';
      status.innerText = '✓ ' + data.message;
      input.value = '';
    } else {
      status.style.color = '#f87171';
      status.innerText = data.error || 'Something went wrong.';
    }
  } catch (e) {
    status.style.color = '#f87171';
    status.innerText = 'Connection error. Please try again.';
  }
}

// ============ Chatbot ============
async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const msg = input.value.trim();
  if (!msg) return;
  const box = document.getElementById('chatMessages');
  box.insertAdjacentHTML('beforeend', `<div class="msg msg-user">${escapeHtml(msg)}</div>`);
  input.value = '';
  box.scrollTop = box.scrollHeight;

  box.insertAdjacentHTML('beforeend', `<div class="msg msg-bot" id="typing">Typing...</div>`);
  box.scrollTop = box.scrollHeight;

  try {
    const res = await fetch('api/chatbot_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: msg })
    });
    const data = await res.json();
    document.getElementById('typing').remove();
    box.insertAdjacentHTML('beforeend', `<div class="msg msg-bot">${data.reply ? mdToHtml(data.reply) : escapeHtml(data.error || 'Error')}</div>`);
  } catch (e) {
    document.getElementById('typing').remove();
    box.insertAdjacentHTML('beforeend', `<div class="msg msg-bot">Connection error. Please try again.</div>`);
  }
  box.scrollTop = box.scrollHeight;
}

function escapeHtml(str) {
  const d = document.createElement('div');
  d.innerText = str;
  return d.innerHTML;
}

// ============ Markdown rendering for chat replies ============
function mdToHtml(src) {
  src = String(src || '').replace(/\r\n?/g, '\n');
  const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const blocks = [];
  src = src.replace(/```[^\n]*\n?([\s\S]*?)```/g, (m, code) => {
    blocks.push(code.replace(/\n$/, ''));
    return '\n\u0000' + (blocks.length - 1) + '\u0000\n';
  });
  const inline = t => {
    const spans = [];
    t = t.replace(/`([^`\n]+)`/g, (m, c) => { spans.push(c); return '\u0001' + (spans.length - 1) + '\u0001'; });
    t = esc(t)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[\s(])\*(?!\s)([^*]+?)\*(?=[\s).,:;!?]|$)/g, '$1<em>$2</em>');
    return t.replace(/\u0001(\d+)\u0001/g, (m, i) => '<code>' + esc(spans[i]) + '</code>');
  };
  let html = '', para = [], list = null;
  const flushPara = () => { if (para.length) { html += '<p>' + para.map(inline).join('<br>') + '</p>'; para = []; } };
  const flushList = () => { if (list) { html += '</' + list + '>'; list = null; } };
  for (const raw of src.split('\n')) {
    const line = raw.replace(/\s+$/, '');
    let m;
    if (!line.trim()) { flushPara(); flushList(); continue; }
    if ((m = line.match(/^\u0000(\d+)\u0000$/))) { flushPara(); flushList(); html += '<pre><code>' + esc(blocks[m[1]]) + '</code></pre>'; continue; }
    if ((m = line.match(/^\s{0,3}(#{1,6})\s+(.*)$/))) { flushPara(); flushList(); const lv = Math.min(m[1].length + 2, 5); html += '<h' + lv + '>' + inline(m[2]) + '</h' + lv + '>'; continue; }
    if (/^\s{0,3}([-*_])\1{2,}\s*$/.test(line)) { flushPara(); flushList(); html += '<hr>'; continue; }
    if ((m = line.match(/^\s*[-*•]\s+(.*)$/))) {
      flushPara();
      if (list !== 'ul') { flushList(); html += '<ul>'; list = 'ul'; }
      html += '<li>' + inline(m[1]) + '</li>';
      continue;
    }
    if ((m = line.match(/^\s*(\d+)[.)]\s+(.*)$/))) {
      flushPara();
      if (list !== 'ol') { flushList(); html += '<ol start="' + m[1] + '">'; list = 'ol'; }
      html += '<li>' + inline(m[2]) + '</li>';
      continue;
    }
    flushList();
    para.push(line);
  }
  flushPara(); flushList();
  return html;
}

(function () {
  if (document.getElementById('mdChatStyles')) return;
  const s = document.createElement('style');
  s.id = 'mdChatStyles';
  s.textContent = `.msg-bot p{margin:0 0 .65em}
.msg-bot ul,.msg-bot ol{margin:.2em 0 .7em 1.3em;padding:0}
.msg-bot li{margin:.25em 0}
.msg-bot ol + ul{margin-left:2.4em;margin-top:-.4em}
.msg-bot h3,.msg-bot h4,.msg-bot h5{margin:.7em 0 .35em;font-size:1em}
.msg-bot code{background:rgba(255,255,255,.16);padding:1px 6px;border-radius:5px;font-family:Consolas,monospace;font-size:.9em}
.msg-bot pre{background:rgba(0,0,0,.35);padding:10px 12px;border-radius:8px;overflow-x:auto;margin:.5em 0 .7em}
.msg-bot pre code{background:none;padding:0}
.msg-bot hr{border:0;border-top:1px solid rgba(255,255,255,.2);margin:.7em 0}
.msg-bot>:last-child{margin-bottom:0}`;
  document.head.appendChild(s);
})();

// ============ Mind-map Generator ============
async function generateMindmap() {
  const topic = document.getElementById('mmTopic').value.trim();
  const subject = document.getElementById('mmSubject')?.value || '';
  const resource = document.getElementById('mmResource')?.value || '';
  if (!topic) { alert('Enter a topic first'); return; }
  const st = document.getElementById('mmStatus');
  st.innerText = 'Reading your notes & generating the mind-map... this can take up to a minute or two.';

  try {
    const res = await fetch('api/generate_mindmap.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic, subject, resource })
    });

    // Read as text first so a non-JSON response (502/504 HTML, PHP error) gives a useful message
    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      console.error('Mind-map: response was not JSON. HTTP', res.status, raw.slice(0, 800));
      st.innerText = 'Server error (HTTP ' + res.status + '). Open the browser console for details.';
      return;
    }

    st.innerText = '';
    if (data.error) { alert(data.error); return; }

    try {
      renderMindmap(data.mindmap);
    } catch (e) {
      console.error('Mind-map render error:', e, data);
      st.innerText = 'Mind-map was received but failed to display.';
      return;
    }
    mmShowSources(data.sources, resource);
  } catch (e) {
    console.error('Mind-map network error:', e);
    st.innerText = 'Network error or timeout. Please try again.';
  }
}

// Tell the student which of their uploaded resources the mind-map was built from
function mmShowSources(src, resource) {
  const st = document.getElementById('mmStatus');
  if (!st || resource === 'none' || !src) return;
  const used = src.used || [];
  const bad = src.unreadable || [];
  let msg = used.length
    ? '📚 Built using your resources: ' + used.join(', ') + '. (★ = appeared in past papers)'
    : 'ℹ️ No matching notes were found, so this was built from general syllabus knowledge.';
  if (bad.length) msg += ' ⚠️ Could not read text from: ' + bad.join(', ') + ' (scanned/image-based PDF?).';
  st.innerText = msg;
}

// Re-render the currently loaded mind-map (used when the "Show first" language order changes)
function mmRerender() {
  if (window._mmTree) renderMindmap(window._mmTree);
}

// Normalize a title/item that may be a {en, si} object, or (older saved data) a plain string
function mmBi(x) {
  if (x && typeof x === 'object') return { en: x.en || x.si || '', si: x.si || x.en || '' };
  return { en: String(x || ''), si: String(x || '') };
}

function renderMindmap(tree) {
  const container = document.getElementById('mindmapCanvas');
  const branches = tree.children || [];
  const langOrder = document.getElementById('mmLangOrder')?.value || 'en';

  function ordered(bi) {
    return langOrder === 'si' ? [bi.si, bi.en] : [bi.en, bi.si];
  }

  const titleBi = mmBi(tree.title);

  container.innerHTML = `
    <div class="mm-wrapper">
      <div class="mm-header">
        <div>
          <div class="mm-header-title">${escapeHtml(titleBi.en)}${titleBi.si && titleBi.si !== titleBi.en ? ' <span class="mm-header-title-si">/ ' + escapeHtml(titleBi.si) + '</span>' : ''}</div>
          <div class="mm-header-sub">${branches.length} main topics · English + සිංහල · hover a circle for the full text</div>
        </div>
        <div class="mm-header-icons">
          <button class="mm-icon-btn" title="Download outline" onclick="mmDownloadOutline()">⬇</button>
        </div>
      </div>
      <div class="mm-body">
        <div class="mm-controls">
          <button onclick="mmZoom(1.15)">+</button>
          <button onclick="mmZoom(0.87)">−</button>
        </div>
        <div class="mm-canvas-scroll">
          <div class="mm-canvas mm-canvas-radial" id="mmCanvasInner">
            <svg class="mm-lines" id="mmLines"></svg>
          </div>
        </div>
      </div>
    </div>`;

  window._mmTree = tree;
  window._mmZoomLevel = 1;

  const palette = ['#3b82f6', '#a855f7', '#10b981', '#ec4899', '#f59e0b', '#ef4444', '#06b6d4', '#84cc16'];

  const canvas = document.getElementById('mmCanvasInner');
  const n = branches.length || 1;
  const branchSize = 132, branchGap = 26;
  const subSize = 98, subGap = 18;

  // Radius for the branch ring — big enough that branch circles don't overlap each other
  const R1 = Math.max(230, n * (branchSize + branchGap) / (2 * Math.PI));

  // Each branch gets an angular slice; R2 (extra radius for its sub-items) grows with how many it has
  const meta = branches.map((branch, i) => {
    const angle = (2 * Math.PI * i / n) - Math.PI / 2;   // start at top, clockwise
    const kids = branch.items || branch.children || [];
    const spread = (2 * Math.PI / n) * 0.72;              // angular width reserved for this branch's sub-items
    const neededArc = kids.length * (subSize + subGap);
    const R2 = kids.length > 0 ? Math.max(130, neededArc / spread - R1) : 0;
    return { branch, angle, kids, spread, R2, color: palette[i % palette.length] };
  });

  const maxReach = R1 + Math.max(0, ...meta.map(m => m.R2)) + subSize / 2 + 50;
  const canvasSize = maxReach * 2;
  canvas.style.width = canvasSize + 'px';
  canvas.style.height = canvasSize + 'px';

  const svg = document.getElementById('mmLines');
  svg.setAttribute('width', canvasSize);
  svg.setAttribute('height', canvasSize);

  const cx = canvasSize / 2, cy = canvasSize / 2;
  let svgLines = '';

  // Center topic node
  const [cPrim, cSec] = ordered(titleBi);
  const centerEl = document.createElement('div');
  centerEl.className = 'mm-node-center';
  centerEl.style.left = cx + 'px';
  centerEl.style.top = cy + 'px';
  centerEl.innerHTML = `<div class="mm-center-main">${escapeHtml(cPrim)}</div>${cSec && cSec !== cPrim ? `<div class="mm-center-sub">${escapeHtml(cSec)}</div>` : ''}`;
  canvas.appendChild(centerEl);

  meta.forEach(m => {
    const bx = cx + R1 * Math.cos(m.angle);
    const by = cy + R1 * Math.sin(m.angle);

    svgLines += `<line x1="${cx}" y1="${cy}" x2="${bx}" y2="${by}" stroke="#64748b" stroke-width="1.5" stroke-dasharray="3,5" opacity="0.6"/>`;

    const bBi = mmBi(m.branch.title);
    const [bPrim, bSec] = ordered(bBi);
    const branchEl = document.createElement('div');
    branchEl.className = 'mm-node-branch';
    branchEl.style.left = bx + 'px';
    branchEl.style.top = by + 'px';
    branchEl.style.background = m.color;
    branchEl.title = bSec && bSec !== bPrim ? `${bPrim} / ${bSec}` : bPrim;
    branchEl.innerHTML = `<span>${escapeHtml(bPrim)}</span>`;
    canvas.appendChild(branchEl);

    m.kids.forEach((kid, j) => {
      const kAngle = m.kids.length > 1
        ? m.angle - m.spread / 2 + (m.spread * j / (m.kids.length - 1))
        : m.angle;
      const kx = cx + (R1 + m.R2) * Math.cos(kAngle);
      const ky = cy + (R1 + m.R2) * Math.sin(kAngle);

      svgLines += `<line x1="${bx}" y1="${by}" x2="${kx}" y2="${ky}" stroke="${m.color}" stroke-width="1.2" opacity="0.45"/>`;

      const kBi = mmBi(kid);
      const [kPrim, kSec] = ordered(kBi);
      const subEl = document.createElement('div');
      subEl.className = 'mm-node-sub';
      subEl.style.left = kx + 'px';
      subEl.style.top = ky + 'px';
      subEl.style.borderColor = m.color;
      subEl.title = kSec && kSec !== kPrim ? `${kPrim} / ${kSec}` : kPrim;
      const isImportant = kid && typeof kid === 'object' && kid.important;
      if (isImportant) {
        subEl.style.background = '#fff7d6';
        subEl.style.borderWidth = '3px';
        subEl.title = '★ Appeared in past papers — ' + subEl.title;
      }
      subEl.innerHTML = `<span>${isImportant ? '★ ' : ''}${escapeHtml(kPrim)}</span>`;
      canvas.appendChild(subEl);
    });
  });

  svg.innerHTML = svgLines;
}

function mmZoom(factor) {
  window._mmZoomLevel = Math.min(2, Math.max(0.4, (window._mmZoomLevel || 1) * factor));
  const canvas = document.getElementById('mmCanvasInner');
  if (canvas) canvas.style.transform = `scale(${window._mmZoomLevel})`;
}

function mmDownloadOutline() {
  const tree = window._mmTree;
  if (!tree) return;
  function t(x) {
    const bi = mmBi(x);
    return bi.si && bi.si !== bi.en ? `${bi.en}  /  ${bi.si}` : bi.en;
  }
  let text = t(tree.title) + '\n';
  (tree.children || []).forEach(c => {
    text += `  - ${t(c.title)}\n`;
    (c.items || c.children || []).forEach(it => { text += `      - ${t(it)}\n`; });
  });
  const blob = new Blob([text], { type: 'text/plain' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = mmBi(tree.title).en.replace(/[^a-z0-9]/gi, '_') + '_mindmap.txt';
  a.click();
}

// ============ Quiz ============
if (typeof currentQuiz === 'undefined') {
  var currentQuiz = [];
}
if (typeof userAnswers === 'undefined') {
  var userAnswers = {};
}

async function generateQuiz() {
  const topic = document.getElementById('quizTopic').value.trim();
  const subject = document.getElementById('quizSubject')?.value || '';
  const difficulty = document.getElementById('quizDifficulty').value;
  const count = document.getElementById('quizCount').value;
  const language = document.getElementById('quizLanguage')?.value || 'auto';
  if (!topic) { alert('Enter a topic first'); return; }

  document.getElementById('quizStatus').innerText = 'Generating quiz...';
  document.getElementById('quizContainer').innerHTML = '';

  try {
    const res = await fetch('api/generate_quiz.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic, subject, difficulty, count, language })
    });
    const data = await res.json();
    document.getElementById('quizStatus').innerText = '';
    if (data.error) { alert(data.error); return; }
    currentQuiz = data.questions;
    userAnswers = {};
    renderQuiz();
  } catch (e) {
    document.getElementById('quizStatus').innerText = 'Error generating quiz.';
  }
}

function renderQuiz() {
  const container = document.getElementById('quizContainer');
  container.innerHTML = '';
  currentQuiz.forEach((q, i) => {
    const opts = ['a', 'b', 'c', 'd'];
    const optHtml = opts.map(o => `<div class="option" data-q="${i}" data-opt="${o}" onclick="selectOption(${i}, '${o}')">${o.toUpperCase()}. ${escapeHtml(q['option_' + o])}</div>`).join('');
    container.insertAdjacentHTML('beforeend', `
      <div class="question-card">
        <p><strong>Q${i + 1}.</strong> ${escapeHtml(q.question)}</p>
        ${optHtml}
      </div>`);
  });
  container.insertAdjacentHTML('beforeend', `<button class="btn" onclick="submitQuiz()">Submit Quiz</button>`);
}

function selectOption(qIndex, opt) {
  userAnswers[qIndex] = opt;
  document.querySelectorAll(`.option[data-q="${qIndex}"]`).forEach(el => el.classList.remove('selected'));
  document.querySelector(`.option[data-q="${qIndex}"][data-opt="${opt}"]`).classList.add('selected');
}

function submitQuiz() {
  let score = 0;
  currentQuiz.forEach((q, i) => {
    const chosen = userAnswers[i];
    const opts = document.querySelectorAll(`.option[data-q="${i}"]`);
    opts.forEach(el => {
      const opt = el.dataset.opt;
      if (opt === q.correct_option) el.classList.add('correct');
      else if (opt === chosen && chosen !== q.correct_option) el.classList.add('incorrect');
    });
    const isCorrect = chosen === q.correct_option;
    if (isCorrect) score++;

    const card = opts[0]?.closest('.question-card');
    if (card && q.explanation) {
      const existing = card.querySelector('.ai-explanation');
      if (!existing) {
        const box = document.createElement('div');
        box.className = 'ai-explanation ' + (isCorrect ? 'ai-explanation-correct' : 'ai-explanation-wrong');
        box.innerHTML = `<strong>${isCorrect ? '✅ Correct!' : '❌ Wrong — AI Explanation:'}</strong><br>${escapeHtml(q.explanation)}`;
        card.appendChild(box);
      }
    }
  });
  document.getElementById('quizStatus').innerHTML = `<strong>Score: ${score} / ${currentQuiz.length}</strong>`;
  fetch('api/save_quiz_attempt.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ score, total: currentQuiz.length, questions: currentQuiz })
  });
}

// ============ Tool star toggle (Home page tool cards, GitHub-style) ============
async function starTool(toolSlug) {
  try {
    const base = window.SITE_BASE_URL || '';
    const res = await fetch(base + 'api/rate_tool.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tool: toolSlug })
    });
    const data = await res.json();
    if (!data.success) {
      console.error('Star toggle failed:', data.error);
    }
    return data;
  } catch (e) {
    console.error('Star toggle error:', e);
  }
}
window.starTool = starTool;

// ============ Digital rain background (all pages) ============
(function () {
  const canvas = document.getElementById('matrixRain');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  const chars = '01アイウエオカキクケコサシスセソタチツテト{}[]<>/*+-=;:.,ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');
  const fontSize = 15;
  let columns, drops;

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    columns = Math.floor(canvas.width / fontSize);
    drops = new Array(columns).fill(0).map(() => Math.random() * -100);
  }
  resize();
  window.addEventListener('resize', resize);

  function draw() {
    // fade the previous frame slightly instead of clearing, for a soft trailing effect
    ctx.fillStyle = 'rgba(255,255,255,0.12)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.font = fontSize + 'px monospace';
    ctx.fillStyle = 'rgba(100,116,139,0.6)'; // slightly stronger gray

    for (let i = 0; i < drops.length; i++) {
      const char = chars[Math.floor(Math.random() * chars.length)];
      const x = i * fontSize;
      const y = drops[i] * fontSize;
      ctx.fillText(char, x, y);

      if (y > canvas.height && Math.random() > 0.975) {
        drops[i] = 0;
      }
      drops[i]++;
    }
  }

  setInterval(draw, 60);
})();

// ============ Navbar: user menu (Dashboard + Logout under the name) ============
(function () {
  var btn = document.getElementById('navUserBtn');
  var menu = document.getElementById('navUserMenuLight');
  if (!btn || !menu) return;
  function setOpen(open) {
    menu.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    setOpen(!menu.classList.contains('open'));
  });
  document.addEventListener('click', function (e) {
    if (!menu.contains(e.target) && !btn.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.classList.contains('open')) { setOpen(false); btn.focus(); }
  });
})();

// ============ Mobile hamburger menu: close on link tap / outside tap / Escape ============
// header.php already toggles #navLinks via inline onclick, so this only ADDS
// close-on-link-tap / close-on-outside-tap / close-on-Escape — it never toggles
// the menu itself, so there's no double-toggle conflict with that inline onclick.
(function () {
  const hamburger = document.querySelector('.hamburger');
  const navList = document.getElementById('navLinks');
  if (!hamburger || !navList) return;

  // close the menu when a link inside it is tapped
  navList.addEventListener('click', function (e) {
    if (e.target.tagName === 'A') navList.classList.remove('open');
  });

  // close on outside tap
  document.addEventListener('click', function (e) {
    if (!navList.contains(e.target) && !hamburger.contains(e.target)) {
      navList.classList.remove('open');
    }
  });

  // close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') navList.classList.remove('open');
  });
})();