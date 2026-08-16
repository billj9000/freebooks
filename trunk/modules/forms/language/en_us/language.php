<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/forms/language/en_us/language.php
//

define('BOX_FORMS_DOCUMENTS',      'Documents (Forms)');
define('FORMS_PAGE_TITLE',         'Documents');
define('FORMS_PAGE_INTRO',         'Reports and forms, rendered by the Forms engine. PhreeForm continues to run alongside it.');

define('FORMS_COL_TITLE',          'Document');
define('FORMS_COL_GROUP',          'Group');
define('FORMS_COL_SERVED_BY',      'Served by');
define('FORMS_COL_ACTIONS',        'Run');

define('FORMS_SERVED_DATASET',     'dataset: %s');
define('FORMS_SERVED_PROVIDER',    'built in code: %s');
define('FORMS_SERVED_NOT_YET',     'not yet served by Forms');
define('FORMS_SERVED_FORM',        'a form — printed from a document');
define('FORMS_BTN_PRINT',          'Print…');

define('FORMS_LEGEND_OPTIONS',     'Run options');
define('FORMS_DATE_RANGE',         'Date range');
define('FORMS_PERIOD',             'Accounting period');
define('FORMS_GL_ACCOUNT',         'GL account');
define('FORMS_BTN_PDF',            'PDF');
define('FORMS_BTN_CSV',            'CSV');
define('FORMS_BTN_VIEW',           'View');

define('FORMS_ERROR_NOT_FOUND',    'No document with id %s.');
define('FORMS_ERROR_CANNOT_RUN',   'Forms cannot run this document: %s');
define('FORMS_ERROR_NO_FORMAT',    'Unknown output format: %s');
define('FORMS_ERROR_DELIVERY',     'The document was built but could not be sent: %s');
define('FORMS_ERROR_GL_REQUIRED',  'Choose a GL account before running the reconciliation.');
define('FORMS_WARN_CONVERSION',    'This report converted with warnings: %s');
define('FORMS_NO_DOCUMENTS',       'No documents are defined.');

/***** printing a form from the document that owns it (?module=forms&page=print) *****/
define('FORMS_PRINT_TITLE',        'Print Document');
define('FORMS_PRINT_CHOOSE',       'Choose a form');
define('FORMS_PRINT_CHOOSE_BTN',   'Use this form');
define('FORMS_PRINT_WHAT',         'What will be printed');
define('FORMS_PRINT_RECORDS',      'Records selected');
define('FORMS_PRINT_DELIVER',      'Deliver');
define('FORMS_PRINT_BTN_VIEW',     'View PDF');
define('FORMS_PRINT_BTN_DOWNLOAD', 'Download PDF');
define('FORMS_PRINT_BTN_EMAIL',    'Send email');
define('FORMS_PRINT_EMAIL',        'Email it instead');
define('FORMS_PRINT_EMAIL_TO',     'To address');
define('FORMS_PRINT_EMAIL_NAME',   'To name');
define('FORMS_PRINT_EMAIL_SUBJECT','Subject');
define('FORMS_PRINT_EMAIL_MESSAGE','Message');
define('FORMS_PRINT_EMAIL_BODY',   'Please find attached your %s from %s.');
define('FORMS_PRINT_EMAIL_NO_ADDRESS', 'Enter an address to send it to.');
define('FORMS_PRINT_NO_FORMS',     'No forms are defined in group %s.');
define('FORMS_PRINT_NOTHING_ASKED','No form was named. This page is opened from the document being printed.');
define('FORMS_PRINT_NO_RECORDS',   'Nothing was selected to print.');
define('FORMS_PRINT_OPEN_FROM_DOCUMENT', 'A form prints one record at a time, so it is opened from the document it belongs to — open the order, invoice or statement and use its Print button.');

