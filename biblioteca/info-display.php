<?php
require_once __DIR__ . '/page-storage.php';

$faq_html = '';

try {
    $faq_html = bandpromo_page_render_for_delivery(dirname(__DIR__), 'faq');
} catch (Throwable $throwable) {
    error_log('bandPromo: FAQ content missing or unreadable: ' . $throwable->getMessage());
    $faq_html = '<div class="page-content"><p class="page-paragraph">FAQ content is missing. Run setup to initialize page content.</p></div>';
}
?>

<!-- Info Lightbox -->
<div class="info-lightbox" id="infoLightbox">
    <div class="info-lightbox-content">
        <div class="info-lightbox-close" onclick="closeInfoLightbox()">&times;</div>
        <div class="info-lightbox-text">
            <?php echo $faq_html; ?>
        </div>
    </div>
</div>
