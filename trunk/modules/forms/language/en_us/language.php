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

// "(Forms)" was there to tell this apart from PhreeForm's own Reports entry.
// With that module deleted there is nothing to tell it apart from, and a
// qualifier that no longer qualifies anything is just noise in a menu.
define('BOX_FORMS_DOCUMENTS',      'Reports');
define('FORMS_PAGE_TITLE',         'Reports');
define('FORMS_PAGE_INTRO',         'Every report this company has. Forms — invoices, orders, statements — are printed from the document that owns them, and laid out under Tools &rarr; Form Layout.');

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
define('FORMS_BTN_MORE',           'More ways to run this');
define('FORMS_BTN_OPTIONS',        'Options…');
define('FORMS_RUN_DIALOG_TITLE',   'Run: %s');
define('FORMS_OPT_AS_SET',         'As the report is set');
define('FORMS_OPT_AS_SET_NAMED',   'As the report is set — %s');

define('FORMS_FILTER_PLACEHOLDER', 'Filter by name');
define('FORMS_FILTER_NO_MATCH',    'No document matches what you typed.');

define('FORMS_ERROR_NOT_FOUND',    'No document with id %s.');
define('FORMS_ERROR_CANNOT_RUN',   'Forms cannot run this document: %s');
define('FORMS_ERROR_NO_FORMAT',    'Unknown output format: %s');
define('FORMS_ERROR_DELIVERY',     'The document was built but could not be sent: %s');
define('FORMS_ERROR_GL_REQUIRED',  'Choose a GL account before running the reconciliation.');
define('FORMS_GL_NONE',            'This company has no cash or bank accounts, so there is nothing to reconcile. Add one under General Ledger &rarr; Chart of Accounts, then run this again.');
define('FORMS_WARN_CONVERSION',    'This report converted with warnings: %s');
define('FORMS_NO_DOCUMENTS',       'No documents are defined.');
define('FORMS_SEED_UNREADABLE',    'The shipped definition %s could not be read, so it was not installed.');
define('FORMS_ROW_IS_FORM',        'a form — laid out under Tools → Form Layout');

/***** printing a form from the document that owns it (?module=forms&page=print) *****/
define('FORMS_PRINT_TITLE',        'Print Document');
define('FORMS_PRINT_CHOOSE',       'Choose a form');
define('FORMS_PRINT_CHOOSE_BTN',   'Use this form');
define('FORMS_PRINT_WHAT',         'What will be printed');
define('FORMS_PRINT_RECORDS',      'Records selected');
define('FORMS_PRINT_DOCUMENT',     'Document');
define('FORMS_PRINT_FOR',          'For');
define('FORMS_PRINT_ONE_RECORD',   '%d record');
define('FORMS_PRINT_N_RECORDS',    '%d records');
define('FORMS_PRINT_AND_MORE',     'and %d more');
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
define('FORMS_DESIGN_PICK_ONE',     'No report was chosen to edit. Reports are edited from the Edit button beside them on the Reports list.');
define('FORMS_DESIGN_BACK_TO_LIST', 'Go to the Reports list');
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
define('FORMS_BTN_EMAIL',      'Email…');
define('FORMS_EMAIL_LEGEND',   'Email a report');
define('FORMS_EMAIL_NOTE',     'The report is attached as a PDF, covering exactly what the options above ask for. Leave the subject empty to send it under the report&rsquo;s own name.');
define('FORMS_EMAIL_FROM',     'From');
define('FORMS_EMAIL_CC',       'CC');
define('FORMS_EMAIL_BCC',      'BCC');

