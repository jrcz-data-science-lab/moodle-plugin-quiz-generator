## 1. How to install this plugin
### 1.1 For developers

1. Download the `.zip` file and unzip it.
2. Place the `autogenquiz` folder inside your Moodle `mod/` directory (for example: `moodle/mod/autogenquiz` or `moodle/public/mod/autogenquiz`).
3. Open a terminal, navigate to the `autogenquiz` folder, and run: `composer install`. This installs all required PHP dependencies.
4. Create a new file at: `moodle/public/mod/autogenquiz/config_local.php`. Then paste the following content (replace `YOUR_LLM_IP_ADDRESS` with your server IP):
    ```php
      <?php
      // This file should NOT be committed to Git.
      // Add this to your .gitignore: mod/autogenquiz/config_local.php

      $AUTOGENQUIZ_API_URL = 'http://YOUR_LLM_IP_ADDRESS:11434/api/generate';
    ```

## 2. How to use this plugin
This plugin already provides clear instructions for uploading files and generating questions, so you can follow those steps without any problem.

### 2.1 How to use the generated questions in a Quiz
After you have saved questions into this plugin’s question bank, you can import them into a Quiz activity. Make sure your course already contains at least one ‘Quiz’ activity.

**Steps:**
1. Go to this plugin's page and click “Question bank” in the navigation bar. Then choose 'Export' (top-left menu).\
  <img src="img/01.png" width="500">
2. Select 'Moodle XML format', then click “Export questions to file” to download the file. (You may try other formats if needed.)\
  <img src="img/02.png" width="500">
3. Go to your Quiz activity and open its Question bank page. Choose 'Import' (top-left menu).\
  <img src="img/03.png" width="500">
4. Again select 'Moodle XML format', upload the exported file, and click 'Import'.\
  <img src="img/04.png" width="500">
5. The imported questions will appear immediately. If they don’t show up later, select the correct question category and click 'Apply filters' to display them again.\
  <img src="img/05.png" width="500">

## 3. Moodle 5 Limitation
### 3.1 Course Question Bank Shows Quiz Only
Moodle 5 only displays Quiz activities inside the course-level Question Bank interface.
Even though Moodle 5 supports module-level question banks for all activity types, the course Question Bank page is hard-coded to list only Quiz activities.

This is a Moodle core limitation.
The plugin correctly creates and stores its own module-level question bank and all questions, but Moodle will not show it in the course’s Question Bank UI unless Moodle core code is modified, which is not recommended.

#### 3.1 Evidence
**Moodle official documentation**\
Source: [Moodle Docs — Question banks in upgraded sites](https://docs.moodle.org/405/en/Question_banks_in_upgraded_sites)

> “For each course, the Question banks page shows
>> A: the course question bank
>> B: the question banks for each quiz in the course.”

The documentation explicitly states only Quiz activities are listed.

**Moodle developer documentation**\
Source: [Moodle Dev Docs — Question bank architecture](https://moodledev.io/docs/apis/subsystems/question/structure)\
Moodle explains that module-level question banks exist for all activities, but only Quiz is integrated into the course Question Bank interface. Other activities’ banks are not shown.

**Moodle core source code**\
In Moodle’s core code (`question/classes/local/bank/course_question_bank_navigation.php`), the list of “Other activities with questions” is built by scanning only Quiz instances:
```php
$quizmodules = get_module_instances_in_course('quiz', $course);
```
No other modules are checked, even if they own a question bank.