# 1. Project description

AutoGenQuiz is a Moodle activity plugin that helps teachers generate quiz questions from teaching materials using a Large Language Model (LLM).

The plugin is used inside a course as a normal Moodle activity. Teachers upload teaching materials, review the extracted text, choose a question type, and generate questions based on that text. The generated questions can be edited and then imported into Moodle’s Question Bank, where they can later be used in Quiz activities.

The implementation follows Moodle’s standard permission system and relies on Moodle core Question Bank APIs.

# 2. What is currently implemented

The plugin already provides a complete workflow from file upload to Question Bank import.

Teachers can upload a single PDF or PPTX file, with a maximum size of 80 MB. After upload, the plugin extracts text from the file and stores both the file metadata and extracted text in the database. The extracted text is displayed on the activity page and is read-only by default. Teachers can switch to edit mode, correct extraction errors, and save the confirmed version of the text. Only the confirmed text is used in later steps.

Once the text is confirmed, the teacher chooses a question type and the number of questions to generate. The plugin sends the confirmed text to an external LLM server and expects a strict JSON response. Connection errors and invalid responses are detected and reported to the user.

At the moment, three question types are supported: True / False, Multiple Choice (Single Answer), and Fill in the Blank. Each question type has its own generation page but follows the same overall interaction pattern. Generated questions are displayed in editable forms, where teachers can change question text, answers, or options, or remove questions entirely before saving.

After review, questions can be imported into Moodle’s Question Bank using Moodle core APIs. Each generation task and its result are stored in the database, allowing the plugin to track the source file, generated content, and import status.

# 3. Aspects that can still be improved or extended

Although the main workflow is implemented, some limitations and open points remain.

One important limitation is the Question Bank context. Generated questions are currently inserted into the module-level Question Bank. This behavior is determined by Moodle core and not by the plugin itself. As a result, teachers must manually export questions from the plugin’s Question Bank and then import them into the course-level Question Bank before adding them to a Quiz. If this limitation continues in future Moodle versions, an additional helper or automation mechanism could reduce the amount of manual work required from teachers.

Another aspect is the internal structure of the code. Each question type is handled in separate files, and many parts of the logic are very similar, especially for form handling, saving generated data, and importing questions. This makes the current behavior easy to understand, but it also leads to duplicated code. Once all question types are stable, shared logic could be extracted to simplify maintenance.

The plugin also relies on a single LLM endpoint defined in a local configuration file. If this service becomes unavailable, question generation cannot proceed. While the current structure allows prompts and requests to be adjusted, switching to another LLM or an external API still requires manual changes. Supporting alternative LLM backends would make the system more flexible in different environments.

# 4. Development considerations

AutoGenQuiz depends heavily on Moodle core APIs, especially those related to the Question Bank. Changes in Moodle versions may affect how questions are created, categorized, or reused across different contexts, so these APIs should be reviewed when upgrading Moodle.

For development and testing, a reproducible environment such as a Docker-based Moodle setup is recommended. This is especially important for debugging file uploads, text extraction, and long-running generation requests.

Because question generation depends on an external service, availability and response format must always be considered part of the system boundary. Clear error handling and user feedback are therefore essential to the overall user experience.
