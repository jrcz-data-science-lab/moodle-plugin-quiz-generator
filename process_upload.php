<?php
require('../../config.php');
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '300');
require_once($CFG->libdir . '/filelib.php');

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpPresentation\IOFactory as PPTXReader;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('autogenquiz', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/autogenquiz:view', $context);

// Check upload
if (!isset($_FILES['quizfile'])) {
    throw new moodle_exception('nofile', 'error');
}

$file = $_FILES['quizfile'];
$filename = clean_param($file['name'], PARAM_FILE);

// Validate file
$allowedtypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];
$maxbytes = 10 * 1024 * 1024; // 10MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new moodle_exception('Upload failed. Error code: ' . $file['error']);
}
if ($file['size'] > $maxbytes) {
    throw new moodle_exception('File too large. Maximum 10MB allowed.');
}
if (!in_array($file['type'], $allowedtypes)) {
    throw new moodle_exception('Invalid file type. Only PDF or PPTX files are allowed.');
}

// File API
$fs = get_file_storage();
$fileinfo = [
    'contextid' => $context->id,
    'component' => 'mod_autogenquiz',
    'filearea'  => 'uploadedfiles',
    'itemid'    => $USER->id,
    'filepath'  => '/',
    'filename'  => $filename
];

// Remove old file
if ($oldfile = $fs->get_file($context->id, 'mod_autogenquiz', 'uploadedfiles', $USER->id, '/', $filename)) {
    $oldfile->delete();
}

// Store file
$storedfile = $fs->create_file_from_pathname($fileinfo, $file['tmp_name']);

// --- Extract text ---
require_once(__DIR__ . '/vendor/autoload.php');
$extracted = '';
$tmp = $file['tmp_name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

try {
    if ($ext === 'pdf') {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tmp);
        $extracted = $pdf->getText();
    } else if ($ext === 'pptx') {
        $zip = new ZipArchive();
        if ($zip->open($tmp) === true) {
            $slideTexts = '';
            for ($i = 1; $zip->locateName("ppt/slides/slide{$i}.xml") !== false; $i++) {
                $data = $zip->getFromName("ppt/slides/slide{$i}.xml");
                if ($data) {
                    $data = strip_tags($data, '<a:t>');
                    preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/', $data, $matches);
                    $slideTexts .= implode(' ', $matches[1]) . "\n";
                }
            }
            $zip->close();
            $extracted = trim($slideTexts);
        } else {
            $extracted = '[Error: Cannot open PPTX file]';
        }
    } else {
        $extracted = 'Unsupported file type for extraction.';
    }
} catch (Exception $e) {
    $extracted = 'Text extraction failed: ' . $e->getMessage();
}

// Save metadata and extracted text
$record = new stdClass();
$record->autogenquizid = $cm->instance;
$record->userid = $USER->id;
$record->filename = $storedfile->get_filename();
$record->filesize = $storedfile->get_filesize();
$record->filehash = $storedfile->get_contenthash();
$record->status = 'uploaded';
$record->timecreated = time();
$record->confirmed_text = $extracted;
$DB->insert_record('autogenquiz_files', $record);

// Redirect
redirect(new moodle_url('/mod/autogenquiz/view.php', ['id' => $id]),
    get_string('fileuploaded', 'autogenquiz'));

