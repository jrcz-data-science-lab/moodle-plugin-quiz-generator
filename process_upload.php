<?php

require '../../config.php';

// Makes sure large PDF/PPTX files can be processed without crashing.
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '300');

require_once $CFG->libdir.'/filelib.php'; // file handling library

use Smalot\PdfParser\Parser as PdfParser;

// Get required parameters + permission checks
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

// If the form didn't upload anything → error.
if (!isset($_FILES['quizfile'])) {
    throw new moodle_exception('nofile', 'error');
}

$file = $_FILES['quizfile'];
$filename = clean_param($file['name'], PARAM_FILE);
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Validate the file type, size, and upload errors
$allowedtypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$maxbytes = 80 * 1024 * 1024; // 80MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new moodle_exception('Upload failed. Error code: '.$file['error']);
}
if ($file['size'] > $maxbytes) {
    throw new moodle_exception('File too large. Maximum 10MB allowed.');
}
if (!in_array($file['type'], $allowedtypes)) {
    throw new moodle_exception('Invalid file type. Only PDF or PPTX files are allowed.');
}

// store the file using Moodle's File API after validation
$fs = get_file_storage();
$fileinfo = [
    'contextid' => $context->id,
    'component' => 'mod_autogenquiz',
    'filearea' => 'uploadedfiles',
    'itemid' => $USER->id,
    'filepath' => '/',
    'filename' => $filename,
];

// Remove old file if exists
if ($oldfile = $fs->get_file($context->id, 'mod_autogenquiz', 'uploadedfiles', $USER->id, '/', $filename)) {
    $oldfile->delete();
}

$storedfile = $fs->create_file_from_pathname($fileinfo, $file['tmp_name']);

// --- Extract text ---
require_once __DIR__.'/vendor/autoload.php';

$extracted = '';
$tmp = $file['tmp_name'];

try {
    if ($ext === 'pdf') {
        // --- PDF extraction with light formatting cleanup ---
        $parser = new PdfParser();
        $pdf = $parser->parseFile($tmp);
        $pages = $pdf->getPages();

        $pageTexts = [];
        $pagenum = 1;

        foreach ($pages as $page) {
            $text = trim($page->getText());

            // Normalize spaces and fix broken line issues
            $text = preg_replace("/[ \t]+/", ' ', $text);
            $text = preg_replace("/(\r?\n){2,}/", "\n", $text);

            // Add a line break every ~200 characters to keep natural spacing
            $text = wordwrap($text, 200, "\n", true);

            // Try to detect table-like patterns and mark them
            if (preg_match_all('/(\w+\s+\d+[\s\d]*){3,}/', $text)) {
                $text = preg_replace('/(\w+\s+\d+[\s\d]*){3,}/', "[Table detected]\n$0\n", $text);
            }

            $pageTexts[] = "[Page {$pagenum}]\n".trim($text)."\n";
            ++$pagenum;
        }

        $extracted = implode("\n\n", $pageTexts);
    } elseif ($ext === 'pptx') {
        // --- PPTX extraction (skips images and tables) ---
        $zip = new ZipArchive();
        if ($zip->open($tmp) === true) {
            $slideTexts = [];
            $i = 1;

            while ($zip->locateName("ppt/slides/slide{$i}.xml") !== false) {
                $xml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                if ($xml) {
                    // Remove images and tables
                    $xml = preg_replace('/<p:pic.*?<\/p:pic>/s', '', $xml);
                    $xml = preg_replace('/<a:tbl.*?<\/a:tbl>/s', '', $xml);

                    // Extract visible text
                    preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/', $xml, $matches);
                    $slidecontent = implode(' ', $matches[1]);

                    // Normalize spaces
                    $slidecontent = trim(preg_replace('/\s+/', ' ', $slidecontent));

                    $slideTexts[] = "[Slide {$i}]\n".$slidecontent."\n";
                }
                ++$i;
            }

            $zip->close();
            $extracted = implode("\n\n", $slideTexts);
        } else {
            $extracted = '[Error: Cannot open PPTX file]';
        }
    } else {
        $extracted = 'Unsupported file type for extraction.';
    }
} catch (Exception $e) {
    $extracted = 'Text extraction failed: '.$e->getMessage();
}

// --- Normalize encoding ---
$extracted = mb_convert_encoding($extracted, 'UTF-8', 'auto');
$extracted = iconv('UTF-8', 'UTF-8//IGNORE', $extracted);

// Save file metadata + extracted text into the database
$record = new stdClass();
$record->autogenquizid = $cm->instance;
$record->userid = $USER->id;
$record->filename = $storedfile->get_filename();
$record->filesize = $storedfile->get_filesize();
$record->filehash = $storedfile->get_contenthash();
$record->status = 'uploaded';
$record->timecreated = time();
$record->confirmed_text = trim($extracted);

$fileid = $DB->insert_record('autogenquiz_files', $record, true);

// --- Create a task entry ---
$task = new stdClass();
$task->fileid = $fileid;
$task->courseid = $course->id;
$task->status = 'pending';
$task->created_at = time();
$task->updated_at = time();
$DB->insert_record('autogenquiz_tasks', $task);

// --- Redirect back ---
redirect(
    new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
    get_string('fileuploaded', 'autogenquiz'),
    null,
    core\output\notification::NOTIFY_SUCCESS
);
