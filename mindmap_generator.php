<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db.php';
require_login();
$pageTitle = 'Mind-map Generator';
require_once __DIR__ . '/includes/header.php';
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

// Uploaded notes / past papers the student can build the mind-map from
$mmResources = [];
try {
    $mmResources = $pdo->query("SELECT id, title, type, subject_id FROM resources ORDER BY title LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $mmResources = [];
}
?>
<h1 style="margin-bottom:16px;">🧠 AI Mind-map Generator</h1>
<p style="color:var(--muted); margin-bottom:16px;">Enter any topic and get an instant, detailed, bilingual (English + සිංහල) mind-map built from your own notes &amp; past papers.</p>

<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
  <input type="text" id="mmTopic" placeholder="e.g. Object Oriented Programming" style="flex:1; min-width:220px; padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
  <select id="mmSubject" style="padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
    <option value="">General</option>
    <?php foreach ($subjects as $s): ?>
      <option value="<?= (int)$s['id'] ?>"><?= clean($s['code']) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="mmLangOrder" onchange="mmRerender()" title="Show first" style="padding:10px 12px; border:1px solid var(--border); border-radius:8px;">
    <option value="en">English first</option>
    <option value="si">සිංහල first</option>
  </select>
  <button class="btn" onclick="generateMindmap()">Generate</button>
</div>

<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
  <label for="mmResource" style="font-size:.85rem; color:var(--muted);">Build from:</label>
  <select id="mmResource" style="flex:1; min-width:220px; max-width:520px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:.9rem;"></select>
</div>

<p id="mmStatus" style="color:var(--muted); margin-bottom:10px;"></p>

<div id="mindmapCanvas"></div>

<script>
// Fill the "Build from" list with the resources of the chosen subject
const MM_RESOURCES = <?= json_encode($mmResources, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function mmFillResources() {
  const subj = document.getElementById('mmSubject').value;
  const sel = document.getElementById('mmResource');
  sel.innerHTML = '';
  const add = (value, text) => { const o = document.createElement('option'); o.value = value; o.textContent = text; sel.appendChild(o); };
  add('', 'Auto — use my matching notes & past papers');
  MM_RESOURCES
    .filter(r => !subj || String(r.subject_id) === subj)
    .forEach(r => add(r.id, (r.type ? r.type + ': ' : '') + r.title));
  add('none', "Don't use my resources (general syllabus only)");
}
document.getElementById('mmSubject').addEventListener('change', mmFillResources);
mmFillResources();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>