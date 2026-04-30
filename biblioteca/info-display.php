<?php
$faq_file = dirname(__DIR__) . '/data/faq.html';
$faq_html = '';

if (!file_exists($faq_file)) {
    error_log('bandPromo: FAQ content missing. Run setup to initialize data/faq.html.');
    $faq_html = '<p>FAQ content is missing. Run setup to initialize <code>data/faq.html</code>.</p>';
} else {
    $loaded = file_get_contents($faq_file);
    if ($loaded === false) {
        error_log('bandPromo: Could not read FAQ content from data/faq.html.');
        $faq_html = '<p>Could not read FAQ content. Check file permissions for <code>data/faq.html</code>.</p>';
    } else {
        $faq_html = $loaded;
    }
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
