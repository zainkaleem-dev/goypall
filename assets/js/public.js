/* ===========================================================
   Pitch Page - Public JavaScript
   Carousel, Lightbox, FAQ accordion
   =========================================================== */

(function(){
    'use strict';
    
    // ===== CAROUSEL =====
    var carouselIndex = 0;
    var slides = [];
    var dots = [];
    var track = null;
    
    function initCarousel() {
        track = document.getElementById('carouselTrack');
        if (!track) return;
        slides = track.querySelectorAll('.carousel-slide');
        dots = document.querySelectorAll('.carousel-dot');
        if (slides.length === 0) return;
        updateCarousel();
    }
    
    function updateCarousel() {
        if (!track) return;
        track.style.transform = 'translateX(-' + (carouselIndex * 100) + '%)';
        slides.forEach(function(s, i){ s.classList.toggle('active', i === carouselIndex); });
        dots.forEach(function(d, i){ d.classList.toggle('active', i === carouselIndex); });
    }
    
    window.carouselNext = function() {
        if (slides.length === 0) return;
        carouselIndex = (carouselIndex + 1) % slides.length;
        updateCarousel();
    };
    
    window.carouselPrev = function() {
        if (slides.length === 0) return;
        carouselIndex = (carouselIndex - 1 + slides.length) % slides.length;
        updateCarousel();
    };
    
    window.goToSlide = function(i) {
        if (i < 0 || i >= slides.length) return;
        carouselIndex = i;
        updateCarousel();
    };
    
    // ===== LIGHTBOX =====
    var lightboxIndex = 0;
    var lightboxEl = null;
    var lightboxImg = null;
    var lightboxCap = null;
    var lightboxCur = null;
    
    function initLightbox() {
        lightboxEl = document.getElementById('lightbox');
        lightboxImg = document.getElementById('lightboxImage');
        lightboxCap = document.getElementById('lightboxCaption');
        lightboxCur = document.getElementById('lightboxCurrent');
    }
    
    function showLightboxItem(i) {
        var data = window.PITCH_SCREENSHOTS || [];
        if (!data.length || !lightboxImg) return;
        if (i < 0) i = data.length - 1;
        if (i >= data.length) i = 0;
        lightboxIndex = i;
        lightboxImg.src = data[i].url;
        lightboxImg.alt = data[i].title || '';
        lightboxCap.textContent = data[i].caption || '';
        lightboxCur.textContent = (i + 1);
    }
    
    window.openLightbox = function(i) {
        if (!lightboxEl) return;
        showLightboxItem(i);
        lightboxEl.classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    
    window.closeLightbox = function() {
        if (!lightboxEl) return;
        lightboxEl.classList.remove('open');
        document.body.style.overflow = '';
    };
    
    window.lightboxNext = function(e) {
        if (e) e.stopPropagation();
        showLightboxItem(lightboxIndex + 1);
    };
    
    window.lightboxPrev = function(e) {
        if (e) e.stopPropagation();
        showLightboxItem(lightboxIndex - 1);
    };
    
    // ===== KEYBOARD NAVIGATION =====
    document.addEventListener('keydown', function(e){
        // Lightbox keys
        if (lightboxEl && lightboxEl.classList.contains('open')) {
            if (e.key === 'Escape') closeLightbox();
            else if (e.key === 'ArrowRight') showLightboxItem(lightboxIndex + 1);
            else if (e.key === 'ArrowLeft') showLightboxItem(lightboxIndex - 1);
            return;
        }
        // Carousel keys (only if focused on carousel area)
        if (slides.length > 0 && (e.target === document.body || e.target.classList.contains('carousel-slide'))) {
            if (e.key === 'ArrowRight') window.carouselNext();
            else if (e.key === 'ArrowLeft') window.carouselPrev();
        }
    });
    
    // ===== TOUCH SWIPE =====
    var touchStartX = 0;
    var touchEndX = 0;
    
    function initTouch() {
        var viewport = document.querySelector('.carousel-viewport');
        if (!viewport) return;
        viewport.addEventListener('touchstart', function(e){
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        viewport.addEventListener('touchend', function(e){
            touchEndX = e.changedTouches[0].screenX;
            var diff = touchEndX - touchStartX;
            if (Math.abs(diff) > 50) {
                if (diff < 0) window.carouselNext();
                else window.carouselPrev();
            }
        }, { passive: true });
    }
    
    // ===== FAQ ACCORDION =====
    function initFAQ() {
        document.querySelectorAll('.faq-question').forEach(function(btn){
            btn.addEventListener('click', function(){
                var item = btn.closest('.faq-item');
                if (!item) return;
                var wasOpen = item.classList.contains('open');
                // Close all
                document.querySelectorAll('.faq-item.open').forEach(function(i){ i.classList.remove('open'); });
                if (!wasOpen) item.classList.add('open');
            });
        });
    }
    
    // ===== INIT ALL =====
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){
            initCarousel();
            initLightbox();
            initTouch();
            initFAQ();
        });
    } else {
        initCarousel();
        initLightbox();
        initTouch();
        initFAQ();
    }
})();
