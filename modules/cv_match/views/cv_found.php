<?php
/**
 * What the free match found in the CV, for the CV panel (js/app.js
 * showCvFound): $result['cv_found'] (Match_result::cv_found).
 */
?>
<template class="cv-found" data-status="<?= out($result['cv_found']['status']) ?>"><?php foreach ($result['cv_found']['terms'] as $term): ?><span class="chip"><?= out($term) ?></span><?php endforeach; ?><?php if ($result['cv_found']['more'] !== ''): ?><span class="muted"><?= out($result['cv_found']['more']) ?></span><?php endif; ?></template>
