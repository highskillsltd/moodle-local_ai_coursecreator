<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hebrew language strings for local_ai_coursecreator.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'יוצר קורסים בבינה מלאכותית';

// Capability.
$string['ai_coursecreator:generate'] = 'יצירת קורסים באמצעות בינה מלאכותית';

// Role.
$string['rolename']        = 'יוצר קורסי בינה מלאכותית';
$string['roledescription'] = 'משתמשים בתפקיד זה יכולים ליצור קורסים בעזרת בינה מלאכותית. מבוסס על ארכיטיפ מנהל. ניתן להקצות ברמת מערכת או קטגוריה על ידי מנהלי אתר בלבד.';

// Admin settings.
$string['settings_stream_url']           = 'כתובת URL של נקודת הקצה';
$string['settings_stream_url_desc']      = 'כתובת URL מלאה של נקודת הקצה לסטרימינג של צינור הבינה מלאכותית, כולל נתיב הדייר (לדוגמה: https://api.example.com/api/v1/process-stream/your-tenant-id).';
$string['settings_api_key']              = 'מפתח API';
$string['settings_api_key_desc']         = 'מפתח הקס בן 64 תווים המוצג בממשק הניהול לאחר יצירת הדייר או חידוש המפתח.';
$string['settings_stream_timeout']          = 'פסק זמן לסטרימינג (שניות)';
$string['settings_stream_timeout_desc']     = 'מספר השניות המקסימלי להמתנה לסיום פעולת הבינה מלאכותית. הגדל עבור מסמכים גדולים (PDF, טקסטים ארוכים). מינימום 30. ברירת מחדל 600 (10 דקות).';
$string['settings_system_prompt']           = 'הנחיית מערכת';
$string['settings_system_prompt_desc']      = 'טקסט אופציונלי שמצורף לפני כל הגשת מקור קורס. השתמש בזה כדי לתת לבינה מלאכותית הוראות קבועות (לדוגמה: שפה, טון, תקני תכנית לימודים). השאר ריק כדי לשלוח רק את טקסט המורה.';
$string['settings_diagnostics_heading']     = 'אבחון';

// Index page.
$string['page_title']        = 'יצירת קורס באמצעות בינה מלאכותית';
$string['source_text_label'] = 'טקסט מקור לקורס';
$string['source_text_help']  = 'הדבק הערות הרצאה, סילבוס או כל מסמך שברצונך להפוך לקורס מודל.';
$string['generate_btn']         = 'צור קורס';
$string['include_images_label'] = 'כלול יצירת תמונות';
$string['progress_heading']  = 'התקדמות היצירה';

// Progress row labels.
$string['agent_analyst']   = 'סוכן 1: אנליסט';
$string['agent_architect'] = 'סוכן 2: אדריכל';
$string['agent_writer']    = 'סוכן 3: כותב';
$string['image_generator'] = 'מחולל תמונות';
$string['mbz_builder']     = 'בונה MBZ';

// Status labels.
$string['status_waiting'] = 'ממתין…';
$string['status_running'] = 'פועל…';
$string['status_done']    = 'הושלם';

// Result area.
$string['result_heading']     = 'הקורס מוכן';
$string['result_title_label'] = 'שם הקורס:';
$string['result_size_label']  = 'גודל הקובץ:';
$string['restore_btn']        = 'שחזר כקורס חדש';
$string['backup_area_link']   = 'צפה באזור הגיבוי';

// File upload.
$string['upload_label']    = 'העלאת קבצי מקור (אופציונלי)';
$string['upload_help']     = 'מותר: .txt, .csv, .html, .docx, .pdf. התוכן מחולץ ומשולב עם הטקסט שהוזן למעלה.';
$string['upload_no_input'] = 'אנא הזן טקסט מקור או העלה לפחות קובץ אחד.';
$string['input_too_large'] = 'תוכן המקור עולה על מגבלת 512 KB של טקסט רגיל. אנא הפחת את כמות הטקסט או פצל אותו על פני מספר יצירות.';

// Error.
$string['error_heading'] = 'היצירה נכשלה';
$string['error_unknown'] = 'אירעה שגיאה לא ידועה. אנא נסה שוב.';

// Restore.
$string['restore_success']   = 'הקורס שוחזר בהצלחה.';
$string['restore_failed']    = 'השחזור נכשל: {$a}';
$string['no_mbz_in_session'] = 'לא נמצא קורס שנוצר בסשן שלך. אנא צור קורס תחילה.';

// Security / config errors.
$string['api_not_configured'] = 'שירות יוצר הקורסים אינו מוגדר במלואו. אנא בקש מהמנהל להגדיר את כתובת נקודת הקצה ומפתח ה-API.';
$string['ssl_warning']        = 'אזהרה: אימות תעודת SSL מושבת מכיוון שכתובת ה-API משתמשת ב-HTTP. יש להגדיר HTTPS בסביבת ייצור.';
$string['curlerror']          = 'לא ניתן להתחבר לשירות הבינה מלאכותית: {$a}';
$string['apierror']           = 'שירות הבינה מלאכותית החזיר שגיאה: {$a}';
$string['ai_disclaimer']      = '⚠️ תוכן הקורס נוצר על ידי בינה מלאכותית ועשוי להכיל אי-דיוקים, מידע מיושן או שגיאות. יש לסקור ולאמת לפני פרסום לסטודנטים.';