// ── the form canvas (design §13.7 step 3) ────────────────────────────────────
define('BOX_FORMS_CANVAS',        'Form Layout');
define('FORMS_CANVAS_TITLE',      'Form Layout');
define('FORMS_CANVAS_TITLE_FOR',  'Form Layout — %s');
define('FORMS_CANVAS_PICK_ONE',  'Every form this company has. Choose one to see where its elements sit on the printed page, and to move them. A receipt printer form opens in the receipt editor instead — it has no page and no positions, so its lines are built from character cells. A form is printed from the document it belongs to — an order, an invoice, a statement — not from here.');
define('FORMS_CANVAS_FOLDER',     'Folder');
define('FORMS_CANVAS_CHOOSE',     'Forms you can look at');
define('FORMS_CANVAS_OPEN',       'Layout');
define('FORMS_CANVAS_IS_REPORT',  'This is a report, not a form. Reports are made of columns rather than positioned boxes, and are changed in the report editor.');
// ── new, copy, rename and delete (§13.29) ────────────────────────────────────
define('FORMS_DOC_NEW',        'New');
define('FORMS_DOC_COPY',       'Copy');
define('FORMS_DOC_RENAME',     'Rename');
define('FORMS_DOC_DELETE',     'Delete');
define('FORMS_DOC_NAME',       'Name');
define('FORMS_DOC_FOLDER',     'Folder');
define('FORMS_DOC_DATASET',    'Built on');
define('FORMS_DOC_CREATED',    '%s was created. It is empty — add to it below.');
define('FORMS_DOC_COPIED',     '%s was copied. The original is unchanged.');
define('FORMS_DOC_RENAMED',    'Renamed to %s.');
define('FORMS_DOC_DELETED',    'The document was deleted.');
define('FORMS_DOC_WAS_LAST',   'That was the only document in its folder, so any Print button pointing there now has nothing to open.');
define('FORMS_DOC_NEW_REPORT', 'New report');
define('FORMS_DOC_NEW_FORM',   'New form');
define('FORMS_DOC_COPY_OF',    'Copy of %s');
define('FORMS_DOC_CONFIRM_DELETE', 'Delete this document? This cannot be undone from here.');
define('FORMS_DOC_CONFIRM_LAST',   'This is the only document in its folder. Deleting it leaves any Print button pointing there with nothing to open. Delete it anyway?');
define('FORMS_DOC_NEW_HELP',   'A new document starts empty. To start from one that is nearly right, copy that instead.');

// ── the receipt editor (§13.27) ──────────────────────────────────────────────
define('FORMS_RECEIPT_TITLE',      'Receipts');
define('FORMS_RECEIPT_TITLE_FOR',  'Receipt: %s');
define('FORMS_RECEIPT_BACK_TO_LIST', 'Back to the form list');
define('FORMS_RECEIPT_NO_LAYOUT',  'This is marked as a receipt printer form, but no character-cell layout could be read from it.');
define('FORMS_RECEIPT_ABOUT',      'This receipt');
define('FORMS_RECEIPT_NAME',       'Name');
define('FORMS_RECEIPT_PAPER',      'Paper width');
define('FORMS_RECEIPT_PAPER_HELP', 'How many characters fit across the paper. This is what a new field is given, and what the ruler above the preview measures — it does not cut anything short.');
define('FORMS_RECEIPT_LINES',      'Lines');
define('FORMS_RECEIPT_LINES_HELP', 'Fields print in this order. Several fields can share one line: each is written OVER the line built so far, which is how a label on the left and an amount on the right sit together. Tick Ends line on the last field of each line.');
define('FORMS_RECEIPT_COL_LINE',   'Line');
define('FORMS_RECEIPT_COL_PRINTS', 'Prints');
define('FORMS_RECEIPT_COL_WIDTH',  'Cell');
define('FORMS_RECEIPT_COL_ALIGN',  'Position');
define('FORMS_RECEIPT_COL_BREAK',  'Ends line');
define('FORMS_RECEIPT_COL_ACTION', 'Move');
define('FORMS_RECEIPT_ALIGN_L',    'Left');
define('FORMS_RECEIPT_ALIGN_C',    'Centred');
define('FORMS_RECEIPT_ALIGN_R',    'Right');
define('FORMS_RECEIPT_ADD_TEXT',   'Add a line of text');
define('FORMS_RECEIPT_ADD_HELP',   'Wording you type yourself. A field that prints a VALUE — a total, a date, the customer — is added where its value can be chosen.');
define('FORMS_RECEIPT_NEW_TEXT',   'New text');
define('FORMS_RECEIPT_KIND_TEXT',  'Text');
define('FORMS_RECEIPT_KIND_VALUE', 'Value');
define('FORMS_RECEIPT_KIND_BLOCK', 'Block');
define('FORMS_RECEIPT_KIND_TABLE', 'Items');
define('FORMS_RECEIPT_KIND_OTHER', 'Not printed');
define('FORMS_RECEIPT_PREVIEW',    'What prints');
define('FORMS_RECEIPT_PREVIEW_OF', 'A real sale (entry %d), printed through the receipt engine itself.');
define('FORMS_RECEIPT_NOT_A_RECEIPT', 'This is not a receipt printer form.');
define('FORMS_RECEIPT_PREVIEW_STALE', 'Showing what is saved. Change anything to see what would print.');
define('FORMS_RECEIPT_NO_SAMPLE',  'There is no point-of-sale entry on this company to print, so there is nothing to preview. The lines can still be edited.');
define('FORMS_CANVAS_IS_RECEIPT', 'Receipt');
define('FORMS_CANVAS_NAME',       'Name');
define('FORMS_CANVAS_OPEN_RECEIPT', 'Lines');
define('FORMS_CANVAS_READ_ONLY',  'This shows where each element sits on the printed page.');
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

