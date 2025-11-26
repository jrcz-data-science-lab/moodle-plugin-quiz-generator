<?php

function autogenquiz_extract_text($filepath, $extension)
{
    if ($extension === 'pdf') {
        $parser = new Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filepath);

        return $pdf->getText();
    }

    if ($extension === 'pptx') {
        $zip = new ZipArchive();
        $text = '';

        if ($zip->open($filepath) === true) {
            $i = 1;
            while ($zip->locateName("ppt/slides/slide{$i}.xml") !== false) {
                $xml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/', $xml, $matches);
                $text .= implode(' ', $matches[1])."\n";
                ++$i;
            }
            $zip->close();
        }

        return $text;
    }

    return '';
}
