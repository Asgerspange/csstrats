<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-logo">
            <a href="/" class="logo-link">
                <span class="logo-text">CSSTRATS</span>
            </a>
            <p class="copyright">&copy; 2024 - <span id="year"></span> IS Digital ApS CVR: 45603768. All rights reserved.</p>
        </div>
        
        <div class="footer-links">
            <div class="links-group">
                <h4 class="group-title">Legal</h4>
                <div class="links-list">
                    <a href="#" onClick="window.csConsent&&window.csConsent.open();return false;" class="footer-link">Manage your consents</a>
                    <a href="/tos" class="footer-link" target="_blank">Terms and Conditions</a>
                    <a href="/cookie-policy" class="footer-link" target="_blank">Cookie Policy</a>
                    <a href="/policy" class="footer-link" target="_blank">Privacy Policy</a>
                </div>
            </div>
            
            <div class="links-group">
                <h4 class="group-title">Quick Links</h4>
                <div class="links-list">
                    <a href="/#features" class="footer-link">Features</a>
                    <a href="/#pricing" class="footer-link">Pricing</a>
                    <a href="/#support" class="footer-link">Support</a>
                </div>
            </div>
            
            <div class="links-group">
                <h4 class="group-title">Follow Us</h4>
                <div class="social-icons">
                    <a href="https://discord.gg/Rmp5p4r7CT" target="_blank" rel="noopener noreferrer" class="social-icon">
                        <i class="pi pi-discord"></i>
                    </a>
                    <a href="https://x.com/CsStrats2" target="_blank" rel="noopener noreferrer" class="social-icon">
                        <i class="pi pi-twitter"></i>
                    </a>
                    <a href="https://www.youtube.com/@CSStrats2" target="_blank" rel="noopener noreferrer" class="social-icon">
                        <i class="pi pi-youtube"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="footer-container">
            <p class="footer-tagline">Elevate Your CS2 Teamplay</p>
            <a href="#navbar" class="back-to-top">
                <span>Back to top</span>
                <i class="pi pi-chevron-up"></i>
            </a>
        </div>
    </div>
</footer>

<script>
    document.getElementById("year").textContent = new Date().getFullYear();
    
    // Smooth scroll for back to top
    document.querySelector('.back-to-top').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
</script>

<style>
.site-footer {
  background-color: #141421;
  color: white;
  padding-top: 4rem;
  overflow: hidden;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    flex-direction: column;
    gap: 3rem;
}

@media (min-width: 768px) {
    .footer-container {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: space-between;
    }
}

.footer-logo {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.logo-link {
    text-decoration: none;
    display: inline-block;
}

.logo-text {
    font-size: 1.8rem;
    font-weight: 800;
    color: white;
    letter-spacing: 1px;
    text-transform: uppercase;
    position: relative;
}

.logo-text::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 0;
    width: 40px;
    height: 3px;
    border-radius: 1.5px;
    background: linear-gradient(to right, #58bc82, #3a9861);
    transition: width 0.3s ease;
}

.logo-text:hover::after {
    width: 100%;
}

.copyright {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    margin-top: 0.5rem;
}

.footer-links {
    display: flex;
    flex-wrap: wrap;
    gap: 3rem;
}

.links-group {
    min-width: 160px;
}

.group-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: white;
    margin-bottom: 1.2rem;
    position: relative;
    display: inline-block;
}

.group-title::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 0;
    width: 30px;
    height: 2px;
    border-radius: 1px;
    background: #58bc82;
}

.links-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.footer-link {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    position: relative;
    display: inline-block;
}

.footer-link:hover {
    color: white;
    transform: translateX(3px);
}

.footer-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background-color: #58bc82;
    transition: width 0.3s ease;
}

.footer-link:hover::after {
    width: 100%;
}

.social-icons {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.social-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transition: all 0.3s ease;
}

.social-icon:hover {
    background: #58bc82;
    transform: translateY(-3px);
}

.social-icon i {
    font-size: 1rem;
}

.footer-bottom {
    margin-top: 3rem;
    padding: 1.5rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-bottom .footer-container {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    padding-top: 0;
    padding-bottom: 0;
    gap: 1rem;
}

.footer-tagline {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    font-style: italic;
}

.back-to-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.back-to-top:hover {
    color: white;
}

.back-to-top i {
    font-size: 0.8rem;
}

@media (max-width: 767px) {
    .site-footer {
        padding-top: 3rem;
    }
    
    .footer-bottom .footer-container {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1rem;
    }
    
    .footer-links {
        width: 100%;
        justify-content: space-between;
    }
    
    .links-group {
        min-width: 120px;
    }
}

@media (max-width: 480px) {
    .footer-links {
        flex-direction: column;
        gap: 2rem;
    }
}
</style>