// ── editing on the canvas (design §13.7 step 4) ──────────────────────────────
define('FORMS_CANVAS_INTRO',      'Drag an element to move it, or drag one of its edges or corners to resize it. Everything can also be typed exactly, on the right. The page redraws itself as you work; turn Auto redraw off if it feels slow, and use Redraw the page instead.');
define('FORMS_CANVAS_GROUPING',   'To move several elements together, shift-click each one, or start on empty space and drag a box that completely surrounds them. They keep their positions relative to one another.');
define('FORMS_CANVAS_SNAP',       'Snap to');
define('FORMS_CANVAS_SNAP_OFF',   'Off');
define('FORMS_CANVAS_SELECTED',   'Selected element');
define('FORMS_CANVAS_NOTHING',    'Click an element on the page, or a row in the list below.');
define('FORMS_CANVAS_MANY',       '%d elements are selected. Dragging, Delete and the order arrows apply to all of them; the boxes below describe the one named above.');
define('FORMS_CANVAS_X',          'Across (mm)');
define('FORMS_CANVAS_Y',          'Down (mm)');
define('FORMS_CANVAS_W',          'Width (mm)');
define('FORMS_CANVAS_H',          'Height (mm)');
define('FORMS_CANVAS_LINE_HEIGHT','Line height (mm)');
define('FORMS_CANVAS_TEXT',       'Text');
define('FORMS_CANVAS_LABEL',      'Name');
define('FORMS_CANVAS_LABEL_NOTE', 'What this element is called in the list. Leave it empty and it is described by what it is.');
define('FORMS_CANVAS_BIND',       'Shows');
define('FORMS_CANVAS_BIND_NOTE',  'Only the values this form can supply are offered.');
define('FORMS_CANVAS_BIND_COMPANY', 'One of your company details. Change it in Company Settings; it cannot be pointed at anything else here.');
define('FORMS_CANVAS_BORDER',     'Border width');
define('FORMS_CANVAS_BORDER_SHOW','Draw it');
define('FORMS_CANVAS_VISIBLE',    'Appears on');
define('FORMS_CANVAS_VIS_ALL',    'Every page');
define('FORMS_CANVAS_VIS_FIRST',  'The first page only');
define('FORMS_CANVAS_VIS_LAST',   'The last page only');
define('FORMS_CANVAS_VIS_CONTINUED', 'The last page, "Continued" before it');
define('FORMS_CANVAS_FORWARD',    'Bring forward');
define('FORMS_CANVAS_BACKWARD',   'Send backward');
define('FORMS_CANVAS_CONFIRM_DELETE', 'Remove this element from the form?');
define('FORMS_CANVAS_CONFIRM_DELETE_MANY', 'Remove these %d elements from the form?');
define('FORMS_CANVAS_DELETE_HINT', 'Remove the selected elements. The Delete key does the same.');
define('FORMS_CANVAS_ADD',        'Add an element');
define('FORMS_CANVAS_ADD_TEXT',   'Text');
define('FORMS_CANVAS_ADD_FIELD',  'Value');
define('FORMS_CANVAS_ADD_LINE',   'Line');
define('FORMS_CANVAS_ADD_RECT',   'Box');
define('FORMS_CANVAS_ADD_IMAGE',  'Image');
define('FORMS_CANVAS_ADD_BLOCK',   'Address block');
define('FORMS_CANVAS_ADD_TABLE',   'Line items');
define('FORMS_CANVAS_ADD_PAGENUM', 'Page number');
define('FORMS_CANVAS_ADD_BARCODE', 'Barcode');
define('FORMS_CANVAS_ADD_NOTE',   'A new element appears near the top-left of the page. Drag it where you want it.');
define('FORMS_CANVAS_NEW',        'New element');
define('FORMS_CANVAS_IMAGE',      'Image file');
define('FORMS_CANVAS_IMAGE_NOTE', 'The images this company has. Add another below.');
define('FORMS_CANVAS_UPLOAD',     'Add an image');
define('FORMS_CANVAS_UPLOAD_GO',  'Upload');
define('FORMS_CANVAS_UPLOAD_NOTE','PNG, JPEG or GIF, up to %d MB. It is added to this company only, and the element you have selected will use it.');
define('FORMS_CANVAS_UPLOADING',  'Uploading…');
define('FORMS_CANVAS_UPLOADED',   '%s was added.');
define('FORMS_UPLOAD_NOTHING',    'No image was chosen.');
define('FORMS_UPLOAD_FAILED',     'The image could not be saved.');
define('FORMS_CANVAS_RERENDER',   'Redraw the page');
define('FORMS_CANVAS_FONT_DEFAULT', 'The report default');
define('FORMS_CANVAS_FONT_OTHER',   '%s (already in use)');
define('FORMS_CANVAS_BORDER_COLOUR', 'Border colour');
define('FORMS_CANVAS_BORDER_NOTE', 'In points. 0 is a hairline, not "no border".');
define('FORMS_CANVAS_TABLE_ON_TOP', 'The line-item table is always drawn over the other elements, whatever its place in this order.');

