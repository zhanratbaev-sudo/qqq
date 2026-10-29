<?php
// локальная копия: почта никуда не уходит
add_filter('pre_wp_mail', '__return_true');