// ── Dataset authoring (design §3.2, §3.3) ────────────────────────────────────
define('BOX_FORMS_DATASETS',        'Report Datasets');
define('BOX_FORMS_DESIGN',          'Report Editor');
define('FORMS_DS_TITLE',            'Report Datasets');
define('FORMS_DS_INTRO',            'A dataset is the set of tables a report author may draw fields from. Defining one is a separate privilege from writing or running reports, because it decides what data becomes reachable.');
define('FORMS_DS_SHIPPED_NOTE',     'Datasets shipped with FreeBooks are defined in code and cannot be edited here.');
define('FORMS_DS_COL_NAME',         'Name');
define('FORMS_DS_COL_LABEL',        'Label');
define('FORMS_DS_COL_TABLES',       'Tables');
define('FORMS_DS_COL_FIELDS',       'Fields');
define('FORMS_DS_COL_TIER',         'Origin');
define('FORMS_DS_TIER_SHIPPED',     'Shipped');
define('FORMS_DS_TIER_CUSTOM',      'Custom');
define('FORMS_DS_NEW',              'New dataset');
define('FORMS_DS_EDIT',             'Edit dataset');
define('FORMS_DS_NONE',             'No custom datasets have been defined.');

define('FORMS_DS_LEGEND_ABOUT',     'Dataset');
define('FORMS_DS_LEGEND_JOINS',     'Tables');
define('FORMS_DS_LEGEND_FIELDS',    'Fields');
define('FORMS_DS_NAME',             'Name');
define('FORMS_DS_NAME_HELP',        'Letters, digits and underscores. Used in stored report definitions, so it cannot be changed later.');
define('FORMS_DS_LABEL',            'Label');
define('FORMS_DS_DESCRIPTION',      'Description');
define('FORMS_DS_BASE_TABLE',       'Starting table');
define('FORMS_DS_ADD_JOIN',         'Add a table');
define('FORMS_DS_ADD_FIELD',        'Add a field');
define('FORMS_DS_REMOVE',           'Remove');
define('FORMS_DS_JOIN_TYPE',        'Include rows');
define('FORMS_DS_JOIN_INNER',       'Only where both match');
define('FORMS_DS_JOIN_LEFT',        'All, matched or not');
define('FORMS_DS_JOIN_TABLE',       'Table');
define('FORMS_DS_JOIN_MATCH',       'Matched on');
define('FORMS_DS_FIELD_KEY',        'Field name');
define('FORMS_DS_FIELD_LABEL',      'Shown as');
define('FORMS_DS_FIELD_SOURCE',     'Column');
define('FORMS_DS_FIELD_TYPE',       'Type');
define('FORMS_DS_NO_SQL_NOTE',      'Tables and columns are chosen from this database. There is no place to type SQL, by design.');

define('FORMS_DS_SAVED',            "Dataset '%s' saved.");
define('FORMS_DS_DELETED',          "Dataset '%s' deleted.");
define('FORMS_DS_ERROR',            'Dataset not saved: %s');
define('FORMS_DS_NOT_FOUND',        "There is no custom dataset named '%s'.");
define('FORMS_DS_IN_USE',           "Dataset '%s' cannot be deleted: %d report(s) still use it.");
define('FORMS_DS_CONFIRM_DELETE',   'Delete this dataset? Reports that use it will stop working.');
define('FORMS_DS_PROBLEM',          '%s');

// ── the report editor (design §13.7 step 2) ──────────────────────────────────
define('FORMS_DESIGN_TITLE',        'Report Editor');
define('FORMS_DESIGN_TITLE_FOR',    'Report Editor — %s');
define('FORMS_DESIGN_NO_REPORT',    'No report was chosen to edit.');
define('FORMS_DESIGN_PICK_ONE',     'Choose a report to edit. You can also reach this from the Edit button beside a report on the document list.');
define('FORMS_DESIGN_CHOOSE',       'Reports you can edit');
define('FORMS_DESIGN_BACK_TO_LIST', 'Back to the document list');
define('FORMS_DESIGN_EDIT',         'Edit');
define('FORMS_DESIGN_IS_FORM',      'This is a form, not a report. Forms are laid out on a page rather than in columns, and are edited in the form designer.');
define('FORMS_DESIGN_DENIED',       'You do not have permission to author reports.');
define('FORMS_DESIGN_CONVERT_WARNING', 'This report is still stored in the old format. Saving it here rewrites it in the Forms format, and the old report engine will no longer be able to open it. A copy of the original is kept alongside it.');
define('FORMS_DESIGN_SAVED',        "Report '%s' saved.");
define('FORMS_DESIGN_ERROR',        'Report not saved: %s');

