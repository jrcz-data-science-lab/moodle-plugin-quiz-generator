<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/extractor.php';

$pdf = __DIR__.'/fixtures/simple.pdf';

$result = autogenquiz_extract_text($pdf, 'pdf');

echo "Extracted:\n";
echo $result."\n";
