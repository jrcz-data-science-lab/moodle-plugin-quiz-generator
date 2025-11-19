## How to use this plugin
**Option A (for developers):**

1. Download the `.zip` file and unzip it.
2. Place the `autogenquiz` folder inside your Moodle `mod/` directory (for example: `moodle/mod/autogenquiz`).
3. Open a terminal, navigate to the `autogenquiz` folder, and run: `composer install`. This installs all required PHP dependencies.
4. Create a new file at: `moodle/public/mod/autogenquiz/config_local.php`. Then paste the following content (replace `YOUR_LLM_IP_ADDRESS` with your server IP):
    ```php
      <?php
      // This file should NOT be committed to Git.
      // Add this to your .gitignore: mod/autogenquiz/config_local.php

      $AUTOGENQUIZ_API_URL = 'http://YOUR_LLM_IP_ADDRESS:11434/api/generate';
    ```

**Option B (recommended for non-developers):**
1. Ask me for the `.zip` file that includes the `vendor/` folder (all dependencies are preinstalled) and a preconfigured `config_local.php`.
2. Unzip it and place the folder directly inside your Moodle `mod/` directory — no additional steps required.

## Files explanation

### Required core files
When creating a new activity plugin, Moodle requires a minimum core set of files.

<table>
  <tr>
    <td>version.php</td>
    <td>lang/en/autogenquiz.php</td>
    <td>db/access.php</td>
  </tr>
  <tr>
    <td>db/install.xml</td>
    <td>index.php</td>
    <td>view.php</td>
  </tr>
  <tr>
    <td>mod_form.php</td>
    <td>lib.php</td>
    <td> </td>
  </tr>
</table>


1. `version.php`\
Defines the plugin, such as: the plugin’s name and version, the minimum Moodle version required, etc. Moodle reads this file during installation and upgrade. Without this file, Moodle cannot install or recognize the plugin.

2. `lang/en/autogenquiz.php`\
Contains English language strings. Every plugin must have a default language pack.\
When text is in the language file, Moodle’s translation system can replace it based on the user's chosen language.

3. `db/access.php`\
Defines plugin capabilities (permissions). Moodle requires capability definitions for roles.

4. `db/install.xml`\
Defines plugin database tables. Moodle installs DB schema from this file on first install.

5. `index.php`\
Lists all AutoGenQuiz activities in the course and provides links to open them. This file is required by Moodle and follows the standard template for all activity modules.

6. `view.php`\
Primary display page when user clicks the activity. Every module must include this.\
The `view.php` of AutoGenQuiz plugin does three major things: Checks permissions and sets up the Moodle page; Displays file upload UI + instructions; Shows uploaded files, their extracted text, and actions (edit/save/delete/ready). This page is the “home screen” of the AutoGenQuiz activity.

7. `mod_form.php`\
Defines the form used when adding or editing the activity. Required for all modules.

8. `lib.php`\
Defines core callbacks: `add_instance`, `update_instance`, `delete_instance`, `supports()`. These are required for a working module.\
For the AutoGenQuiz plugin, it provides “callback functions” that Moodle calls when: a teacher creates an AutoGenQuiz, a teacher updates it, a teacher deletes it. Moodle checks what features the module supports.\
If these functions do not exist, Moodle cannot install or manage the activity instance. This file connects the add/edit form (`mod_form.php`) with the database table (autogenquiz).

### Other files
Everything below is not required at the start, but they exist because the plugin needs advanced functionality.

<table>
  <tr>
    <td>db/upgrade.php</td>
    <td>config_local.php</td>
    <td>process_upload.php</td>
  </tr>
  <tr>
    <td>delete_file.php</td>
    <td>save_text.php</td>
    <td>ai_request.php</td>
  </tr>
  <tr>
    <td>generate.php</td>
    <td>save_generated.php</td>
    <td>import_to_bank.php</td>
  </tr>
  <tr>
    <td>composer.json</td>
    <td> </td>
    <td> </td>
  </tr>
</table>

1. Basic metadata & UI\
`composer.json`: Adds external libraries (PDF parser, PPTX parser).

2. Handling file uploading\
`process_upload.php`:\
Receiving the uploaded file; Validating it (correct type, size, no errors); Saving it into Moodle’s File API; Extracting text from PDF or PPTX; Cleaning and normalizing the extracted text; Storing the metadata + text into your database tables; Creating a task entry (the plugin’s internal tracking); Redirecting back to the main view page.\
`delete_file.php`:\
This script deletes: The stored physical file from Moodle’s File API; The corresponding database record in `autogenquiz_files`; Then redirects back to the activity page with a message.

3. Managing extracted text\
`save_text.php`:\
This script saves the edited extracted text for a file after the teacher clicks Save in the view page: Receive the edited text; Security + permission checks; Update the database record in `autogenquiz_files`; Redirect back with a success message. This file does not handle file uploads — only text editing.

4. AI generation system\
`ai_request.php`:\
This file defines a function to ask the AI server to generate questions based on extracted text. Other pages (`generate.php`) rely on this function to get the AI output.\
The function: Loads the local API configuration(from `config_local.php`); Builds a strict prompt for the AI model; Sends a JSON request to the LLM API using cURL; Returns the raw response from the API (or an error message). \
`generate.php`:\
This page handles the core workflow of the plugin:\
Show a form to choose how many questions to generate; Send the extracted text to the AI model; Parse the AI’s JSON output; Display editable questions (teachers can change text and answers); Save edited questions; Import questions into the activity-specific question bank.\
`save_generated.php`:\
This script is called when the teacher clicks “Save Changes” on the Generated Questions form in `generate.php`. It does not talk to the AI. It only saves the teacher-edited version of the questions.\
It: Receives the edited questions from the form; Normalizes them into a clean JSON structure; Updates the `autogenquiz_generated` record; Redirects back to `generate.php` and shows a success message.

5. Import into Moodle Question Bank\
`import_to_bank.php`:\
Reads the saved, cleaned AI-generated questions; Converts each item into a Moodle True/False question; Inserts them into the activity-level question bank category; Marks the generation as “imported”; Redirects back to the generation page with a success message.

6. Local configuration\
`config_local.php`(ignored by Git): Stores local settings such as the API URL(`$AUTOGENQUIZ_API_URL`).

7. DB upgrade\
`db/upgrade.php`: Defines upgrade steps when schema changes. Only needed after plugin evolves beyond version 1.