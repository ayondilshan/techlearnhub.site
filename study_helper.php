<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';

require_login();
$pageTitle = 'AI Study Helper';
require_once __DIR__ . '/includes/header.php';
?>
<h1 style="margin-bottom:16px;">🤖 Cypher Study Helper</h1>
<p style="color:var(--muted); margin-bottom:16px;">Ask anything about SFT, ICT, ET, BST or English — the AI will help explain concepts, solve problems, or summarize topics.</p>

<style>
.msg-bot p{margin:0 0 .65em}
.msg-bot ul,.msg-bot ol{margin:.2em 0 .7em 1.3em;padding:0}
.msg-bot li{margin:.25em 0}
.msg-bot ol + ul{margin-left:2.4em;margin-top:-.4em}
.msg-bot h3,.msg-bot h4,.msg-bot h5{margin:.7em 0 .35em;font-size:1em}
.msg-bot code{background:rgba(255,255,255,.16);padding:1px 6px;border-radius:5px;font-family:Consolas,monospace;font-size:.9em}
.msg-bot pre{background:rgba(0,0,0,.35);padding:10px 12px;border-radius:8px;overflow-x:auto;margin:.5em 0 .7em}
.msg-bot pre code{background:none;padding:0}
.msg-bot hr{border:0;border-top:1px solid rgba(255,255,255,.2);margin:.7em 0}
.msg-bot>:last-child{margin-bottom:0}
</style>

<div class="chat-window">
  <div class="chat-messages" id="chatMessages">
    <div class="msg msg-bot">Hi! I'm your TechLearn AI assistant. What would you like to study today?</div>
  </div>
  <div class="chat-input">
    <input type="text" id="chatInput" placeholder="Type your question..." onkeydown="if(event.key==='Enter') sendStudyMessage()">
    <button class="btn" id="chatSend" onclick="sendStudyMessage()">Send</button>
  </div>
</div>

<script>
// Safe Markdown -> HTML (headings, **bold**, *italic*, `code`, code blocks, lists, paragraphs)
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

let studyBusy = false;

function sendStudyMessage() {
  if (studyBusy) return;
  const input = document.getElementById('chatInput');
  const messages = document.getElementById('chatMessages');
  const sendBtn = document.getElementById('chatSend');
  const text = input.value.trim();
  if (!text) return;

  const userDiv = document.createElement('div');
  userDiv.className = 'msg msg-user';
  userDiv.textContent = text;
  messages.appendChild(userDiv);

  input.value = '';
  messages.scrollTop = messages.scrollHeight;

  const botDiv = document.createElement('div');
  botDiv.className = 'msg msg-bot';
  botDiv.textContent = 'Thinking...';
  messages.appendChild(botDiv);
  messages.scrollTop = messages.scrollHeight;

  studyBusy = true;
  sendBtn.disabled = true;

  fetch('api/study_helper_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: text })
  })
    .then(res => res.text())
    .then(raw => {
      let data;
      try { data = JSON.parse(raw); }
      catch (e) { botDiv.textContent = 'Server error. Please try again.'; return; }

      if (data.error) {
        botDiv.textContent = 'Error: ' + data.error;
      } else {
        botDiv.innerHTML = mdToHtml(data.reply);
      }
    })
    .catch(() => {
      botDiv.textContent = 'Something went wrong. Please try again.';
    })
    .finally(() => {
      studyBusy = false;
      sendBtn.disabled = false;
      messages.scrollTop = messages.scrollHeight;
      input.focus();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>