// The line-item table's columns, and the address block's lines (§13.30).
define('FORMS_CANVAS_COLUMNS',      'Columns');
define('FORMS_CANVAS_COLUMNS_EDIT', 'Columns (%d)…');
define('FORMS_CANVAS_COLUMNS_NONE', 'This table has no columns yet, so it prints nothing. Add one below.');
define('FORMS_CANVAS_COLUMNS_TITLE','Columns of %s');
define('FORMS_CANVAS_COL_ADD',      'Add a column');
define('FORMS_CANVAS_COL_HEADING',  'Heading');
define('FORMS_CANVAS_COL_WIDTH',    'Width');
define('FORMS_CANVAS_COL_FORMAT',   'Format');
// ── value formats (§13.31) ──────────────────────────────────────────────────
//
// The labels for Formatter::CHOOSABLE. A hint's stored name is what goes in the
// file; this is what an author reads. Each names the RESULT rather than the
// mechanism, because "Money, in the document's currency" is a choice somebody
// can make and `posted_cur` is a thing they have to already know.
//
// The stored name is kept in the label, in brackets. It is what appears in a
// converted definition, what the manual is indexed by, and what anyone
// comparing two forms will see - hiding it would make the list easier to read
// once and harder to use twice.
define('FORMS_FORMAT_NONE',       'As stored');
define('FORMS_FORMAT_GROUP_MONEY','Money');
define('FORMS_FORMAT_GROUP_NUMBER','Numbers');
define('FORMS_FORMAT_GROUP_TEXT', 'Dates and text');
define('FORMS_FORMAT_GROUP_CODE', 'Codes turned into words');
define('FORMS_FORMAT_OTHER',      '%s (already in use)');
define('FORMS_FORMAT_GROUP_OTHER','Already used by this document');

define('FORMS_FORMAT_POSTED_CUR', "Money, in the document's currency (posted_cur)");
define('FORMS_FORMAT_NULL_PCUR',  "Money, in the document's currency; blank if zero (null_pcur)");
define('FORMS_FORMAT_DEF_CUR',    "Money, in the company's currency (def_cur)");
define('FORMS_FORMAT_NULL_DCUR',  "Money, in the company's currency; blank if zero (null_dcur)");
define('FORMS_FORMAT_DLR',        'Money, always in dollars (dlr)');
define('FORMS_FORMAT_NULL_DLR',   'Money, always in dollars; blank if zero (null-dlr)');
define('FORMS_FORMAT_EURO',       'Money, always in euros (euro)');

define('FORMS_FORMAT_RND_DEC',    'Number, to the usual decimal places (rnd_dec)');
define('FORMS_FORMAT_RND_PRE',    'Number, to the precise decimal places (rnd_pre)');
define('FORMS_FORMAT_RND2D',      'Number, rounded to two decimals (rnd2d)');
define('FORMS_FORMAT_NEG',        'Number, with the sign flipped (neg)');
define('FORMS_FORMAT_N2WRD',      'Number, spelled out in words (n2wrd)');

define('FORMS_FORMAT_DATE',       'Date, in this installation\'s format (date)');
define('FORMS_FORMAT_UC',         'Text, in capitals (uc)');
define('FORMS_FORMAT_LC',         'Text, in lower case (lc)');
define('FORMS_FORMAT_BLANK',      'Print nothing (blank)');