define('FORMS_DESIGN_ABOUT',        'Report');
define('FORMS_DESIGN_NAME',         'Name');
define('FORMS_DESIGN_DESCRIPTION',  'Description');
define('FORMS_DESIGN_FROM_DATASET', 'Fields come from the %s dataset (%s). Only fields it declares can be used.');
define('FORMS_DESIGN_FROM_PROVIDER','This report is built by the %s calculation, so its columns are fixed and no new ones can be added.');

define('FORMS_DESIGN_COLUMNS',      'Columns');
define('FORMS_DESIGN_COL_FIELD',    'Field');
define('FORMS_DESIGN_COL_HEADING',  'Heading');
define('FORMS_DESIGN_COL_WIDTH',    'Width');
define('FORMS_DESIGN_COL_ALIGN',    'Align');
define('FORMS_DESIGN_COL_FORMAT',   'Format');
define('FORMS_DESIGN_COL_TOTAL',    'Total');
define('FORMS_DESIGN_ADD_COLUMN',   'Add a column');
define('FORMS_DESIGN_COL_BREAK',    'Break');
define('FORMS_DESIGN_COLUMNS_NOTE', 'Width is in millimetres; leave it empty to let the report decide. Break: ticked, the next field starts a new column across the page; unticked, it is printed underneath this one in the same column.');

define('FORMS_DESIGN_ALIGN_DEFAULT','Automatic');
define('FORMS_DESIGN_ALIGN_LEFT',   'Left');
define('FORMS_DESIGN_ALIGN_CENTER', 'Centre');
define('FORMS_DESIGN_ALIGN_RIGHT',  'Right');

define('FORMS_DESIGN_GROUPING',     'Grouping and totals');
define('FORMS_DESIGN_GROUP_BY',     'Group on');
define('FORMS_DESIGN_GROUP_NONE',   '(none)');
define('FORMS_DESIGN_GROUP_FORMAT', 'Group label format');
define('FORMS_DESIGN_TOTALS_ONLY',  'Show group totals only');
define('FORMS_DESIGN_GROUP_BREAK',  'Start a new page after each group');

define('FORMS_DESIGN_SORTING',      'Sorting');
define('FORMS_DESIGN_ADD_SORT',     'Add a sort');
define('FORMS_DESIGN_ASCENDING',    'Ascending');
define('FORMS_DESIGN_DESCENDING',   'Descending');

define('FORMS_DESIGN_FILTERS',      'Filters');
define('FORMS_DESIGN_FILTER_IS',    'Comparison');
define('FORMS_DESIGN_FILTER_VALUE', 'Value');
define('FORMS_DESIGN_FILTER_AND',   'and');
define('FORMS_DESIGN_FILTER_EXPRESSION', 'Compared against other columns. This filter is kept as it is; it cannot be edited here yet.');
define('FORMS_DESIGN_ADD_FILTER',   'Add a filter');
define('FORMS_DESIGN_FILTERS_NOTE', 'For "is one of", separate the values with commas.');

define('FORMS_DESIGN_PAGE',         'Page');
define('FORMS_DESIGN_PAPER',        'Paper');
define('FORMS_DESIGN_ORIENTATION',  'Orientation');
define('FORMS_DESIGN_PORTRAIT',     'Portrait');
define('FORMS_DESIGN_LANDSCAPE',    'Landscape');
define('FORMS_DESIGN_MARGINS',      'Margins');
define('FORMS_DESIGN_TOP',          'Top');
define('FORMS_DESIGN_BOTTOM',       'Bottom');
define('FORMS_DESIGN_LEFT',         'Left');
define('FORMS_DESIGN_RIGHT',        'Right');
define('FORMS_DESIGN_MM',           'Millimetres.');
define('FORMS_DESIGN_DATE_RANGE',   'Dates covered');
define('FORMS_DESIGN_DATE_FIELD',   'Date range applies to');
define('FORMS_DESIGN_TRUNCATE',     'Clip text to the column width');

define('FORMS_DESIGN_HEADINGS',     'Headings and styling');
define('FORMS_DESIGN_TITLE1',       'First title line');
define('FORMS_DESIGN_TITLE2',       'Second title line');
define('FORMS_DESIGN_TITLE_TOKENS', 'The title lines may contain %reportname% and %date%.');
define('FORMS_DESIGN_BAND',         'Part of the page');
define('FORMS_DESIGN_SHOWN',        'Shown');
define('FORMS_DESIGN_STYLED',       'Styled');
define('FORMS_DESIGN_FONT',         'Font');
define('FORMS_DESIGN_SIZE',         'Size');
define('FORMS_DESIGN_COLOUR',       'Colour');
define('FORMS_DESIGN_BAND_HEADING', 'Company name');
define('FORMS_DESIGN_BAND_TITLE1',  'First title');
define('FORMS_DESIGN_BAND_TITLE2',  'Second title');
define('FORMS_DESIGN_BAND_FILTER',  'Filter description');
define('FORMS_DESIGN_BAND_DATA',    'Columns and rows');
define('FORMS_DESIGN_BAND_TOTALS',  'Total rows');

