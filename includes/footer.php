</main>
<footer class="site-footer-full">
  <div class="footer-inner">
    <div class="footer-col footer-brand">
     <div class="footer-logo"><span class="footer-logo-icon"><img src="<?= BASE_URL ?>assets/img/logo.png?v=5" alt="TechLearn Hub" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"></span> TechLearn <span>Hub</span></div>
      <p>A/L Technology students සඳහා Notes, Past Papers, Tutorials සහ AI-Powered Study Tools එකම තැනකින්.</p>
      <div class="footer-subscribe">
        <h4>📩 Stay Updated</h4>
        <p style="margin-bottom:10px;">New resources add වුනාම දැනගන්න, email එක දාන්න.</p>
        <div class="subscribe-box">
          <input type="email" id="subscribeEmail" placeholder="your@email.com">
          <button onclick="subscribeNewsletter()">Subscribe</button>
        </div>
        <p id="subscribeStatus" style="margin-top:8px; font-size:.8rem;"></p>
      </div>
    </div>
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="<?= BASE_URL ?>index.php">Home</a>
      <a href="<?= BASE_URL ?>subjects.php">Subjects</a>
      <a href="<?= BASE_URL ?>resources.php">Resources</a>
      <a href="<?= BASE_URL ?>register.php">Sign Up</a>
      <a href="<?= BASE_URL ?>admin/login.php" >Login</a>
       <a href="<?= BASE_URL ?>Contact .php">Contact Us</a>
   <a href="terms.php">Terms of Service</a>
      <a href="<?= BASE_URL ?>privacy.php">Privacy Policy</a>
     
    </div>
    <div class="footer-col">
      <h4>AI Tools</h4>

  <a href="<?= BASE_URL ?>study_helper.php">Cypher Study Helper</a>
      <a href="<?= BASE_URL ?>mindmap_generator.php">Mind-map Generator</a>
      <a href="<?= BASE_URL ?>quiz_generator.php">Quiz Generator</a>
       <a href="<?= BASE_URL ?>learn.php">Code Lab</a>
      
    </div>
    <div class="footer-col">
      <h4>Subjects</h4>
      <a href="<?= BASE_URL ?>resources.php?subject=SFT">SFT</a>
      <a href="<?= BASE_URL ?>resources.php?subject=ICT">ICT</a>
      <a href="<?= BASE_URL ?>resources.php?subject=ET">ET</a>
      <a href="<?= BASE_URL ?>resources.php?subject=BST">BST</a>
      <a href="<?= BASE_URL ?>resources.php?subject=English">English</a>
    </div>
  </div>
  <div class="social-strip">
    <a href="https://github.com/ayondilshan" target="_blank" rel="noopener" title="GitHub" aria-label="GitHub">
      <svg viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
    </a>
    <a href="https://linkedin.com/in/your-username" target="_blank" rel="noopener" title="LinkedIn" aria-label="LinkedIn">
      <svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
    </a>
    <a href="https://www.instagram.com/techlearn.hub?stkn=emU0cndybm5rdThl" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram">
      <svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
    </a>
    <a href="https://your-blog-link.com" target="_blank" rel="noopener" title="Blog" aria-label="Blog">
      <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
    </a>
    <a href="mailto:your@email.com" title="Email" aria-label="Email">
      <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    </a>
    <a href="https://twitter.com/your-username" target="_blank" rel="noopener" title="Twitter" aria-label="Twitter">
      <svg viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
    </a>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
    <p>🇱🇰 Made for Sri Lankan A/L Technology students</p>
  </div>
</footer>
<script>window.SITE_BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>