define('FORMS_FORMAT_TERMS',      'Payment terms, written out (terms)');
define('FORMS_FORMAT_SHIP_NAME',  'Shipping method name (ship_name)');
define('FORMS_FORMAT_J_DESC',     'Journal name, from its number (j_desc)');
define('FORMS_FORMAT_YESBNO',     '"Yes", or blank (yesBno)');
define('FORMS_FORMAT_NEWUSED',    '"New" or "Used" (NewUsed)');
define('FORMS_FORMAT_PRINTED',    '"Duplicate" on a reprint (printed)');
define('FORMS_FORMAT_COA_TYPE',   'Account type name (coa_type)');
define('FORMS_FORMAT_CVT_ISO2',   'Country code, two letters from three (cvt_iso2)');
define('FORMS_FORMAT_CVT_CNTRY',  'Country name, from the code (cvt_cntry)');

define('FORMS_CANVAS_COL_NOTE',     'Widths are in millimetres and are shared out across the table. A blank width lets the column take what is left.');

define('FORMS_CANVAS_PARTS',        'Lines');
define('FORMS_CANVAS_PARTS_EDIT',   'Lines (%d)…');
define('FORMS_CANVAS_PARTS_NONE',   'This block has no lines yet, so it prints nothing. Add one below.');
define('FORMS_CANVAS_PARTS_TITLE',  'Lines of %s');
define('FORMS_CANVAS_PART_ADD',     'Add a line');
define('FORMS_CANVAS_PART_JOIN',    'Followed by');
define('FORMS_CANVAS_PART_NOTE',    'Each line is joined to the next by what you choose here. The block is one box of text, so this is what puts the town on its own line.');
define('FORMS_CANVAS_PART_FIXED',   'A company detail, kept as it is.');

define('FORMS_CANVAS_SEP_NONE',     'Nothing');
define('FORMS_CANVAS_SEP_NL',       'A new line');
define('FORMS_CANVAS_SEP_DEL_NL',   'A new line, unless it is empty');
define('FORMS_CANVAS_SEP_SP',       'A space');
define('FORMS_CANVAS_SEP_2SP',      'Two spaces');
define('FORMS_CANVAS_SEP_COMMA',    'A comma');
define('FORMS_CANVAS_SEP_COM_SP',   'A comma and a space');
define('FORMS_CANVAS_SEP_SEMI_SP',  'A semicolon and a space');

define('FORMS_CANVAS_PATTERN',      'Wording');
define('FORMS_CANVAS_PATTERN_NOTE', 'Use {page} for the page number, {pages} for the total and {of} for the word "of".');
define('FORMS_CANVAS_SYMBOLOGY',       'Barcode type');
define('FORMS_CANVAS_SYMBOLOGY_NOTE',  'Every form converted from the old designer uses Code 128. Change it only if your scanner needs another.');
define('FORMS_CANVAS_SYMBOLOGY_OTHER', '%s (already in use)');
define('FORMS_CANVAS_SUB_DONE',     'Done');
define('FORMS_CANVAS_BOLD',   'Bold');
define('FORMS_CANVAS_ITALIC', 'Italic');
define('FORMS_CANVAS_LIVE',     'Auto redraw');
define('FORMS_CANVAS_DRAWING',  'Redrawing…');
define('FORMS_CANVAS_UNDO',      'Undo');
define('FORMS_CANVAS_REDO',      'Redo');
define('FORMS_CANVAS_UNDO_HINT', 'Undo the last change (Ctrl+Z). Undo history is lost when you leave the page.');
define('FORMS_CANVAS_REDO_HINT', 'Redo the change you just undid (Ctrl+Y).');

/***** the faces a document may be drawn in *****/
define('FORMS_FONT_HELVETICA', 'Helvetica');
define('FORMS_FONT_COURIER',   'Courier');
define('FORMS_FONT_TIMES',     'Times Roman');
define('FORMS_FONT_SERIF',     'Serif');

/***** labels the renderers draw *****/
define('FORMS_TABLE_CONTINUED',    'Continued');
define('FORMS_GROUP_TOTAL_FOR',    'Group Total For:');
define('FORMS_REPORT_TOTAL_FOR',   'Report Total For:');

/***** seeding a company's documents *****/
define('FORMS_SEED_DUPLICATE',     'A document called "%s" already exists, so it was not replaced.');
define('FORMS_SEED_WRITE_ERROR',   'The document definition could not be written to %s.');
define('FORMS_SEED_UNREADABLE',    'The document definition "%s" could not be read, so it was not installed.');

/***** the paper a document may sit on *****/
define('FORMS_PAPER_LEGAL',        'Legal');
define('FORMS_PAPER_LETTER',       'Letter');
define('FORMS_PAPER_TABLOID',      'Tabloid');
