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

Although the core workflow is implemented and usable, several limitations and extension points remain. These are based on both technical constraints of Moodle and feedback gathered from teachers and stakeholders.

One important limitation is the Question Bank context. Generated questions are currently inserted into the module-level Question Bank. This behavior is determined by Moodle core APIs and not by the plugin itself. As a result, teachers must manually export questions from the module context and then import them into the course-level Question Bank before they can be used in a Quiz activity. If this limitation remains in future Moodle versions, an additional helper or automation mechanism could be introduced to reduce the amount of manual work required from teachers.

Another aspect concerns the internal structure of the codebase. Each question type is currently implemented in separate files, and many parts of the logic are very similar, especially form handling, saving generated data, validation, and importing questions into the Question Bank. While this separation makes each question type easy to follow in isolation, it also results in duplicated code. Once all supported question types are considered stable, shared logic could be extracted into common helper functions or base abstractions to improve maintainability.

The plugin also relies on a single LLM endpoint defined in a local configuration file. If this external service becomes unavailable, question generation cannot proceed. Although prompts and request formats can be adjusted, switching to another LLM backend or an external API currently requires manual code changes. Supporting multiple or configurable LLM backends would make the system more robust and adaptable to different deployment environments.

Feedback handling is another area with room for improvement. For short answer questions, feedback often requires teacher judgment and may depend on how students phrase their answers. In these cases, it is reasonable for teachers to manually write or adjust feedback. However, for objective question types such as True / False and Multiple Choice (Single Answer), feedback can be more standardized. Because correct and incorrect options are clearly defined, the LLM could automatically generate template-based feedback explaining why an answer is correct or incorrect. Introducing automatic feedback generation for these question types could reduce teachers’ workload while maintaining consistent feedback quality.

Answer strictness for Fill in the Blank questions is also a point of discussion. In real classroom scenarios, students may make minor variations such as differences in capitalization, missing spaces, or small spelling mistakes, even when their conceptual understanding is correct. Currently, answers are matched strictly against predefined correct values. Teachers have indicated that they may want more flexible matching rules. This requires further discussion with teachers to clarify expectations and to decide which types of deviations should be accepted. Based on these requirements, future development could introduce configurable answer-matching strategies, such as case-insensitive comparison or relaxed string matching.

Another important extension point is support for additional question types. At the moment, the plugin only supports generating True / False, Multiple Choice (Single Answer), and Fill in the Blank questions. Moodle’s Question Bank, however, supports many other question types, such as Short Answer, Matching, and potentially others depending on installed plugins. Future development may involve adding support for more question types, which would require new prompt designs, validation logic, form structures, and import handling. This further emphasizes the need for a more modular and extensible internal architecture.

Finally, the current prompt design is tailored specifically for ICT-related teaching materials. This specialization was chosen to maximize output stability and quality for the original use case. However, it limits the plugin’s applicability to other subjects. To support broader educational contexts, subject-specific or configurable prompt templates should be designed in the future, allowing question generation to adapt to different disciplines while maintaining predictable output formats.

### Potential future work (to-do list)

Based on the points above, the following tasks could guide future development:

* Investigate whether Moodle core APIs allow more direct insertion into course-level Question Banks, or design helper tools to streamline question transfer.
* Refactor duplicated logic across question types into shared utilities or base abstractions.
* Introduce support for multiple or configurable LLM backends, with graceful fallback or clearer error handling.
* Implement automatic, template-based feedback generation for objective question types (True / False, Multiple Choice).
* Define and implement configurable answer-matching rules for Fill in the Blank questions, based on teacher requirements.
* Extend support to additional Moodle question types (e.g. Short Answer, Matching), including prompt design, validation, and import logic.
* Redesign the prompt system to support multiple subjects through subject-aware or configurable prompt templates.
* Validate new features iteratively with teachers to ensure alignment with real teaching and assessment practices.

# 4. Development considerations

AutoGenQuiz depends heavily on Moodle core APIs, especially those related to the Question Bank. Changes in Moodle versions may affect how questions are created, categorized, or reused across different contexts, so these APIs should be reviewed when upgrading Moodle.

For development and testing, a reproducible environment such as a Docker-based Moodle setup is recommended. This is especially important for debugging file uploads, text extraction, and long-running generation requests.

Because question generation depends on an external service, availability and response format must always be considered part of the system boundary. Clear error handling and user feedback are therefore essential to the overall user experience.