define('FORMS_DESIGN_PREVIEW',      'Preview');
define('FORMS_DESIGN_PREVIEW_NOTE', 'The preview renders what is on this page, including changes you have not saved.');
define('FORMS_DESIGN_REFRESH',      'Refresh the preview');
define('FORMS_DESIGN_PREVIEW_EMPTY','This report returned no rows, so there is nothing to preview. Check the filters and the dates covered.');
define('FORMS_DESIGN_PREVIEW_DATES', 'Preview covering');
define('FORMS_DESIGN_PREVIEW_AS_SET','As the report is set');
define('FORMS_DESIGN_PREVIEW_SAMPLE','SAMPLE DATA — this report returned no rows, so the figures below are invented to show the layout.');

// The filter description drawn under a report's titles. PhreeForm printed the
// same line, which is why the band exists at all.
define('FORMS_FILTER_PREFIX',       'Report Filters: ');
define('FORMS_FILTER_GROUPED_BY',   'Grouped by: %s');
define('FORMS_FILTER_SORTED_BY',    'Sorted by: %s');
define('FORMS_FILTER_DATE_RANGE',   'Date range: %s to %s');
define('FORMS_FILTER_PERIOD',       'Accounting period: %s');

// Emailing a document — the run options card on the document list, and the
// extra recipients on the form print page.
define('FORMS_BTN_EMAIL',      'Email');
define('FORMS_EMAIL_LEGEND',   'Email a report');
define('FORMS_EMAIL_SHOW',     'Show / hide');
define('FORMS_EMAIL_NOTE',     'Fill these in, then use the Email button beside the report you want sent. It is emailed as a PDF, over the same date range and period chosen above.');
define('FORMS_EMAIL_FROM',     'From');
define('FORMS_EMAIL_CC',       'CC');
define('FORMS_EMAIL_BCC',      'BCC');

// ── the form canvas (design §13.7 step 3) ────────────────────────────────────
define('BOX_FORMS_CANVAS',        'Form Layout');
define('FORMS_CANVAS_TITLE',      'Form Layout');
define('FORMS_CANVAS_TITLE_FOR',  'Form Layout — %s');
define('FORMS_CANVAS_PICK_ONE',   'Choose a form to look at. You can also reach this from the Layout button beside a form on the document list.');
define('FORMS_CANVAS_CHOOSE',     'Forms you can look at');
define('FORMS_CANVAS_OPEN',       'Layout');
define('FORMS_CANVAS_IS_REPORT',  'This is a report, not a form. Reports are made of columns rather than positioned boxes, and are changed in the report editor.');
define('FORMS_CANVAS_READ_ONLY',  'This shows where each element sits on the printed page. Nothing here can be moved yet — the layout is shown so it can be checked, and editing comes next.');
define('FORMS_CANVAS_SAMPLED',    'There is no record on this company that this form would print, so the figures below are invented. The layout is the real one; only the data is not.');
define('FORMS_CANVAS_PAGE',       'The printed page');
define('FORMS_CANVAS_SHOW_BOXES', 'Show the elements');
define('FORMS_CANVAS_ELEMENTS',   'Elements');
define('FORMS_CANVAS_ELEMENT_COUNT', 'on this form. Point at one to find it on the page.');
define('FORMS_CANVAS_LOADING',    'Rendering the page…');
define('FORMS_CANVAS_FAILED',     'The page could not be rendered.');
define('FORMS_CANVAS_GROWS',      'The text continues below this box.');
define('FORMS_CANVAS_DASHED',     'A box with a dashed lower edge holds text that flows on below it — an address block, or any value long enough to wrap. The box marks where the text starts and how tall one line is, not where it ends.');
define('FORMS_CANVAS_UNMEASURED', 'The size of this box could not be worked out, so it is only indicative.');
