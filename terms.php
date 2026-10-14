<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Terms of Service';
require_once __DIR__ . '/includes/header.php';
?>

<div class="about-section" style="padding:44px 40px;">
  <div class="about-header" style="max-width:640px;">
    <span class="about-tag">📄 Terms of Service</span>
    <h2>Terms of Service</h2>
    <p>By using TechLearn Hub, you agree to the terms below. Please read them carefully before you create an account or use any of our study tools.</p>
  </div>

  <div style="max-width:760px; margin:0 auto; color:#374151; font-size:.94rem; line-height:1.75;">

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">1. Acceptance of Terms</h3>
    <p>By accessing or using TechLearn Hub ("we", "our", "us"), you agree to be bound by these Terms of Service. If you do not agree, please do not use the website.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">2. About the Service</h3>
    <p>TechLearn Hub is an educational platform for Sri Lankan A/L Technology students (SFT, ICT, ET, BST and English). It provides notes, past papers, tutorials and AI-powered study tools, including Cypher Study Helper, Mind-map Generator, Quiz Generator and Code Lab.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">3. User Accounts</h3>
    <ul style="padding-left:20px; margin-bottom:6px;">
      <li>You must provide accurate information when signing up.</li>
      <li>You are responsible for keeping your password secure and for all activity on your account.</li>
      <li>We may suspend or remove accounts that violate these terms.</li>
    </ul>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">4. Acceptable Use</h3>
    <ul style="padding-left:20px; margin-bottom:6px;">
      <li>Do not attempt to hack, disrupt, or damage the website or its servers.</li>
      <li>Do not access or use other users' data without permission.</li>
      <li>Do not copy, redistribute, or resell our content for commercial purposes without written permission.</li>
    </ul>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">5. Intellectual Property</h3>
    <p>Unless stated otherwise, the notes, articles, design and code on this site belong to TechLearn Hub. Past papers remain the property of their original owners (for example, the Department of Examinations, Sri Lanka). Please use all materials for personal study only.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">6. AI Tools Disclaimer</h3>
    <p>Responses from Cypher AI, the Quiz Generator, the Mind-map Generator, Code Lab and similar tools may contain errors. Please verify important information against your textbooks and teachers before relying on it for exams.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">7. Limitation of Liability</h3>
    <p>The website and its content are provided "as is" without warranties of any kind. We do not guarantee accuracy, completeness, or uninterrupted availability, and we are not liable for any loss or damage arising from your use of the site.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">8. Advertising and Third-Party Links</h3>
    <p>This site may display third-party advertisements, such as Google AdSense, and may contain links to external websites. We are not responsible for the content or practices of those third parties. For details on how data is handled, please see our <a href="<?= BASE_URL ?>privacy.php" style="color:#7c3aed; font-weight:600;">Privacy Policy</a>.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">9. Changes to These Terms</h3>
    <p>We may update these terms at any time. Continued use of the website after changes are posted means you accept the updated terms.</p>

    <h3 style="margin:28px 0 10px; color:#1f2937; font-size:1.1rem;">10. Contact Us</h3>
    <p>If you have any questions about these terms, please reach out through our <a href="<?= BASE_URL ?>Contact .php" style="color:#7c3aed; font-weight:600;">Contact Us</a> page.</p>

    <p style="margin-top:32px; color:#9ca3af; font-size:.82rem;">Last updated: <?= date('F Y') ?></p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>