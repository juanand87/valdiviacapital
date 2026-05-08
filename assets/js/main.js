// ========================================
// VALDIVIA CAPITAL - JavaScript Principal
// ========================================

$(document).ready(function() {
    
    // Fecha actual en el header
    updateCurrentDate();

    // ---- MODO OSCURO ----
    if (localStorage.getItem('darkMode') === '1') {
        document.documentElement.classList.add('dark-mode');
    }
    $('#btn-dark-mode').on('click', function () {
        const isDark = document.documentElement.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', isDark ? '1' : '0');
    });
    
    // Newsletter form
    $('#newsletter-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const email = $form.find('input[type="email"]').val();
        const $btn  = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'ajax/newsletter.php',
            method: 'POST',
            data: { email: email },
            dataType: 'json',
            success: function(response) {
                const color = response.success ? '#d1fae5' : '#fee2e2';
                const txtColor = response.success ? '#065f46' : '#991b1b';
                $form.html('<p style="background:' + color + ';color:' + txtColor + ';padding:12px;border-radius:8px;font-size:14px;text-align:center;">' + response.message + '</p>');
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Suscribirme');
            }
        });
    });
    
    // Smooth scroll para enlaces internos
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.getAttribute('href'));
        if(target.length) {
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 100
            }, 800);
        }
    });
    
    // Animación de aparición para las cards
    animateOnScroll();
    $(window).on('scroll', animateOnScroll);
    
    // Contador de vistas (incrementar cuando se lee una noticia)
    if($('.article-full').length > 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const noticiaId = urlParams.get('id');
        if(noticiaId) {
            incrementarVistas(noticiaId);
        }
    }
    
    // Botón de búsqueda
    $('.search-form').on('submit', function(e) {
        const searchValue = $(this).find('input[name="q"]').val().trim();
        if(searchValue === '') {
            e.preventDefault();
            alert('Por favor ingresa un término de búsqueda');
        }
    });
    
    // Navegación sticky con efecto
    let lastScroll = 0;
    $(window).scroll(function() {
        const currentScroll = $(this).scrollTop();
        
        if (currentScroll > 100) {
            $('.main-nav').addClass('scrolled');
        } else {
            $('.main-nav').removeClass('scrolled');
        }
        
        lastScroll = currentScroll;
    });
});

// Función para actualizar la fecha
function updateCurrentDate() {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    
    const fecha = new Date();
    const fechaTexto = `${dias[fecha.getDay()]}, ${fecha.getDate()} de ${meses[fecha.getMonth()]} de ${fecha.getFullYear()}`;
    
    $('#current-date').text(fechaTexto);
}

// Animación al scroll con clase CSS
function animateOnScroll() {
    $('.fade-in, .hero-grid-card, .widget').each(function() {
        const elementTop = $(this).offset().top;
        const viewportBottom = $(window).scrollTop() + $(window).height();
        if (elementTop < viewportBottom - 40) {
            $(this).css({ opacity: '', transform: '' }).addClass('visible');
        }
    });
}

// Incrementar contador de vistas
function incrementarVistas(noticiaId) {
    $.ajax({
        url: 'ajax/incrementar_vista.php',
        method: 'POST',
        data: { id: noticiaId },
        dataType: 'json'
    });
}

// Función para compartir en redes sociales
function compartirNoticia(red, titulo, url) {
    const urls = {
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
        twitter: `https://twitter.com/intent/tweet?text=${encodeURIComponent(titulo)}&url=${encodeURIComponent(url)}`,
        whatsapp: `https://wa.me/?text=${encodeURIComponent(titulo + ' ' + url)}`,
        email: `mailto:?subject=${encodeURIComponent(titulo)}&body=${encodeURIComponent(url)}`
    };
    
    if(urls[red]) {
        window.open(urls[red], '_blank', 'width=600,height=400');
    }
}

// Inicialización de estilos de animación (solo vía CSS, no inline)
// Los estilos iniciales se manejan con .fade-in { opacity: 0 } en style.css
